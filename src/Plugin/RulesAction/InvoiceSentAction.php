<?php

namespace Drupal\concrete_finishers\Plugin\RulesAction;

use Drupal\concrete_finishers\Task\TaskInvoiced;
use Drupal\concrete_finishers\Task\TaskProposed;
use Drupal\Core\Entity\EntityInterface;
use Drupal\node\Entity\Node;
use Drupal\rules\Core\RulesActionBase;

/**
 * Provides an action to trigger a custom publishing option.
 *
 * @RulesAction(
 *   id = "invoice_sent_action",
 *   label = @Translation("React to an invoice being sent to a client for the
 *     first time"), category = @Translation("Content"), context = {
 *    "entity" = @ContextDefinition("entity",
 *       label = @Translation("Entity"),
 *       description = @Translation("Specifies the entity, which should be
 *     saved permanently.")
 *     )
 *   }
 * )
 */
class InvoiceSentAction extends RulesActionBase
{

    /**
     * {@inheritdoc}
     */
    public function doExecute(EntityInterface $entity)
    {
        return (new TaskInvoiced($entity))->createTask();
    }
}