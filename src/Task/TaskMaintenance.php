<?php

namespace Drupal\concrete_finishers\Task;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Url;
use Drupal\node\Entity\Node;

class TaskMaintenance extends TaskBase
{

  public function createTask() {
    $dt = new \DateTime('now');
    $term = \Drupal::entityTypeManager()
      ->getStorage('taxonomy_term')
      ->loadByProperties(['name' => 'Proposed']);

    $client = (new \Drupal\concrete_finishers\Client\Client(
      $this->getEntity()->get('field_client_reference')->getValue(0)[0]['target_id']
    ))->getClient();

    $this->task = Node::create([
      'type' => 'task',
      'title' => "Follow up with @clientName about the proposal",
      'field_client_reference' => [
        $client->id(),
      ],
      'field_phase' => $term,
      'body' => "Reach out to the client and remind them to complete the proposal. @phone @email.",
      'field_due_date' => $dt->modify('+2 days')
        ->format('Y-m-d'),
    ]);
    $this->getTask()->save();

    return $this->getTask();
  }

  public function getOperations() {
    $operations = [];
    $operations['email-proposal'] = [
      'title' => t('Send Reminder Email'),
      'url' => \Drupal\Core\Url::fromRoute('concrete_finishers.sendProposal',
        ['entity_id' => $this->getEntity()->id()]),
      'weight' => 0,
    ];
    $operations['view-proposal'] = [
      'title' => t('View Proposal'),
      'url' => Url::fromRoute('entity.node.canonical', ['node' => $this->getEntity()->id()]),
      'weight' => 0,
    ];

    return $operations;
  }
}