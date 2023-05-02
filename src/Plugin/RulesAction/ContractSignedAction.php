<?php

namespace Drupal\concrete_finishers\Plugin\RulesAction;

use Drupal\concrete_finishers\Task\TaskContracted;
use Drupal\concrete_finishers\Task\TaskProposed;
use Drupal\Core\Entity\EntityInterface;
use Drupal\node\Entity\Node;
use Drupal\rules\Core\RulesActionBase;
use Drupal\Core\Language as Language;
use Drupal\concrete_finishers\Task\TaskColorNeeded;
use Drupal\concrete_finishers\Task\TaskDepositNeeded;
use Drupal\concrete_finishers\Task\TaskFinished;
use Drupal\concrete_finishers\Task\TaskSuppliesNeeded;

/**
 * Provides an action to trigger a custom publishing option.
 *
 * @RulesAction(
 *   id = "contract_signed_action",
 *   label = @Translation("React to a contract being signed on Docusign"),
 *   category = @Translation("Content"),
 *   context = {
 *    "entity" = @ContextDefinition("entity",
 *       label = @Translation("Entity"),
 *       description = @Translation("Specifies the entity, which should be
 *   saved permanently.")
 *     )
 *   }
 * )
 */
class ContractSignedAction extends RulesActionBase
{

    /**
     * {@inheritdoc}
     */
    public function doExecute(EntityInterface $entity)
    {
        $module = 'concrete_finishers';
        $key = 'task_client_contract_signed';
        $from = 'sales@concrete-finishers.com';//\Drupal::config('system.site')->get('mail');
        $to = 'sales@concrete-finishers.com';

        $params = [
            'supplies' => $entity->field_supplies_needed->value,
            'sqft' => $entity->field_square_footage->value,
            'id' => $entity->id(),
            'name' => $entity->field_contact_name->value,
        ];

        $result = \Drupal::service('plugin.manager.mail')->mail($module, $key, $to,
            Language\LanguageInterface::LANGCODE_SYSTEM,
            $params, $from, TRUE);

        $task = (new TaskSuppliesNeeded($entity))->createTask();
        $task = (new TaskColorNeeded($entity))->createTask();
        $task = (new TaskDepositNeeded($entity))->createTask();
        $task = (new TaskFinished($entity))->createTask();

        return (new TaskContracted($entity))->createTask();
    }
}