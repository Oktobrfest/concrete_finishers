<?php

namespace Drupal\concrete_finishers\Plugin\RulesAction;

use Drupal\concrete_finishers\Task\TaskBase;
use Drupal\Core\Entity\EntityInterface;
use Drupal\node\Entity\Node;
use Drupal\rules\Core\RulesActionBase;

/**
 * Provides an action to trigger a custom publishing option.
 *
 * @RulesAction(
 *   id = "service_scheduled_action",
 *   label = @Translation("React to a job being scheduled"),
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
class ServiceScheduledAction extends RulesActionBase
{

    /**
     * {@inheritdoc}
     */
    public function doExecute(EntityInterface $entity)
    {

        $task = (new TaskBase($entity))->completePreviousTask('Contracted');
        return $task;
    }
}