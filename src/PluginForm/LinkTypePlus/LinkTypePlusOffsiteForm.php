<?php

namespace Drupal\commerce_gmo_linktypeplus\PluginForm\LinkTypePlus;

use Drupal\commerce_payment\Exception\PaymentGatewayException;
use Drupal\commerce_payment\PluginForm\PaymentOffsiteForm as BasePaymentOffsiteForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\RequestOptions;
use \Drupal\user\Entity\User;

/**
 * Manages the offsite redirection to the vendor website.
 */
class LinkTypePlusOffsiteForm extends BasePaymentOffsiteForm {

  /**
   * Url of the sandbox host.
   */
  const HOST_SANDBOX = '';

  /**
   * Gmo credentials needed to communicate with the api.
   *
   * @var string[]
   */
  protected array $credentials = [];


  /**
   * The host used for transactions.
   *
   * @var string
   */
  protected string $host;

  /**
   * LinkTypePlusOffsiteForm constructor.
   */
  public function __construct() {
    $this->host = $this->host ?? static::HOST_SANDBOX;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    /** @var \Drupal\commerce_payment\Entity\PaymentInterface $payment */
    $payment = $this->entity;
    /** @var \Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\OffsitePaymentGatewayInterface $payment_gateway_plugin */
    $payment_gateway_plugin = $payment->getPaymentGateway()->getPlugin();
    $configuration = $payment_gateway_plugin->getConfiguration();
    $redirect_method = $configuration['redirect_method'];
    $host = $configuration['host'];
    $shop_id = $configuration['shop_id'];
    $shop_pass = $configuration['shop_pass'];
    $shop_name = $configuration['shop_name'];
    $notify_mailaddress = $configuration['notify_mailaddress'];
    $confirmkipflag = $configuration['confirmkipflag'];
    $thanksmailsendflag = $configuration['thanksmailsendflag'];
    $transdetailflag = $configuration['transdetailflag'];
    $language = $configuration['language'];
    $resultskipflag = $configuration['resultskipflag'];
    $payment_methods = $configuration['payment_methods'];
    $template_no = $configuration['template_no'];
    $template_id = $configuration['template_id'];
    $color_pattern = $configuration['color_pattern'];
    $cancel_url = $configuration['cancel_url'];
    $return_url = $configuration['return_url'];
    $logo_url = $configuration['logo_url'];
    //cvs params
    $contact_information = $configuration['contact_information'];
    $contact_number = $configuration['contact_number'];
    $contact_reception_hours = $configuration['contact_reception_hours'];

    $this->host = $host;
    $this->credentials = [
      'ShopID' => $shop_id,
      'ShopPass' => $shop_pass,
    ];
    if (in_array($redirect_method, ['post', 'post_manual'])) {
      $redirect_method = 'post';
      $order = $payment->getOrder();
    }
    else {
      // Gateways that use the GET redirect method usually perform an API call
      // that prepares the remote payment and provides the actual url to
      // redirect to. Any params received from that API call that need to be
      // persisted until later payment creation can be saved in $order->data.
      // Example: $order->setData('my_gateway', ['test' => '123']), followed
      // by an $order->save().
      $order = $payment->getOrder();
      // Simulate an API call failing and throwing an exception,
      // for test purposes.
      // See PaymentCheckoutTest::testFailedCheckoutWithOffsiteRedirectGet().
      if ($order->getBillingProfile()->get('address')->family_name == 'FAIL') {
        throw new PaymentGatewayException('Could not get the redirect URL.');
      }
    }
    try {
      $configPayload = [
        'resultskipflag' => $resultskipflag,
        'pay_methods' => array_values($payment_methods),
        'template_no' => $template_no,
        'template_id' => $template_id,
        'color_pattern' => $color_pattern,
        'cancel_url' => $cancel_url,
        'return_url' => $return_url,
        'logo_url' => $logo_url,
        'shop_name' => $shop_name,
        'notify_mailaddress' => $notify_mailaddress,
        'confirmkipflag' => $confirmkipflag,
        'thanksmailsendflag' => $thanksmailsendflag,
        'transdetailflag' => $transdetailflag,
        'language' => $language,
        'contact_information' => $contact_information,
        'contact_number' => $contact_number,
        'contact_reception_hours' => $contact_reception_hours,
      ];

      $redirectUrl = $this->getRedirectUrl($order, $configPayload);
      // Wait for 3 seconds OR masking the redirect loop?!
      sleep(3);
      $form = $this->buildRedirectForm(
        $form,
        $form_state,
        $redirectUrl['LinkUrl'],
        [],
        self::REDIRECT_POST
      );
      return $form;
    }
    catch (\Exception $ex) {
      $error = 'Exception: ' . $ex->getMessage();
      \Drupal::messenger()->addError($error);
    }
  }

  /**
   * Get the redirect URL after making the API call to linktypeplus.
   *
   * @param object $order
   *   The order details.
   * @param array $configPayload
   *   Additional payloads that configured in the linktypeplus form.
   *
   * @return array
   *   The response array that will contain the redirectUrl.
   *
   * @throws \ClientException
   */
  public function getRedirectUrl($order, array $configPayload) {
    // $orderId = $order->id() . $order->getVersion();
    $amount = round((string) $order->getBalance()->getNumber());
    $callBackUrlObj = Url::fromUri('route:commerce_gmo_linktypeplus.complete_response');
    $callBackUrlObj->setAbsolute();
    $callBackUrl = $callBackUrlObj->toString();

    $payload['configid'] = $order->id();
    $payload['transaction'] = [
      'OrderID' => $order->id(),
      'Amount'  => $amount,
      'Overview' => 'SampleOverview',
      'CompleteUrl' => $callBackUrl,
      'PayMethods' => $configPayload['pay_methods'],
      'ResultSkipFlag' => $configPayload['resultskipflag'],
      'CancelUrl' => $configPayload['cancel_url'],
      'RetUrl' => $configPayload['return_url'],
      'ConfirmSkipFlag' => $configPayload['confirmkipflag'],
      'TranDetailShowFlag' => $configPayload['transdetailflag'],
      'NotifyMailaddress' => $configPayload['notify_mailaddress'],
    ];

    $payload['displaysetting'] = [
      "TemplateID" => $configPayload['template_id'],
      "ColorPattern" => $configPayload['color_pattern'],
      "LogoUrl" => $configPayload['logo_url'],
      "Lang" => $configPayload['language'],
      "ShopName" => $configPayload['shop_name'],
    ];

    //Get current user email
    $uid = \Drupal::currentUser()->id();
    $user = User::load($uid);
    $customerName = $user->get('name')->value;
    $customerEmail = $user->get('mail')->value;

    // if(isset($customerName) && isset($customerEmail)){
    //   $payload['customer'] = [
    //     "CustomerName" => $customerName,
    //     "MailAddress" => $customerEmail
    //   ];
    // }

    $this->credentials = [...$this->credentials, 
      'TemplateNo' => $configPayload['template_no'],
      'ThanksMailSendFlag' => $configPayload['thanksmailsendflag'],
      "CustomerName" => $customerName,
      "SendMailAddress" => $customerEmail
    ];

    $payload['geturlparam'] = $this->credentials;

    if(in_array('cvs',$configPayload['pay_methods'])){
      $payload['cvs'] = [
        "ReceiptsDisp11" => $configPayload['contact_information'] ?? '',
        "ReceiptsDisp12" => $configPayload['contact_number'] ?? '',
        "ReceiptsDisp13" => $configPayload['contact_reception_hours'] ?? ''
      ];
    }

    return $this->doCall('payment/GetLinkplusUrlPayment.json', $payload);
  }

  /**
   * Does the api call.
   *
   * @param string $path
   *   The path without leading slash.
   * @param array $payload
   *   The payload.
   *
   * @return array
   *   The response in array form.
   *
   * @throws \Exception
   */
  protected function doCall(string $path, array $payload) {
    if (empty($this->credentials)) {
      throw new \Exception('Client not configured');
    }

    $options = [
      RequestOptions::JSON => $payload,
      RequestOptions::HEADERS => [
        'Content-Type' => 'application/json',
        'charset' => 'UTF-8',
      ],
    ];

    try {
      $result = \Drupal::httpClient()->post($this->host . $path, $options);
      \Drupal::logger('gmojson')->notice('<pre>Success Response :<code>' . print_r($result, TRUE) . '</code></pre>');
      return json_decode($result->getBody()->getContents(), TRUE);
    }
    catch (ClientException $e) {
      $content = json_decode($e->getResponse()->getBody()->getContents());
      \Drupal::logger('gmojson')->notice('<pre>Failure Response: <code>' . print_r($content, TRUE) . '</code></pre>');
    }

  }

}
