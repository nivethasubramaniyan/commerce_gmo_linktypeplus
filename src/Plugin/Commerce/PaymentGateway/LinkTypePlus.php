<?php

namespace Drupal\commerce_gmo_linktypeplus\Plugin\Commerce\PaymentGateway;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\OffsitePaymentGatewayBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Provides the Link Type Plus payment gateway using Off-site Redirect .
 *
 * @CommercePaymentGateway(
 *   id = "link_type_plus",
 *   label = "Link Type Plus",
 *   display_label = "Link Type Plus",
 *   forms = {
 *     "offsite-payment" = "Drupal\commerce_gmo_linktypeplus\PluginForm\LinkTypePlus\LinkTypePlusOffsiteForm",
 *   },
 *   requires_billing_information = FALSE,
 * )
 */
class LinkTypePlus extends OffsitePaymentGatewayBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'redirect_method' => 'post',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    // A real gateway would always know which redirect method should be used,
    // it's made configurable here for test purposes.
    $form['redirect_method'] = [
      '#type' => 'radios',
      '#title' => $this->t('Redirect method'),
      '#options' => [
        'get' => $this->t('Redirect via GET (302 header)'),
        'post' => $this->t('Redirect via POST (automatic)'),
        'post_manual' => $this->t('Redirect via POST (manual)'),
      ],
      '#default_value' => $this->configuration['redirect_method'],
    ];

    $form['host'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Host'),
      '#default_value' => $this->configuration['host'],
      '#required' => TRUE,
      '#description' => $this->t('Enter linktype plus sandbox host URL'),
    ];

    $form['shop_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Shop ID'),
      '#default_value' => $this->configuration['shop_id'],
      '#required' => TRUE,
    ];

    $form['shop_pass'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Shop password'),
      '#default_value' => $this->configuration['shop_pass'],
      '#required' => TRUE,
    ];

    $form['shop_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Shop Name'),
      '#default_value' => $this->configuration['shop_name'],
      '#required' => TRUE,
    ];

    $form['notify_mailaddress'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Notify Mail address'),
      '#default_value' => $this->configuration['notify_mailaddress'],
      '#description' => $this->t('<b>This is the email address to which the payment completion notification email will be sent to the Merchant when the payment is completed.
      If omitted, the payment completion notification 
      email will not be sent.</b>'),
    ];

    $form['resultskipflag'] = [
      '#type' => 'radios',
      '#title' => $this->t('Should Skip The Result?'),
      '#options' => ["1" => $this->t('Yes'), "0" => $this->t('No')],
      '#default_value' => $this->configuration['resultskipflag'],
      '#required' => TRUE,
      '#description' => $this->t('<b>When set to ON, skip the result screen and 
      transition to the return destination at the time of completion.</b>')
    ];

    $form['confirmkipflag'] = [
      '#type' => 'radios',
      '#title' => $this->t('Should Skip The Confirmation Screen?'),
      '#options' => ["1" => $this->t('Yes'), "0" => $this->t('No')],
      '#default_value' => $this->configuration['confirmkipflag'],
      '#required' => TRUE,
      '#description' => $this->t('<b>When set to ON, the confirmation screen will be skipped and the 
      screen will transition to the next screen.</b>')
    ];

    $form['transdetailflag'] = [
      '#type' => 'radios',
      '#title' => $this->t('Show transaction details?'),
      '#options' => ["1" => $this->t('Yes'), "0" => $this->t('No')],
      '#default_value' => $this->configuration['transdetailflag'],
      '#required' => TRUE,
      '#description' => $this->t('<b>When set to ON, the initial display will be the expanded 
      transaction details on the payment screen..</b>')
    ];

    $form['payment_methods'] = [
      '#type' => 'select',
      '#title' => $this->t('Select the payment methods'),
      '#options' => [
        'cvs' => $this->t('convenience stores'),
        'credit' => $this->t('credit card'),
        'payeasy' => $this->t('Pay-easy'),
        'docomo' => $this->t('d payment'),
        'au' => $this->t('au easy payment'),
        'sb' => $this->t('Softbank lump sum payment'),
        'epospay' => $this->t('Epos easy payment'),
        'dcc' => $this->t('Multicurrency credit card payment (DCC)'),
        'linepay' => $this->t('LINE Pay payment'),
        'famipay' => $this->t('FamiPay payment'),
        'merpay' => $this->t('Merpay payment'),
        'rakutenid' => $this->t('Rakuten pay'),
        'rakutenpayv2' => $this->t('Rakuten Pay V2'),
        'paypay' => $this->t('paypay'),
        // 'virtual account' => $this->t('Bank transfer (virtual account)'),
        'aupay' => $this->t('au PAY (online payment)'),
        'ganb' => $this->t('Bank transfer (virtual account Aozora)'),
        // 'union pay' => $this->t('Net Union Pay'),
      ],
      '#default_value' => $this->configuration['payment_methods'],
      '#multiple' => TRUE,
      '#required' => TRUE,
    ];

    $form['template_no'] = [
      '#type' => 'select',
      '#title' => $this->t('Select the Template Number'),
      '#options' => [
        '1' => $this->t('1'),
        '2' => $this->t('2'),
        '3' => $this->t('3'),
        '4' => $this->t('4'),
        '5' => $this->t('5'),
      ],
      '#default_value' => $this->configuration['template_no'],
      '#description' => $this->t('Please enter the template type that
      will display on payment screen. please refer: <a href="https://docs.mul-pay.jp/linkplus/payment/common">https://docs.mul-pay.jp/linkplus/payment/common</a> for more'),
      '#multiple' => FALSE,
      '#required' => TRUE,
    ];

    $form['template_id'] = [
      '#type' => 'select',
      '#title' => $this->t('Select the Template ID'),
      '#options' => [
        'designA' => $this->t('designA'),
        'designB' => $this->t('designB'),
        'designC' => $this->t('designC'),
        'designD' => $this->t('designD'),
      ],
      '#default_value' => $this->configuration['template_id'],
      '#description' => $this->t('Please enter the template design that
        will display on payment screen. please refer: <a href="https://docs.mul-pay.jp/linkplus/payment/common">https://docs.mul-pay.jp/linkplus/payment/common</a> for more'),
      '#multiple' => FALSE,
      '#required' => TRUE,
    ];

    $form['color_pattern'] = [
      '#type' => 'select',
      '#title' => $this->t('Select the Color Pattern'),
      '#options' => [
        'blue_01' => $this->t('blue_01'),
        'bluegray_01' => $this->t('bluegray_01'),
        'skyblue_01' => $this->t('skyblue_01'),
        'pink_01' => $this->t('pink_01'),
        'yellow_01' => $this->t('yellow_01'),
        'black_01' => $this->t('black_01'),
        'nature_01' => $this->t('nature_01'),
        'greengray_01' => $this->t('greengray_01'),
      ],
      '#default_value' => $this->configuration['color_pattern'],
      '#description' => $this->t('Please enter the color pattern that
        will display on payment screen. please refer: <a href="https://docs.mul-pay.jp/linkplus/payment/common">https://docs.mul-pay.jp/linkplus/payment/common</a> for more'),
      '#multiple' => FALSE,
      '#required' => TRUE,
    ];

    $form['language'] = [
      '#type' => 'select',
      '#title' => $this->t('Select the language'),
      '#options' => [
        'ja' => $this->t('Japanese'),
        'en' => $this->t('English'),
        'zh' => $this->t('Simplified Chinese')
      ],
      '#default_value' => $this->configuration['language'],
      '#description' => $this->t('The language (ISO639 code) to be displayed on the payment screen.'),
      '#multiple' => FALSE,
      '#required' => TRUE,
    ];

    $form['cancel_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Cancel URL'),
      '#default_value' => $this->configuration['cancel_url'],
      '#required' => TRUE,
      '#description' => $this->t('Cancellation URL that will callback 
      when the user cancels the payment'),
    ];

    $form['return_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Return URL'),
      '#default_value' => $this->configuration['return_url'],
      '#required' => TRUE,
      '#description' => $this->t('Link type This is the destination URL when pressing 
        the "Return to site" button'),
    ];

    $form['logo_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Logo URL'),
      '#default_value' => $this->configuration['logo_url'],
      '#required' => TRUE,
      '#description' => $this->t('URL of the logo that will display on payment screen'),
    ];

    $form['customer_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Customer Name'),
      '#default_value' => $this->configuration['customer_name'],
      '#required' => TRUE,
      '#description' => $this->t('<b>It is used as the default value for the name field on the input screen of each payment method.the payment 
      method list where customer information is used.</b>'),
    ];

    $form['customer_mailaddress'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Customer Mail Address'),
      '#default_value' => $this->configuration['customer_mailaddress'],
      '#required' => TRUE,
      '#description' => $this->t('<b>It is used as the default value in the email address field 
      of the input screen of each payment method.</b>'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);
    if (!$form_state->getErrors()) {
      $values = $form_state->getValue($form['#parents']);
      $this->configuration['redirect_method'] = $values['redirect_method'];
      $this->configuration['host'] = $values['host'];
      $this->configuration['mode'] = $values['mode'];
      $this->configuration['shop_id'] = $values['shop_id'];
      $this->configuration['shop_pass'] = $values['shop_pass'];
      $this->configuration['shop_name'] = $values['shop_name'];
      $this->configuration['notify_mailaddress'] = $values['notify_mailaddress'];
      $this->configuration['confirmkipflag'] = $values['confirmkipflag'];
      $this->configuration['transdetailflag'] = $values['transdetailflag'];
      $this->configuration['language'] = $values['language'];
      $this->configuration['customer_name'] = $values['customer_name'];
      $this->configuration['customer_mailaddress'] = $values['customer_mailaddress'];
      $this->configuration['resultskipflag'] = $values['resultskipflag'];
      $this->configuration['payment_methods'] = $values['payment_methods'];
      $this->configuration['template_no'] = $values['template_no'];
      $this->configuration['template_id'] = $values['template_id'];
      $this->configuration['color_pattern'] = $values['color_pattern'];
      $this->configuration['cancel_url'] = $values['cancel_url'];
      $this->configuration['return_url'] = $values['return_url'];
      $this->configuration['logo_url'] = $values['logo_url'];
    }
  }

  /**
   * Processes the "return" request.
   *
   * This method should only be concerned with creating/completing payments,
   * the parent order does not need to be touched. The order state is updated
   * automatically when the order is paid in full, or manually by the
   * merchant (via the admin UI).
   */
  public function onReturn(OrderInterface $order, Request $request) {
    // @todo Add examples of request validation.
    // Note: Since requires_billing_information is FALSE, the order is
    // not guaranteed to have a billing profile. Confirm that
    // $order->getBillingProfile() is not NULL before trying to use it.
  }

  /**
   * Processes the "cancel" request.
   *
   * Allows the payment gateway to clean up any data added to the $order, set
   * a message for the customer.
   */
  public function onCancel(OrderInterface $order, Request $request) {
    $this->onReturn($order, $request);
  }

}
