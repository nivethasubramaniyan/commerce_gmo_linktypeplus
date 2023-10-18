<?php

namespace Drupal\commerce_gmo_linktypeplus\EventSubscriber;

use Drupal\commerce_gmo_linktypeplus\Event\LinkTypePlusEvent;
use Drupal\commerce_order\Entity\Order;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Orderstatus change event subscriber.
 */
class LinkTypePlusEventSubscriber implements EventSubscriberInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Logger Factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $loggerFactory;

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $loggerFactory
   *   Logger .
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager, LoggerChannelFactoryInterface $loggerFactory) {
    $this->entityTypeManager = $entityTypeManager;
    $this->loggerFactory = $loggerFactory->get('linktype_event_subscriber');
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      'Credit' => 'onlinkTypeCreditCardPaymentEvent',
      'PayPay' => 'onlinkTypePayPayEvent',
      'CVS' => 'onlinkTypePayPayEvent',
      'PayEasy' => 'onlinkTypePayPayEvent',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function onlinkTypeCreditCardPaymentEvent(LinkTypePlusEvent $event) {
    $paymentMethod = $event->getPaymentMethod();
    // Get the payment status and update the payment in Drupal.
    $orderId = $event->getOrderId();
    $status = $event->getTransitionState();
    $drupalStatus = $this->webhookStatusMapper($status);
    $remoteId = $event->getRemoteId();
    $this->loggerFactory->notice("$paymentMethod payment event has been subscribed");
    $this->loggerFactory->notice("$orderId \n  $status \n $remoteId");
    if (!empty($orderId) && !empty($drupalStatus) && !empty($remoteId)) {
      if ($this->updatePaymentStatus($orderId, $drupalStatus, $remoteId)) {
        $this->loggerFactory->notice('Status has been updated');
        return TRUE;
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function onlinkTypePayPayEvent(LinkTypePlusEvent $event) {
    $paymentMethod = $event->getPaymentMethod();
    // Get the payment status and update the payment in Drupal.
    $orderId = $event->getOrderId();
    $status = $event->getTransitionState();
    $drupalStatus = $this->webhookStatusMapper($status);
    $remoteId = $event->getRemoteId();
    $this->loggerFactory->notice("$paymentMethod payment event has been subscribed");
    $this->loggerFactory->notice("$orderId \n  $status \n $remoteId");
    if (!empty($orderId) && !empty($drupalStatus) && !empty($remoteId)) {
      if ($this->updatePaymentStatus($orderId, $drupalStatus, $remoteId)) {
        $this->loggerFactory->notice('Status has been updated');
        return TRUE;
      }
    }
  }

  /**
   * Update the status in Drupal.
   *
   * @param array $data
   *   The GMO api response data.
   */
  public function updatePaymentStatus($order_id, $status, $remote_id) {
    if ($order_id && !empty($order_id)) {
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
        $payment->setState($status);
        $payment->setRemoteId($remote_id);
        $payment->save();
      }
      else {
        $payment = $payment_storage->create([
          'state' => $status,
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
      return TRUE;
    }
  }

  /**
   * Method used to track the linktype status with the drupal commerce_payment.
   *
   * We got these status through webhook.
   *
   * It may differs for credit card, paypay etc.
   * Please add the required status by refering the doc
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
        return 'authorization';
        break;
      case 'REQSUCCESS':
      case 'AUTHPROCESS':
        return 'authorization';
        break;
      case 'PAYSTART':
        return 'new';
        break;
      case 'ERROR':
        return '';
        break;
      case 'PAYSUCCESS':
        return 'completed';
        break;
      case 'SALES':
      case 'TRADING':
        return 'completed';
        break;
      case 'UNPROCESSED':
      case 'AUTHENTICATED':
      case 'CAPTURE':
        return 'new';
        break;
      case 'PAYFAIL':
        return 'authorization_expired';
        break;
      case 'VOID':
        return 'authorization_voided';
        break;
      default:
        return 'new';
    }

  }

}
