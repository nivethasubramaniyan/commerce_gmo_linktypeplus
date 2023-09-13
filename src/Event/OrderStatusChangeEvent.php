<?php

namespace Drupal\commerce_gmo_linktypeplus\Event;

use Symfony\Contracts\EventDispatcher\Event;

class OrderStatusChangeEvent extends Event {

  const EVENT_NAME = 'link_type_plus.order_status_change';
  protected $orderId;
  protected $transitionState;

  public function __construct($orderId, $status) {
    $this->orderId = $orderId;
    $this->transitionState = $status;
  }

  public function getOrderId() {
    return $this->orderId;
  }

  public function getTransitionState() {
    return $this->transitionState;
  }
}
