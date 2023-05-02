<?php

namespace Drupal\concrete_finishers\Plugin\Event;

use Symfony\Component\EventDispatcher\GenericEvent;

class ContractSignedEvent extends GenericEvent {

  const EVENT_NAME = 'rules_contract_signed';

}