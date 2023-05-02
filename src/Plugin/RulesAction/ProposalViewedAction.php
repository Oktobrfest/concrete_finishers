<?php

namespace Drupal\concrete_finishers\Plugin\RulesAction;

use Drupal\concrete_finishers\Task\TaskLead;
use Drupal\concrete_finishers\Task\TaskProposalViewed;
use Drupal\Core\Entity\EntityInterface;
use Drupal\node\Entity\Node;
use Drupal\rules\Core\RulesActionBase;

/**
 * Provides an action to trigger a custom publishing option.
 *
 * @RulesAction(
 *   id = "proposal_viewed_rules_action",
 *   label = @Translation("React to a proposal being viewed"),
 *   category = @Translation("Content"),
 *   context = {
 *    "entity" = @ContextDefinition("entity",
 *       label = @Translation("Entity"),
 *       description = @Translation("Specifies the entity, which should be
 *     saved permanently.")
 *     )
 *   }
 * )
 */
class ProposalViewedAction extends RulesActionBase
{

    /**
     * {@inheritdoc}
     */
    public function doExecute(EntityInterface $entity)
    {
        $client = (new \Drupal\concrete_finishers\Client\Client(
            $entity->get('field_client_reference')->getValue(0)[0]['target_id']
        ))->getClient();
        return (new TaskProposalViewed($client))->createTask();
    }
}