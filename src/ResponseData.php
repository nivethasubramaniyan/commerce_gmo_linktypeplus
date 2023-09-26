<?php

namespace Drupal\commerce_gmo_linktypeplus;

/**
 * Processing the response data.
 */
class ResponseData {

  /**
   * Shop ID.
   *
   * @var string
   */
  public $shopId;
  /**
   * Shop Pass.
   *
   * @var string
   */
  public $shopPass;
  /**
   * Order ID.
   *
   * @var string
   */
  public $orderId;
  /**
   * LinkType Result / Status.
   *
   * @var string
   */
  public $status;
  /**
   * LinkType Payment Method.
   *
   * @var string
   */
  public $paymentMethod;
  /**
   * LinkType Remote ID varies based on the payment Method.
   *
   * @var array
   */
  public $remoteIdVaries = ['TranID', 
  'PayPayTrackingID', 
  'RakutenChargeID',
  'DocomoSettlementCode',
  'SbTrackingId',
  'AuPayInfoNo',
  'UriageNO',
  'EposTradeId'];
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
  public $payMethod = ['45' => 'PayPay', '0' => 'Credit'];

  /**
   * {@inheritdoc}
   */
  public function __construct($data) {
    if ($data) {
      $this->orderId = $this->getOrderId($data['OrderID']);
      $this->status = $this->checkStatus($data);
      $this->remoteId = $this->processRemoteId($data);
      $this->paymentMethod = $this->checkPayMethod($data);
    }
  }

  /**
   * Check whether the value is set or not.
   */
  public function checkValueExists($val) {
    $val = $val ?? ' ';
    return $val;
  }

  /**
   * Get order id.
   */
  public function getOrderId($val) {
    $order_id = explode('-', $val)[0];
    return $order_id;
  }

  /**
   * Processing remote id since the data varies based on the payment method.
   *
   * THIS NEEDS TO BE EXTENDED.
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
   * Check status if Result is not available.
   */
  public function checkStatus($data) {
    if (array_key_exists('Result', $data)) {
      return $this->checkValueExists($data['Result']);
    }
    return $this->checkValueExists($data['Status']);
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

}
