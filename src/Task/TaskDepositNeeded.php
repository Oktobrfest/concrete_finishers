<?php

namespace Drupal\concrete_finishers\Task;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\concrete_finishers\Client\Client;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Url;
use Drupal\node\Entity\Node;

class TaskDepositNeeded extends TaskBase
{

    public function createTask()
    {
        $dt = new \DateTime($this->getEntity()->field_proposed_service_date->value);

        try {
            $term = \Drupal::entityTypeManager()
                ->getStorage('taxonomy_term')
                ->loadByProperties(['name' => 'Deposit Needed']);
        } catch (InvalidPluginDefinitionException $e) {
            \Drupal::logger('TaskDepositNeeded')->error($e->getMessage());
            drupal_set_message($e->getMessage(), 'error');
        }
        $this->task = Node::create([
            'type' => 'task',
            'title' => "Get the deposit for " . $this->getEntity()->title->value,
            'field_client_reference' => [
                $this->getEntity()->id(),
            ],
            'field_phase' => $term,
            'body' => "Reach out to " . $this->getEntity()->field_contact_name->value .
                " and make sure that they remit payment for a deposit "
                . $this->getEntity()->field_email->value . " " . $this->getEntity()->field_phone->value,
            'field_due_date' => $dt->modify('-1 days')->format('Y-m-d'),
        ]);
        try {
            $this->getTask()->save();
        } catch (EntityStorageException $e) {
            \Drupal::logger('TaskDepositNeeded')->error($e->getMessage());
            drupal_set_message($e->getMessage(), 'error');
        }


        return $this->getTask();
    }

    public function getOperations()
    {
        try {
            $client = (new Client($this->getEntity()
                ->get('field_client_reference')
                ->getValue(0)[0]['target_id']))->getClient();
        } catch (InvalidPluginDefinitionException $e) {
        \Drupal::logger('TaskDepositNeeded')->error($e->getMessage());
        drupal_set_message($e->getMessage(), 'error');
    }
        

        $operations = [];
        $operations['view-proposal'] = [
            'title' => t('View Proposal'),
            'url' => Url::fromRoute('entity.node.canonical',
                [
                    'node' => $client->get('field_proposal_reference')
                        ->getValue(0)[0]['target_id'],
                ]),
            'weight' => 0,
        ];

        return $operations;
    }
}