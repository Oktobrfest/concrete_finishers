<?php

namespace Drupal\concrete_finishers\Task;

use Drupal\concrete_finishers\Client\Client;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Url;
use Drupal\node\Entity\Node;

class TaskReview extends TaskBase
{

    public function createTask()
    {
        $dt = new \DateTime('now');

        $term = \Drupal::entityTypeManager()
            ->getStorage('taxonomy_term')
            ->loadByProperties(['name' => 'Review']);

        $this->task = Node::create([
            'type' => 'task',
            'title' => "Send data sheets and review links to " . $this->getEntity()->field_contact_name->value,
            'field_client_reference' => [
                $this->getEntity()->id(),
            ],
            'field_phase' => $term,
            'body' => "Email data sheets and review links to " . $this->getEntity()->field_contact_name->value .
                " via email @ " . $this->getEntity()->field_email->value,
            'field_due_date' => $dt->modify('+3 days')->format('Y-m-d'),
        ]);
        $this->getTask()->save();

        return $this->getTask();
    }

    public function getOperations()
    {
        try {
            $client = (new Client($this->getEntity()
                ->get('field_client_reference')
                ->getValue(0)[0]['target_id']))->getClient();
        } catch (\Exception $e) {
            \Drupal::logger('TaskReview')->error($e->getMessage());
            drupal_set_message($e->getMessage(), 'error');
            return [];
        }



        $operations = [];
        $operations['send-closure-email'] = [
            'title' => t('Send Closure Email'),
            'url' => \Drupal\Core\Url::fromRoute('concrete_finishers.sendClosureEmail',
                ['entity_id' => $client->id()]),
            'weight' => 0,
        ];
        if ($client->field_proposal_reference->value) {
            $operations['view-proposal'] = [
                'title' => t('View Proposal'),
                'url' => \Drupal\Core\Url::fromRoute('entity.node.canonical',
                    [
                        'node' => $client->get('field_proposal_reference')
                            ->getValue(0)[0]['target_id']
                    ]),
                'weight' => 0,
            ];
        }
        if ($client->field_invoice_reference->value) {
            $operations['view-invoice'] = [
                'title' => t('View Invoice'),
                'url' => Url::fromRoute('entity.node.canonical',
                    [
                        'node' => $client->get('field_invoice_reference')
                            ->getValue(0)[0]['target_id']
                    ]),
                'weight' => 0,
            ];
        }

        return $operations;
    }
}