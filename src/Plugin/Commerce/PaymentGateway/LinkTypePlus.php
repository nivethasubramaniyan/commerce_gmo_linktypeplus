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
    $form['resultskipflag'] = [
      '#type' => 'radios',
      '#title' => $this->t('Should Skip The Result?'),
      '#options' => ["1" => $this->t('Yes'), "0" => $this->t('No')],
      '#default_value' => $this->configuration['resultskipflag'],
      '#required' => TRUE,
    ];

    $form['payment_methods'] = [
      '#type' => 'select',
      '#title' => $this->t('Select the payment methods'),
      '#options' => [
        'paypay' => $this->t('paypay'),
        'cvs' => $this->t('cvs'),
        'credit' => $this->t('credit'),
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
      ],
      '#default_value' => $this->configuration['template_no'],
      '#multiple' => FALSE,
      '#required' => TRUE,
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
