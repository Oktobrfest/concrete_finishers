<?php

namespace Drupal\concrete_finishers\Plugin\Action;

use Drupal\Core\Action\ActionBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\Entity\Node;

/**
 * Removes the "needs review" flag on a migrated news article.
 *
 * @Action(
 *   id = "new_client_action",
 *   label = @Translation("Take action after a new client is created"),
 *   type = "entity"
 * )
 */
class NewClientAction extends ActionBase {

  /**
   * {@inheritdoc}
   */
  public function execute($node = NULL) {
    $dt = new \DateTime('now');
    $task = Node::create([
      'type' => 'task',
      'title' => "Schedule a visit for estimate",
      'field_client_reference' => [
        $node->id(),
      ],
      'body' => "Call the client at @phone, or reach out via @email",
      'field_due_date' => $dt->modify('+2 days')
        ->format('Y-m-d'),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, AccountInterface $account = NULL, $return_as_object = FALSE) {

    return true;
   /** @var \Drupal\node\NodeInterface $object */
   $result = $object->field_news_migration_review->access('edit', $account, TRUE)
     ->andIf($object->access('update', $account, TRUE));

   return $return_as_object ? $result : $result->isAllowed();
  }

}