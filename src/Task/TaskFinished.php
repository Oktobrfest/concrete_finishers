<?php

namespace Drupal\concrete_finishers\Task;

use Drupal\concrete_finishers\Client\Client;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Url;
use Drupal\node\Entity\Node;

class TaskFinished extends TaskBase
{

    const DURATION = '+2 days';

    public function createTask()
    {
        $dt = new \DateTime($this->getEntity()->field_proposed_service_date->value);
        $term = \Drupal::entityTypeManager()
            ->getStorage('taxonomy_term')
            ->loadByProperties(['name' => 'Finished']);

        $this->task = Node::create([
            'type' => 'task',
            'title' => "Is the service for " . $this->getEntity()->field_contact_name->value
                . " completed?",
            'field_client_reference' => [
                $this->getEntity()->id(),
            ],
            'field_phase' => $term,
            'body' => "Monitor the  "
                . $this->getEntity()->field_email->value . " " . $this->getEntity()->field_phone->value,
            'field_due_date' => $dt->modify(self::DURATION)
                ->format('Y-m-d'),
        ]);
        $this->getTask()->save();

        return $this->getTask();
    }

    public function getOperations()
    {
        $client = (new Client($this->getEntity()->get('field_client_reference')
            ->getValue(0)[0]['target_id']))->getClient();

        $operations = [];
        $operations['set-complete'] = [
            'title' => t('Service Completed'),
            'url' => Url::fromRoute('concrete_finishers.completedDateForm',
                ['entity_id' => $client->id()]
            ),
            'weight' => 0,
        ];
        $operations['email-proposal'] = [
            'title' => t('Send Reminder Email'),
            'url' => \Drupal\Core\Url::fromRoute('concrete_finishers.sendProposal',
                ['entity_id' => $this->getEntity()->id()]),
            'weight' => 0,
        ];
        $operations['view-proposal'] = [
            'title' => t('View Proposal'),
            'url' => Url::fromRoute('entity.node.canonical',
                ['node' => $this->getEntity()->id()]),
            'weight' => 0,
        ];

        return $operations;
    }
}