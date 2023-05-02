<?php

namespace Drupal\concrete_finishers\Task;

use Drupal\Core\Entity\EntityInterface;

interface TaskInterface
{
  public function __construct(EntityInterface $entity);

  public function createTask();

  public function getOperations();
}