<?php

namespace Drupal\commerce_gmo_linktypeplus\Event;

use Symfony\Contracts\EventDispatcher\Event;
use Drupal\commerce_gmo_linktypeplus\PaymentMethodsConstants;
use Drupal\commerce_gmo_linktypeplus\RemoteIdConstants;


/**
 * OrderStatusChange Custom event.
 */
class LinkTypePlusEvent extends Event {

  const EVENT_NAME = 'link_type_plus.event';

  /**
   * Order Id.
   *
   * @var orderId
   */


  protected $orderId;
  /**
   * Transition State.
   *
   * @var transitionState
   */
  protected $transitionState;

  /**
   * LinkType Payment Method.
   *
   * @var string
   */
  public $paymentMethod;

  /**
   * Remote ID variables.
   *
   * @var array
   */

  public $remoteIdVaries = RemoteIdConstants :: REMOTE_IDS ;
  /**
   * Remote ID .
   *
   * @var string
   */
  public $remoteId;
  /**
   * PayType Configuration .
   *
   * @var array
   */
  /**
 * PayType Configuration .
 *
 * @var array
 */
  public $payMethod = [
    PaymentMethodsConstants::CREDIT => 'Credit',
    PaymentMethodsConstants::PAYPAL => 'PayPay',
    PaymentMethodsConstants::RAKUTEN_PAY => 'Rakuten pay',
  ];

  /**
   * Constructor.
   *
   * @param string $orderId
   *   The order id.
   * @param string $status
   *   Status recieved from GMO.
   */
  public function __construct(array $data) {
    $this->orderId = $data['OrderID'];
    $this->transitionState = $data['Status'];
    $this->remoteId = $this->processRemoteId($data);
    $this->paymentMethod = $this->checkPayMethod($data);
  }

  /**
   * Gets the orderId.
   */
  public function getOrderId() {
    return $this->orderId;
  }

  /**
   * Gets the transitionState.
   */
  public function getTransitionState() {
    return $this->transitionState;
  }

  /**
   * Gets the remoteId.
   */
  public function getRemoteId() {
    return $this->remoteId;
  }

  /**
   * Gets the remoteId.
   */
  public function getPaymentMethod() {
    return $this->paymentMethod;
  }


  /**
   * Processing remote id since the data varies based on the payment method.
   *
   */
  public function processRemoteId($val) {
    if (is_array($val)) {
      foreach ($this->remoteIdVaries as $key) {
        if (array_key_exists($key, $val)) {
          return $val[$key];
        }
      }
      return ' ';
    }
  }


  /**
   * Check payType if PayMethod is not available.
   */
  public function checkPayMethod($data) {
    if (array_key_exists('Paymethod', $data)) {
      return $this->checkValueExists($data['Paymethod']);
    }
    return $this->payMethod[$data['PayType']];
  }

  /**
   * Check whether the value is set or not.
   */
  public function checkValueExists($val) {
    $val = $val ?? ' ';
    return $val;
  }
  
}
