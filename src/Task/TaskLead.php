<?php

namespace Drupal\concrete_finishers\Task;

use Drupal\concrete_finishers\Client\Client;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Url;
use Drupal\node\Entity\Node;

class TaskLead extends TaskBase
{

    const DURATION = '+2 days';

    public function createTask()
    {
        $dt = new \DateTime('now');
        $term = \Drupal::entityTypeManager()
            ->getStorage('taxonomy_term')
            ->loadByProperties(['name' => 'Lead']);

        $this->task = Node::create([
            'type' => 'task',
            'title' => "Schedule estimate and proposal",
            'field_client_reference' => [
                $this->getEntity(),
            ],
            'field_phase' => $term,
            'body' => "Contact " . $this->getEntity()->field_contact_name->value .
                "at " . $this->getEntity()->field_phone->value .
                ", or reach out via " . $this->getEntity()->field_email->value .
                "to schedule an estimate and create a proposal",
            'field_due_date' => $dt->modify(self::DURATION)
                ->format('Y-m-d'),
        ]);
        $this->getTask()->save();

        return $this->getTask();
    }

    public function getOperations()
    {
        try {
            $operations = [];
            $client = (new Client($this->getEntity()
                ->get('field_client_reference')
                ->getValue(0)[0]['target_id']))->getClient();
            $p = ($client) ? $client->get('field_proposal_reference')
                ->getValue(0) : null;
            $pid = null;
            if ($p && $p[0] && $p[0]['target_id']) {
                $pid = $p[0]['target_id'];
            }
            if (is_numeric($pid)) {
                $operations['email-proposal'] = [
                    'title' => t('Email Proposal'),
                    'url' => \Drupal\Core\Url::fromRoute('concrete_finishers.sendProposal',
                        ['entity_id' => $pid]),
                    'weight' => 0,
                ];
                $operations['view-proposal'] = [
                    'title' => t('View Proposal'),
                    'url' => Url::fromRoute('entity.node.canonical',
                        ['node' => $pid]),
                    'weight' => 0,
                ];
            } else {
                $operations['create-proposal'] = [
                    'title' => t('Create Proposal'),
                    'url' => \Drupal\Core\Url::fromRoute('concrete_finishers.createProposal',
                        ['entity_id' => $client->id()],
                        []),//->setOption('query', null),
                    'weight' => 0,
                ];
            }
        } catch (\Exception $e) {
            \Drupal::logger('task')->error($e->getMessage());
            drupal_set_message($e->getMessage(), 'error');
        }

        return $operations;
    }
}