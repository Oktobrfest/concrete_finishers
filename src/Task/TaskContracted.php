<?php

namespace Drupal\concrete_finishers\Task;

use Drupal\concrete_finishers\Client\Client;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Url;
use Drupal\node\Entity\Node;

class TaskContracted extends TaskBase
{

    const DURATION = '+1 days';

    public function createTask()
    {
        $dt = new \DateTime('now');
        $term = \Drupal::entityTypeManager()
            ->getStorage('taxonomy_term')
            ->loadByProperties(['name' => 'Contracted']);

        $this->task = Node::create([
            'type' => 'task',
            'title' => "Schedule service date with " . $this->getEntity()->field_contact_name->value,
            'field_client_reference' => [
                $this->getEntity()->id(),
            ],
            'field_phase' => $term,
            'body' => "Reach out to " . $this->getEntity()->field_contact_name->value .
                " and schedule the date to start working "
                . $this->getEntity()->field_email->value . " " . $this->getEntity()->field_phone->value,
            'field_due_date' => $dt->modify(self::DURATION)
                ->format('Y-m-d'),
        ]);
        $this->getTask()->save();
        $this->completePreviousTask('Proposed');

        return $this->getTask();
    }

    public function getOperations()
    {
        $client = (new Client($this->getEntity()->get('field_client_reference')
            ->getValue(0)[0]['target_id']))->getClient();

        $operations = [];
        $operations['set-start'] = [
            'title' => t('Schedule Service'),
            'url' => Url::fromRoute('concrete_finishers.startDateForm',
                ['entity_id' => $client->id()]
            ),
            'weight' => 0,
        ];
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