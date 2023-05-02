<?php

namespace Drupal\concrete_finishers\Plugin\Condition;

use Drupal\Core\Condition\ConditionPluginBase;
use Drupal\node\Entity\Node;

/**
 * Checks whether a proposal has been sent already
 *
 * @Condition(
 *   id = "rules_invoice_sent",
 *   label = @Translation("Invoice not sent"),
 *   category = @Translation("Node"),
 *   context = {
 *     "node" = @ContextDefinition("entity:node",
 *       label = @Translation("Node")
 *     )
 *   }
 * )
 *
 */
class InvoiceSentCondition extends ConditionPluginBase {

  /**
   * {@inheritdoc}
   */
  public function evaluate() {
    /** @var Node $node */
    $node = $this->getContextValue('node');
    if (!$node instanceof Node || $node->bundle() != 'new_client_form') {
      return false;
    }

    /** @var \Drupal\taxonomy\Entity\Term $term */
    $term = \Drupal::entityTypeManager()
      ->getStorage('taxonomy_term')
      ->loadByProperties(['name' => 'Invoiced']);

    $task = \Drupal::entityTypeManager()
      ->getStorage('node')
      ->loadByProperties([
        'field_client_reference' => $node->id(),
        'field_phase' => array_shift($term)->id(),
      ]);

    return ($task) ? true : false;
  }

  /**
   * {@inheritdoc}
   */
  public function summary() {
    return $this->t('If the proposal has been sent already');
  }

}