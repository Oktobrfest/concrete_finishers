<?php

namespace Drupal\concrete_finishers\Task;

use Drupal\concrete_finishers\Client\Client;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Url;
use Drupal\node\Entity\Node;


class TaskInvoiced extends TaskBase
{

    const DURATION = '+30 days';

    public function createTask()
    {
        $dt = new \DateTime('now');
        $term = \Drupal::entityTypeManager()
            ->getStorage('taxonomy_term')
            ->loadByProperties(['name' => 'Invoiced']);

        $this->task = Node::create([
            'type' => 'task',
            'title' => "Follow up with " . $this->getEntity()->field_contact_name->value
                . " to make sure invoice is paid",
            'field_client_reference' => [
                $this->getEntity()->id(),
            ],
            'field_phase' => $term,
            'body' => "Reach out to the client and remind them to complete the invoice payment. "
                . $this->getEntity()->field_email->value . " " . $this->getEntity()->field_phone->value,
            'field_due_date' => $dt->modify(self::DURATION)
                ->format('Y-m-d'),
        ]);
        $this->getTask()->save();

        return $this->getTask();
    }

    public function getOperations()
    {
        $c = (new Client($this->getEntity()->get('field_client_reference')
            ->getValue(0)[0]['target_id']));
        $client = $c->getClient();

        $operations = [];
        $operations['view-proposal'] = [
            'title' => t('View Proposal'),
            'url' => \Drupal\Core\Url::fromRoute('entity.node.canonical',
                ['node' => $client->get('field_proposal_reference')
                    ->getValue(0)[0]['target_id']]),
            'weight' => 0,
        ];
        $operations['view-invoice'] = [
            'title' => t('View Invoice'),
            'url' => Url::fromRoute('entity.node.canonical',
                ['node' => $client->get('field_invoice_reference')
                    ->getValue(0)[0]['target_id']]),
            'weight' => 0,
        ];

        return $operations;
    }
}