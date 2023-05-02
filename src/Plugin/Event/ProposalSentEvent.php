<?php

namespace Drupal\concrete_finishers\Plugin\Event;

use Symfony\Component\EventDispatcher\GenericEvent;

class ProposalSentEvent extends GenericEvent {

  const EVENT_NAME = 'rules_proposal_sent';

}