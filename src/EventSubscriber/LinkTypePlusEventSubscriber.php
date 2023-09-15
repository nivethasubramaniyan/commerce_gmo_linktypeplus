<?php

namespace Drupal\commerce_gmo_linktypeplus\EventSubscriber;

use Drupal\commerce_gmo_linktypeplus\Event\LinkTypePlusEvent;
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
      'PayPay' => 'onlinkTypePayPayEvent'
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function onlinkTypeCreditCardPaymentEvent(LinkTypePlusEvent $event) {
    $paymentMethod = $event->getPaymentMethod();
    $this->loggerFactory->notice("$paymentMethod payment event has been subscribed");
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function onlinkTypePayPayEvent(LinkTypePlusEvent $event) {
    $paymentMethod = $event->getPaymentMethod();
    $this->loggerFactory->notice("$paymentMethod payment event has been subscribed");
    return TRUE;
  }
}
