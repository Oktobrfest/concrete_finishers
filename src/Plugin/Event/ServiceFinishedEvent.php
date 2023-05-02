<?php

namespace Drupal\concrete_finishers\Plugin\Event;

use Symfony\Component\EventDispatcher\GenericEvent;

class ServiceFinishedEvent extends GenericEvent {

  const EVENT_NAME = 'rules_service_finished';

}