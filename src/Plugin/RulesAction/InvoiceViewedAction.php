<?php

namespace Drupal\concrete_finishers\Plugin\RulesAction;

use Drupal\concrete_finishers\Client\Client;
use Drupal\concrete_finishers\Task\TaskLead;
use Drupal\concrete_finishers\Task\TaskProposalViewed;
use Drupal\Core\Entity\EntityInterface;
use Drupal\node\Entity\Node;
use Drupal\rules\Core\RulesActionBase;

/**
 * Provides an action to trigger a custom publishing option.
 *
 * @RulesAction(
 *   id = "invoice_viewed_rules_action",
 *   label = @Translation("React to an invoice being viewed"),
 *   category = @Translation("Content"),
 *   context = {
 *    "entity" = @ContextDefinition("entity",
 *       label = @Translation("Entity"),
 *       description = @Translation("Specifies the entity, which should be saved permanently.")
 *     )
 *   }
 * )
 */
class InvoiceViewedAction extends RulesActionBase {

  /**
   * {@inheritdoc}
   */
  public function doExecute(EntityInterface $entity) {
      $client = new Client();
      $p = $client->getProposal($entity->get('field_proposal_reference')->getValue(0)[0]['target_id']);
      $client = (new \Drupal\concrete_finishers\Client\Client(
        $p->get('field_client_reference')->getValue(0)[0]['target_id']
      ))->getClient();
    return (new TaskProposalViewed($client))->createTask();
  }
}