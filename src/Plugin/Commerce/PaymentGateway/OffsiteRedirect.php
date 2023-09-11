<?php

namespace Drupal\commerce_gmo_linktypeplus\Plugin\Commerce\PaymentGateway;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\OffsitePaymentGatewayBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\HttpFoundation\Request;
// UPDATE THE ANNOTATION ID AND LABEL AND ADD APPROPRIATE PARAMS
// HAVE CLASS NAME LINKTYPE SINCE WE ALREADY HAVE offsiteRedirect
/**
 * Provides the Off-site Redirect payment gateway.
 *
 * @CommercePaymentGateway(
 *   id = "example_offsite_redirect",
 *   label = "Off-site redirect",
 *   display_label = "Example",
 *   forms = {
 *     "offsite-payment" = "Drupal\commerce_gmo_linktypeplus\PluginForm\OffsiteRedirect\PaymentOffsiteForm",
 *   },
 *   payment_method_types = {"credit_card"},
 *   credit_card_types = {
 *     "amex", "dinersclub", "discover", "jcb", "maestro", "mastercard", "visa",
 *   },
 *   requires_billing_information = FALSE,
 * )
 */
class OffsiteRedirect extends OffsitePaymentGatewayBase {

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
    $form['resultskipflag'] = [
      '#type' => 'radios',
      '#title' => $this->t('Should Skip The Result?'),
      '#options' => array("1" => $this->t('Yes'), "0" => $this->t('No')),
      '#default_value' => $this->configuration['resultskipflag'],
      '#required' => TRUE,
    ];

    $form['payment_methods'] = [
      '#type' => 'select',
      '#title' => $this->t('Select the payment methods'),
      '#options' =>array(
        'paypay' => $this->t('paypay'),
        'cvs' => $this->t('cvs'),
        'credit' => $this->t('credit')
      ),
      '#default_value' =>$this->configuration['payment_methods'],
      '#multiple' => TRUE,
      '#required' => TRUE
    ];

    $form['template_no'] = [
      '#type' => 'select',
      '#title' => $this->t('Select the Template Number'),
      '#options' =>array(
        '1' => $this->t('1'),
        '2' => $this->t('2'),
        '3' => $this->t('3')
      ),
      '#default_value' =>$this->configuration['template_no'],
      '#multiple' => FALSE,
      '#required' => TRUE
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
      $this->configuration['shop_id'] = $values['shop_id'];
      $this->configuration['shop_pass'] = $values['shop_pass'];
      $this->configuration['resultskipflag'] = $values['resultskipflag'];
      $this->configuration['payment_methods'] = $values['payment_methods'];
      $this->configuration['template_no'] = $values['template_no'];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function onReturn(OrderInterface $order, Request $request) {
    // @todo Add examples of request validation.
    // Note: Since requires_billing_information is FALSE, the order is
    // not guaranteed to have a billing profile. Confirm that
    // $order->getBillingProfile() is not NULL before trying to use it.
    
    // WE CAN REMOVE THIS , SINCE V HANDLE IT IN SEPARATE CONTROLLER
    $payment_storage = $this->entityTypeManager->getStorage('commerce_payment');
    $payment = $payment_storage->create([
      'state' => 'completed',
      'amount' => $order->getBalance(),
      'payment_gateway' => $this->parentEntity->id(),
      'order_id' => $order->id(),
      'remote_id' => $request->query->get('txn_id'),
      'remote_state' => $request->query->get('payment_status'),
    ]);
    $payment->save();
  }


  public function onCancel(OrderInterface $order, Request $request) {
    $this->onReturn($order, $request);
  }


}
