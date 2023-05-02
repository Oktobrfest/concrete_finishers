<?php

namespace Drupal\concrete_finishers\Plugin\Event;

use Symfony\Component\EventDispatcher\GenericEvent;

class InvoicePaidEvent extends GenericEvent {

  const EVENT_NAME = 'rules_invoice_paid';

}