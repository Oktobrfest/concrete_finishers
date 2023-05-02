<?php

namespace Drupal\concrete_finishers\EventSubscriber;

use Drupal\entity_print\Event\PrintEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\entity_print\Event\PreSendPrintEvent;

/**
 * The PostRenderSubscriber class.
 */
class PreSendSubscriber implements EventSubscriberInterface {

    /**
     * @param PreSendPrintEvent $event
     * @return bool|null
     */
    public function preSend(PreSendPrintEvent $event) {
        $uri = 'private://proposal-' . $event->getEntities()->id() . '.pdf';
        echo file_unmanaged_save_data($event->getPrintEngine()->getBlob(), $uri , FILE_EXISTS_REPLACE);exit();
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents() {
        return [PrintEvents::PRE_SEND => 'preSend'];
    }

}
