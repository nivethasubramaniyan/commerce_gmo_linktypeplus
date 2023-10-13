<?php

namespace Drupal\commerce_gmo_linktypeplus\Controller;

use Drupal\commerce_gmo_linktypeplus\Event\LinkTypePlusEvent;
use Drupal\commerce_gmo_linktypeplus\ResponseData;
use Drupal\commerce_order\Entity\Order;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Routing\Access\AccessInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * GMO LinkType Plus Controller process the response of the LinkType integrate with our commerce_payment.
 */
class GmoLinkTypePlusController extends ControllerBase implements ContainerInjectionInterface, AccessInterface {

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;


  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Var used to track the payment status .
   *
   * @var defaultPaymentStatus
   */
  public $defaultPaymentStatus = 'new';

  /**
   * Logger Factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $loggerFactory;


  /**
   * The current request.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected $currentRequest;

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * GmoLinkTypePlusController constructor.
   *
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $loggerFactory
   *   Logger .
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $eventDispatcher
   *   The event dispatcher .
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   Messenger .
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   Config factory.
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   The request stack.
   */
  public function __construct(LoggerChannelFactoryInterface $loggerFactory, EventDispatcherInterface $eventDispatcher, EntityTypeManagerInterface $entityTypeManager, MessengerInterface $messenger, ConfigFactoryInterface $config_factory, RequestStack $requestStack) {
    $this->loggerFactory = $loggerFactory->get('commerce_gmo_linktypeplus');
    $this->eventDispatcher = $eventDispatcher;
    $this->entityTypeManager = $entityTypeManager;
    $this->messenger = $messenger;
    $this->configFactory = $config_factory;
    $this->requestStack = $requestStack;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('logger.factory'),
      $container->get('event_dispatcher'),
      $container->get('entity_type.manager'),
      $container->get('messenger'),
      $container->get('config.factory'),
      $container->get('request_stack'),
    );
  }

  /**
   * Processes the post data from LinkTypePlus and integrating with commerce_payment.
   */
  public function responseProcessor(Request $request) {
    try {
      $hashedData = $request->request->get('result');
      if (!isset($hashedData)) {
        throw new \Exception("Error in processing");
      }
      $resultData = $this->preProcessingResult($hashedData);
      $first_array = array_shift($resultData);
      $second_array = array_pop($resultData);
      if (is_array($second_array) && !empty($second_array)) {
        $data = array_merge($first_array, $second_array);
      }
      else {
        $data = array_merge($first_array, []);
      }

      $this->loggerFactory->notice('<pre><code>' . print_r($data, TRUE) . '</code></pre>');
      $responseObj = new ResponseData($data);

      // If the order is failure or cancelled, then redirect to review
      // page and show the status message.
      $order_id = $responseObj->orderId;
      $linkTypeState = $responseObj->status;
      $remote_id = $responseObj->remoteId;
      
      // print_r($order->getState()->getId());exit;

      //remove version from orderId
      $order_id = explode("-", $order_id)[0];
      $order = Order::load($order_id);
      if ($linkTypeState != 'PAYSUCCESS') {
        if ($linkTypeState == 'ERROR') {
          $redirect = new RedirectResponse('/checkout/' . $order_id . '/review');
          $str = "Payment has been failed. Please check the payment details.";
        }
        elseif ($linkTypeState == 'PAYSTART') {
          $redirect = new RedirectResponse('/checkout/' . $order_id . '/review');
          $str = "Please review the payment details. Payment has been cancelled.";
        }
        elseif ($linkTypeState == 'REQSUCCESS') {
          $this->statusMapper($linkTypeState);
          $payment_storage = $this->entityTypeManager->getStorage('commerce_payment');
          $paymentGateway = $order->get('payment_gateway')->entity->id();
          $total_price = $order->getTotalprice()->getNumber();
          $currency = $order->getTotalprice()->getCurrencyCode();
          $payment = $payment_storage->create([
            'state' => $this->defaultPaymentStatus,
            'payment_gateway' => $paymentGateway,
            'remote_id' => $remote_id,
            'amount' => [
              'number' => $total_price,
              'currency_code' => $currency,
            ],
            'order_id' => $order_id,
            'completed' => time(),
          ]);
          $payment->save();
          $redirect = new RedirectResponse('/checkout/' . $order_id . '/complete');
          $str = 'Order Place Request recieved successfully. Your order will be 
          updated soon.';
        }else{
          $str = "Please review the payment details.";
        }
        $this->messenger()->addWarning($str);
        return $redirect;
      }else{
        $response = $this->updateLinkTypePaymentStatus(
          $responseObj->orderId,
          $responseObj->paymentMethod,
          $responseObj->status,
          $responseObj->remoteId,
          $data,
          TRUE
        );
        return $response;
      }
    }
    catch (\Exception $e) {
      $this->loggerFactory->error($e->getMessage());
    }
  }

  /**
   * Processing the hashed data in the required format.
   */
  public function preProcessingResult($hashedData) {
    $decoded = base64_decode($hashedData);
    $resultData = Json::decode(explode('}}', $decoded)[0] . '}}');
    return $resultData;
  }

  /**
   * Updating the payment status.
   */
  public function updateLinkTypePaymentStatus($order_id, $payment_method, $linkTypeState, $remote_id, $data, $success_page = FALSE) {
    try {
      $order = Order::load($order_id);
      $payment_storage = $this->entityTypeManager->getStorage('commerce_payment');
      $paymentGateway = $order->get('payment_gateway')->entity->id();
      $total_price = $order->getTotalprice()->getNumber();
      $currency = $order->getTotalprice()->getCurrencyCode();
      $payment = $payment_storage->loadByProperties([
        'order_id' => $order_id,
      ]);
      $this->statusMapper($linkTypeState);
      if ($payment) {
        $payment = array_shift($payment);
        $payment->setState($this->defaultPaymentStatus);
        $payment->setRemoteId($remote_id);
        $payment->setCompletedTime(\Drupal::time()->getRequestTime());
        $payment->save();
      }
      else {
        $payment = $payment_storage->create([
          'state' => $this->defaultPaymentStatus,
          // Should be made configurable.
          'payment_gateway' => $paymentGateway,
          'remote_id' => $remote_id,
          'amount' => [
            'number' => $total_price,
            'currency_code' => $currency,
          ],
          'order_id' => $order_id,
          'completed' => time(),
        ]);
        $payment->save();
      }
      if ($success_page) {
        $order->unlock();
        $order->setData($paymentGateway, $data);
        if ($order->getState()->getId() != 'completed') {
          $order->getState()->applyTransitionById('place');
        }
        $order->save();
        $redirect = new RedirectResponse('/checkout/' . $order_id . '/complete');
        $this->messenger()->addStatus('Order placed successfully');
        return $redirect;
      }
    }
    catch (\Exception $e) {
      $this->loggerFactory->error($e->getMessage());
    }
  }

  /**
   * Method to check whether the value exists or not .
   */
  public function checkValueExists($val) {
    $val = $val ? $val : ' ';
    return $val;
  }

  /**
   * Method where we map the LinkType Result with our commerce payment status.
   *
   * Refer : https://docs.mul-pay.jp/linkplus/payment/common.
   */
  public function statusMapper($state) {
    switch ($state) {
      case 'REQPROCESS':
      case 'REQSUCCESS':
        $this->defaultPaymentStatus = 'authorization';
        break;

      case 'PAYSTART':
        $this->defaultPaymentStatus = 'new';
        break;

      case 'ERROR':
        $this->defaultPaymentStatus = 'failed';
        break;

      case 'PAYSUCCESS':
        $this->defaultPaymentStatus = 'completed';
        break;

      case 'EXPIRED':
      case 'INVALID':
      case 'ERROR':
        $this->defaultPaymentStatus = 'authorization_expired';
        break;

      default:
        $this->defaultPaymentStatus = 'new';
    }
  }

  /**
   * Processing response data sent through webhook.
   */
  public function responseSaver(Request $request) {
    try {
      $data = $request->request->all();
      $this->loggerFactory->notice("in response saver");
      $file=fopen("sites/default/files/webhook.txt","a+");
      $con = file_get_contents("webhook.txt");
      echo fwrite($file,"=======".date("d-m-Y h:i:s")."========\n");
      echo fwrite($file,  serialize($data)."\n");
      echo fwrite($file,"=======================================\n");
      fclose($file);
      if ($this->updateEventSubscriber($data)) {
        return new JsonResponse(0);
      }
    }
    catch (\Exception $e) {
      $this->loggerFactory->error($e->getMessage());
    }
    return new JsonResponse(1);
  }

  /**
   * Call the EventSuscriber to update/ log the status.
   *
   * @param array $data
   *   The GMO api response data.
   */
  public function updateEventSubscriber($data) {
    // Dispatch the custom event.
    $event = new LinkTypePlusEvent($data);
    $paymentMethod = $event->getPaymentMethod();
    return $this->eventDispatcher->dispatch($paymentMethod, $event);
  }

  /**
   * Custom access callback on the success and Webhook processor.
   * It will block the requests which are not coming from GMO.
   */
  public function accessCallback() {
    // Get the current request object.
    $request = $this->requestStack->getCurrentRequest();
    // Check if the request has a referrer.
    $referrer = $request->headers->get('referer');
    $allowedDomain = ['https://stg.link.mul-pay.jp/', 'https://link.mul-pay.jp/'];
    if (in_array($referrer, $allowedDomain)) {
      return AccessResult::allowed();
    }
    return AccessResult::allowed();
  }

  /**
   *
   */
  public function recurringCreditWebhook(Request $request) {
    $data = $request->request->all();
    \Drupal::logger('recurringCreditWebhook')->notice('<pre><code>' . print_r($data, TRUE) . '</code></pre>');
    $file=fopen("sites/default/files/data.txt","a+");
    $con = file_get_contents("data.txt");
    echo fwrite($file,"=======".date("d-m-Y h:i:s")."========\n");
    echo fwrite($file,  serialize($data)."\n");
    echo fwrite($file,"=======================================\n");
    fclose($file);
    return new JsonResponse(0);
  }

}
