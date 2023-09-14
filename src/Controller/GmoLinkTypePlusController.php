<?php

namespace Drupal\commerce_gmo_linktypeplus\Controller;

use Drupal\commerce_gmo_linktypeplus\ResponseData;
use Drupal\commerce_order\Entity\Order;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Drupal\commerce_gmo_linktypeplus\Event\LinkTypePlusEvent;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;


/**
 * GMO LinkType Plus Controller process the response of the LinkType integrate with our commerce_payment.
 */
class GmoLinkTypePlusController extends ControllerBase implements ContainerInjectionInterface {

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
   * GmoLinkTypePlusController constructor.
   *
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $loggerFactory
   *   Logger .
   */
  public function __construct(LoggerChannelFactoryInterface $loggerFactory, EventDispatcherInterface $eventDispatcher) {
    $this->loggerFactory = $loggerFactory->get('commerce_gmo_linktypeplus');
    $this->eventDispatcher = $eventDispatcher;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('logger.factory'),
      $container->get('event_dispatcher')
    );
  }

  /**
   * Processes the post data from LinkTypePlus and integrating with commerce_payment.
   */
  public function responseProcessor(Request $request) {
    try {
      // Check referer and process the request.
      $hashedData = $request->request->get('result');
      if (!isset($hashedData)) {
        throw new \Exception("Error in processing");
      }
      $resultData = $this->preProcessingResult($hashedData);
      $data = array_merge(array_shift($resultData), array_pop($resultData));
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
      $payment_storage = \Drupal::entityTypeManager()->getStorage('commerce_payment');
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

      if ($success_page) {
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
      case 'REQSUCCESS':
      case 'AUTHPROCESS':
        $this->defaultPaymentStatus = 'authorization';
        break;

      case 'SALES':
      case 'PAYSUCCESS':
        $this->defaultPaymentStatus = 'Completed';
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
      $hashedData = $request->request->get('result');
      $resultData = $this->preProcessingResult($hashedData);
      $hashedDataMod = array_shift($resultData);
      $responseObj = new ResponseData($hashedDataMod);

      $data = $request->request->all();

      // Simulate the data for now
      $sampleData = Array(
          'ShopID' => "tshop00061625",
          'ShopPass' => "g2d7w1dt",
          'AccessID' => "8d743151e86a61d62b3f7433ffcc3b57",
          'AccessPass' => "7faf5fda072798a9ee16e4ca4d46af90",
          'OrderID' => "70-tt7",
          'Status' => "AUTH",
          'JobCd' => "AUTH",
          'Amount' => "190",
          'Tax' => "0",
          'RakutenChargeID' => "ch_5BUXSNPZWSS",
          'TranDate' => "20230914140044",
          'ErrCode' => "",
          'ErrInfo' => "",
          'PayType' => 50,
          'RakutenSubscriptionType' => "",
          'RakutenSubscriptionID' => "",
          'RakutenSettlementSubscriptionID' => "",
          'RakutenSubscriptionCurrentStatus' => "",
          'RakutenSubscriptionStartDate' => "",
      );
      
      $this->loggerFactory->notice('<pre><code>' . print_r($hashedDataMod, TRUE) . '</code></pre>');
      //Update the order status in Drupal
      if($this->updateEventSubscriber($sampleData)){
         $this->updateLinkTypePaymentStatus(
            $responseObj->orderId,
            $responseObj->paymentMethod,
            $responseObj->status,
            $responseObj->remoteId,
            $hashedDataMod,
            TRUE
        );
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
   * @param string $order_id
   *   The order id.
   * @param string $status
   *   Status recieved from GMO.
   */
  public function updateEventSubscriber($data) {
    // @todo Implement the logic to passthrough an event subscriber here
    // Instead of mapping the whole status
    // Dispatch the custom event.
    $event = new LinkTypePlusEvent($data);
    return $this->eventDispatcher->dispatch(LinkTypePlusEvent::EVENT_NAME, $event);
  }

}
