<?php

namespace Drupal\concrete_finishers\Task;

use Drupal\concrete_finishers\Client\Client;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Url;
use Drupal\node\Entity\Node;

class TaskColorNeeded extends TaskBase
{

    public function createTask()
    {
        $dt = new \DateTime($this->getEntity()->field_proposed_service_date->value);

        $term = \Drupal::entityTypeManager()
            ->getStorage('taxonomy_term')
            ->loadByProperties(['name' => 'Color Needed']);
        $this->task = Node::create([
            'type' => 'task',
            'title' => "Get the color preference for " . $this->getEntity()->title->value,
            'field_client_reference' => [
                $this->getEntity()->id(),
            ],
            'field_phase' => $term,
            'body' => "Reach out to " . $this->getEntity()->field_contact_name->value .
                " and have them select the color options "
                . $this->getEntity()->field_email->value . " " . $this->getEntity()->field_phone->value,
            'field_due_date' => $dt->modify('-7 days')->format('Y-m-d'),
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