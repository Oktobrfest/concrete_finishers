<?php

namespace Drupal\concrete_finishers\Task;

use Drupal\Core\Entity\EntityInterface;

class TaskBase implements \Drupal\concrete_finishers\Task\TaskInterface
{

    /**
     * @var EntityInterface
     */
    protected $entity;

    /**
     * @var EntityInterface
     */
    protected $task;

    public function __construct(\Drupal\Core\Entity\EntityInterface $entity)
    {
        $this->setEntity($entity);

    }

    /**
     * @return \Drupal\Core\Entity\EntityInterface
     */
    public function getEntity()
    {
        return $this->entity;
    }

    /**
     * @param \Drupal\Core\Entity\EntityInterface $entity
     */
    public function setEntity($entity)
    {
        $this->entity = $entity;
    }

    /**
     * @return \Drupal\Core\Entity\EntityInterface
     */
    public function getTask()
    {
        return $this->task;
    }

    /**
     * @param \Drupal\Core\Entity\EntityInterface $task
     */
    public function setTask($task)
    {
        $this->task = $task;
    }

    public function createTask()
    {
        // TODO: Implement createTask() method.
    }

    public function getOperations()
    {
        // TODO: Implement getOperations() method.
    }

    /**
     * @param $phase
     *
     * @return \Drupal\Core\Entity\EntityInterface[]
     */
    public function completePreviousTask($phase)
    {
        try {
            /** @var \Drupal\taxonomy\Entity\Term $term */
            $term = \Drupal::entityTypeManager()
                ->getStorage('taxonomy_term')
                ->loadByProperties(['name' => $phase]);

            /** @var Node $task */
            $tasks = \Drupal::entityTypeManager()
                ->getStorage('node')
                ->loadByProperties([
                    'field_client_reference' => $this->getEntity()->id(),
                    'field_phase' => array_shift($term)->id(),
                ]);

            if ($tasks) {
                $dt = new \DateTime('now');
                foreach ($tasks as $task) {
                    $task->setPublished(false);
                    $task->field_date_completed->setValue($dt->format("Y-m-d\Th:i:s"));
                    $task->save();
                }
            }

            return $tasks;
        } catch (\Exception $e) {
            \Drupal::logger('Task Center')
                ->error('Error marking the previous task as complete ' . $e->getMessage(),
                    $this->getEntity()->toArray());
        }
    }
}