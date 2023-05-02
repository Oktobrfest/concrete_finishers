<?php

namespace Drupal\concrete_finishers\Plugin\RulesAction;

use Drupal\concrete_finishers\Task\TaskBase;
use Drupal\concrete_finishers\Task\TaskColorNeeded;
use Drupal\concrete_finishers\Task\TaskContracted;
use Drupal\concrete_finishers\Task\TaskDepositNeeded;
use Drupal\concrete_finishers\Task\TaskFinished;
use Drupal\concrete_finishers\Task\TaskProposed;
use Drupal\concrete_finishers\Task\TaskReview;
use Drupal\concrete_finishers\Task\TaskSuppliesNeeded;
use Drupal\Core\Entity\EntityInterface;
use Drupal\node\Entity\Node;
use Drupal\rules\Core\RulesActionBase;

/**
 * Provides an action to trigger a custom publishing option.
 *
 * @RulesAction(
 *   id = "service_finished_action",
 *   label = @Translation("React to a job being finished"),
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
class ServiceFinishedAction extends RulesActionBase
{

    /**
     * {@inheritdoc}
     */
    public function doExecute(EntityInterface $entity)
    {
        $task = new TaskReview($entity);
        $task->createTask();
        $task->completePreviousTask('Finished');
        $task->completePreviousTask('Supplies Needed');
        $task->completePreviousTask('Color Needed');
        $task->completePreviousTask('Deposit Needed');

    }
}