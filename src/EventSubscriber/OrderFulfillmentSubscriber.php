<?php

namespace Drupal\commerce_gmo_linktypeplus\EventSubscriber;

use Drupal\state_machine\Event\WorkflowTransitionEvent;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\commerce_order\Entity\Order;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;


/**
 * Sends an email when the order transitions to Fulfillment.
 */
class OrderFulfillmentSubscriber implements EventSubscriberInterface {

  use StringTranslationTrait;

 /**
   * Logger Factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $loggerFactory;

  /**
   * Constructs a new OrderFulfillmentSubscriber object.
   *
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $loggerFactory
   *   Logger.
   */
  public function __construct(LoggerChannelFactoryInterface $loggerFactory,
  ) {
    $this->loggerFactory = $loggerFactory->get('linktype_order_fulfill_subscriber');;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events = [
      'commerce_order.fulfill.post_transition' => ['completeOrder', -100],
    ];
    return $events;
  }

  /**
   * Sends the email.
   *
   * @param \Drupal\state_machine\Event\WorkflowTransitionEvent $event
   *   The transition event.
   */
  public function completeOrder(WorkflowTransitionEvent $event) {
    $this->loggerFactory->notice('in CompleteOrder event');
    $order = $event->getEntity();
    $orderId = $order->id();
    $order = Order::load($orderId);
    $order->unlock();
    $order->setPlacedTime(\Drupal::time()->getCurrentTime());
    $order->setOrderNumber($orderId);
    $order->set('cart', 0);
    $order->set('state', 'completed');
    $order->save();
  }

}
