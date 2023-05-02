<?php

namespace Drupal\concrete_finishers\Plugin\RulesAction;

use Drupal\concrete_finishers\Task\TaskContracted;
use Drupal\concrete_finishers\Task\TaskPaid;
use Drupal\concrete_finishers\Task\TaskProposed;
use Drupal\Core\Entity\EntityInterface;
use Drupal\node\Entity\Node;
use Drupal\rules\Core\RulesActionBase;

/**
 * Provides an action to trigger a custom publishing option.
 *
 * @RulesAction(
 *   id = "invoice_paid_action",
 *   label = @Translation("React to invoice being paid"),
 *   category = @Translation("Content"),
 *   context = {
 *    "entity" = @ContextDefinition("entity",
 *       label = @Translation("Entity"),
 *       description = @Translation("Specifies the entity, which should be saved permanently.")
 *     )
 *   }
 * )
 */
class InvoicePaidAction extends RulesActionBase {

  /**
   * {@inheritdoc}
   */
  public function doExecute(EntityInterface $entity) {
    return (new TaskPaid($entity))->createTask();
  }
}