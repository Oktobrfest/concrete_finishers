<?php

namespace Drupal\concrete_finishers\Plugin\RulesAction;

use Drupal\concrete_finishers\Task\TaskLead;
use Drupal\Core\Entity\EntityInterface;
use Drupal\node\Entity\Node;
use Drupal\rules\Core\RulesActionBase;

/**
 * Provides an action to trigger a custom publishing option.
 *
 * @RulesAction(
 *   id = "new_client_rules_action",
 *   label = @Translation("React to a new client being created"),
 *   category = @Translation("Content"),
 *   context = {
 *    "entity" = @ContextDefinition("entity",
 *       label = @Translation("Entity"),
 *       description = @Translation("Specifies the entity, which should be saved permanently.")
 *     )
 *   }
 * )
 */
class NewClientAction extends RulesActionBase {

  /**
   * {@inheritdoc}
   */
  public function doExecute(EntityInterface $entity) {
    return (new TaskLead($entity))->createTask();
  }
}