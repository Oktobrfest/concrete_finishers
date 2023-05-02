<?php

namespace Drupal\concrete_finishers\Task;

use Drupal\concrete_finishers\Client\Client;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Url;
use Drupal\node\Entity\Node;

class TaskSuppliesNeeded extends TaskBase
{

    /**
     * @return \Drupal\Core\Entity\EntityInterface
     */
    public function createTask()
    {
        \Drupal::logger('tasks')->debug('Task supplies needed');
        $dt = new \DateTime($this->getEntity()->field_proposed_service_date->value);

        $term = \Drupal::entityTypeManager()
            ->getStorage('taxonomy_term')
            ->loadByProperties(['name' => 'Supplies Needed']);
        $this->task = Node::create([
            'type' => 'task',
            'title' => "Get the supplies for " . $this->getEntity()->title->value,
            'field_client_reference' => [
                $this->getEntity()->id(),
            ],
            'field_phase' => $term,
            'body' => "Get the supplies for the job; Supplies: "
                . $this->getEntity()->field_supplies_needed->value . " "
                . "SQFT: " . $this->getEntity()->field_square_footage->value,
            'field_due_date' => $dt->modify('-5 days')->format('Y-m-d'),
        ]);
        $this->getTask()->save();

        return $this->getTask();
    }

    public function getOperations()
    {
        $client = (new Client($this->getEntity()->get('field_client_reference')
            ->getValue(0)[0]['target_id']))->getClient();

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