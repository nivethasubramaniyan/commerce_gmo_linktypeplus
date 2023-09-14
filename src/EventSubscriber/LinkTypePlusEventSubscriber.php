<?php

namespace Drupal\commerce_gmo_linktypeplus\EventSubscriber;

use Drupal\commerce_gmo_linktypeplus\Event\LinkTypePlusEvent;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;


/**
 * Orderstatus change event subscriber.
 */
class LinkTypePlusEventSubscriber implements EventSubscriberInterface
{

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
  public function __construct(EntityTypeManagerInterface $entityTypeManager, LoggerChannelFactoryInterface $loggerFactory){
    $this->entityTypeManager = $entityTypeManager;
    $this->loggerFactory = $loggerFactory->get('linktype_event_subscriber');
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(){
    return [
      LinkTypePlusEvent::EVENT_NAME => 'onlinkTypeEvent',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function onlinkTypeEvent(LinkTypePlusEvent $event){
    $paymentMethod = $event->getPaymentMethod();

    switch ($paymentMethod) {
      case 'Credit':
        $this->loggerFactory->notice("$paymentMethod payment event has been subscribed");
        break;

      case 'PayPay':
        $this->loggerFactory->notice("$paymentMethod payment event has been subscribed");
        break;

      case 'Rakuten pay':
        $this->loggerFactory->notice("$paymentMethod payment event has been subscribed");
        break;

      default:
        break;
    }
    return TRUE;
  }
}
