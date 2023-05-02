<?php

namespace Drupal\concrete_finishers\Task;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Url;
use Drupal\node\Entity\Node;

class TaskProposalViewed extends TaskBase
{

    /**
     * @var $task \Drupal\node\Entity\Node
     */
    protected $entity;

    /**
     * @return \Drupal\Core\Entity\EntityInterface
     */

  public function createTask() {
    $dt = new \DateTime('now');
    $term = \Drupal::entityTypeManager()
      ->getStorage('taxonomy_term')
      ->loadByProperties(['name' => 'Proposal Viewed']);

    $this->task = Node::create([
      'type' => 'task',
      'title' => $this->getEntity()->field_contact_name->value . " viewed the proposal",
      'field_client_reference' => [
        $this->getEntity()->id(),
      ],
      'field_phase' => $term,
      'body' => $this->getEntity()->field_contact_name->value . " viewed the proposal",
      'field_due_date' => $dt->format("Y-m-d"),
      'field_date_completed' => $dt->format("Y-m-d\Th:i:s"),
    ]);
    $this->getTask()->setPublished(false);
    $this->getTask()->save();

    return $this->getTask();
  }

  public function getOperations() {
    $operations = [];

    return $operations;
  }
}