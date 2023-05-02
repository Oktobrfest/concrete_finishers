<?php

namespace Drupal\concrete_finishers\Plugin\Event;

use Symfony\Component\EventDispatcher\GenericEvent;

class InvoiceSentEvent extends GenericEvent {

  const EVENT_NAME = 'rules_invoice_sent';

}