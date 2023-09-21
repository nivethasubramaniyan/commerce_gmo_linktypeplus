<?php

namespace Drupal\commerce_gmo_linktypeplus\Controller;

use Drupal\commerce_gmo_linktypeplus\Event\LinkTypePlusEvent;
use Drupal\commerce_gmo_linktypeplus\ResponseData;
use Drupal\commerce_order\Entity\Order;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Routing\Access\AccessInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Drupal\Core\Routing\RouteMatchInterface;

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
   *  Config factory
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
      if(is_array($second_array) && !empty($second_array)){
        $data = array_merge($first_array, $second_array);
      }else{
        $data = array_merge($first_array, []);
      }
      
      $this->loggerFactory->notice('<pre><code>' . print_r($data, TRUE) . '</code></pre>');
      $responseObj = new ResponseData($data);
      
      $this->updateLinkTypePaymentStatus(
        $responseObj->orderId,
        $responseObj->paymentMethod,
        $responseObj->status,
        $responseObj->remoteId,
        $data,
        TRUE
      );
      return new JsonResponse(0);
    }
    catch (\Exception $e) {
      $this->loggerFactory->error($e->getMessage());
      return new JsonResponse(1);
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
      // $state = $order->get('state')->value;
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
          'completed' => time()
        ]);
        $payment->save();
      }
      $order->unlock();
      $order->setData($paymentGateway, $data);
      // It should be dynamic based on the payment status.
      if ($order->getState()->getId() != 'completed') {
        $order->getState()->applyTransitionById('place');
      }
      $order->save();

      // Check  heck the status and if its failure then show
      // status message
      // print_r($linkTypeState);exit;
      if( $linkTypeState != 'PAYSUCCESS' ){
        if($linkTypeState == 'ERROR'){
          $this->messenger()->addError("Payment has been failed. Please check the payment details.");
        } else if($linkTypeState == 'PAYSTART'){
          $this->messenger()->addWarning("Please review the payment details again.");
        } else{
          $this->messenger()->addWarning("Please review the payment details.");
        }
        $redirect = new RedirectResponse('/checkout/' .$order_id . '/review');
        $redirect->send();
      }else if ($success_page) {
        $this->messenger()->addStatus('Order placed successfully');
        $redirect = new RedirectResponse('/checkout/' . $order_id . '/complete');
        $redirect->send();
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
        $this->defaultPaymentStatus = 'Payment Started';
        break;
      case 'ERROR':
          $this->defaultPaymentStatus = 'Payment Failed';
          break;
      case 'PAYSUCCESS':
        $this->defaultPaymentStatus = 'Completed';
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
   * Method used to track the linktype status with the drupal commerce_payment.
   *
   * We got these status through webhook.
   *
   * It may differs for credit card, paypay etc.
   *
   * Refer: https://docs.mul-pay.jp/payment/credit/notice
   *        https://docs.mul-pay.jp/paypay/payg-notice
   *        https://docs.mul-pay.jp/
   *
   * THIS SECTION NEEDS TO BE EXTENDED.
   */
  public function webhookStatusMapper($state) {
    switch ($state) {
      case 'SAUTH':
      case 'AUTH':
        $this->defaultPaymentStatus = 'Webhook Completed';
        break;
      case 'REQSUCCESS':
      case 'AUTHPROCESS':
        $this->defaultPaymentStatus = 'authorization';
        break;
      case 'PAYSTART':
        $this->defaultPaymentStatus = 'Payment Started';
        break;
      case 'ERROR':
          $this->defaultPaymentStatus = 'Payment Failed';
          break;
      case 'PAYSUCCESS':
        $this->defaultPaymentStatus = 'Completed';
        break;
      case 'SALES':
      case 'TRADING':
          $this->defaultPaymentStatus = 'Bank Transaction Completed';
          break;
      case 'UNPROCESSED':
      case 'AUTHENTICATED':
      case 'CAPTURE':
      case 'PAYFAIL':
        $this->defaultPaymentStatus = 'authorization_expired';
        break;

      case 'VOID':
        $this->defaultPaymentStatus = 'authorization_voided';
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
      $this->loggerFactory->notice('<pre><code>' . print_r($data, TRUE) . '</code></pre>');
      // Update the status and call event subscriber 
      // on order completion only.
        if ($this->updatePaymentStatus($data)) {
          $this->updateEventSubscriber($data);
        }
      return new JsonResponse(0);
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
   * Update the status in Drupal.
   *
   * @param array $data
   *   The GMO api response data.
   */
  public function updatePaymentStatus($data) {
    $event = new LinkTypePlusEvent($data);
    $order_id = $event->getOrderId();
    $remote_id = $event->getRemoteId();
    $linkTypeState = $event->getTransitionState();
    $this->webhookStatusMapper($linkTypeState);
    $this->loggerFactory->notice('<pre><code>Status: '.$linkTypeState.'</code></pre>');
    $this->loggerFactory->notice('<code>OrderId: '.$order_id.'</code>');
    if($order_id && !empty($order_id)){
      $order = Order::load($order_id);
      $payment_storage = $this->entityTypeManager->getStorage('commerce_payment');
      $paymentGateway = $order->get('payment_gateway')->entity->id();
      $total_price = $order->getTotalprice()->getNumber();
      $currency = $order->getTotalprice()->getCurrencyCode();
      $payment = $payment_storage->loadByProperties([
        'order_id' => $order_id,
      ]);
      if ($payment) {
        $payment = array_shift($payment);
        $payment->setState($this->defaultPaymentStatus);
        $payment->setRemoteId($remote_id);
        $payment->save();
      }
      else {
        $payment = $payment_storage->create([
          'state' => $this->defaultPaymentStatus,
          'payment_gateway' => $paymentGateway,
          'remote_id' => $remote_id,
          'amount' => [
            'number' => $total_price,
            'currency_code' => $currency,
          ],
          'order_id' => $order_id,
        ]);
        $payment->save();
      }
      $order->unlock();
      $order->setData($paymentGateway, $event);
      // It should be dynamic based on the payment status.
      if ($order->getState()->getId() != 'completed') {
        $order->getState()->applyTransitionById('place');
      }
      if($order->save()){
        return TRUE;
      }
    }return TRUE;
  }

 /**
  * Custom access callback on the success and Webhook processor
  */
  public function accessCallback() {
    // Get the current request object.
    $request = $this->requestStack->getCurrentRequest();

    // Check if the request has a referrer.
    $referrer = $request->headers->get('referer');
    $urlParts = explode('.', $referrer);

    $mode = 'test';// Hardcoded need to read from config
    if($mode == 'test' && str_contains($urlParts[0], 'stg')){
      $allowedDomain = 'https://stg.link.mul-pay.jp';
    }else if ($mode == 'live'){
      $allowedDomain = 'https://link.mul-pay.jp';
    }

    // Check if the referrer matches the allowed domain.
    if (strpos($referrer, $allowedDomain) !== false) {
      return AccessResult::allowed();
    }
    return AccessResult::forbidden();
  }
}



