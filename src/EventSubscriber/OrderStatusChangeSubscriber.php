<?php

namespace Drupal\commerce_gmo_linktypeplus\EventSubscriber;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\commerce_gmo_linktypeplus\Event\OrderStatusChangeEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\commerce_order\Entity\OrderInterface;


class OrderStatusChangeSubscriber implements EventSubscriberInterface {
  
  protected $entityTypeManager;
  
  public function __construct(EntityTypeManagerInterface $entityTypeManager) {
    $this->entityTypeManager = $entityTypeManager;
  }

  public static function getSubscribedEvents() {
    return [
      OrderStatusChangeEvent::EVENT_NAME => 'onOrderStatusChange',
    ];
  }

  public function onOrderStatusChange(OrderStatusChangeEvent $event) {
    $orderId = $event->getOrderId();
    $transitionState = $event->getTransitionState();

    \Drupal::logger('linktype_event_subscriber')->notice("Status recieved from webhook: $transitionState");

    if(!empty($orderId)){
        // TODO: Don't need to update the status here, we
        // just expose the Event
        // Load the order entity.
        // $order = $this->entityTypeManager->getStorage('commerce_order')->load($orderId);
        // if ($order instanceof OrderInterface) {
        //     // Update the order status.
        //     $order->getState()->applyTransitionById('place');
        //     $order->save();
        // }
        \Drupal::logger('linktype_event_subscriber')->notice("$transitionState event has been subscribed");
    }
    return TRUE;
  }
}
