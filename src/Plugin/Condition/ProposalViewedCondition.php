<?php

namespace Drupal\concrete_finishers\Plugin\Condition;

use Drupal\Core\Condition\ConditionPluginBase;
use Drupal\node\Entity\Node;

/**
 * Checks whether a proposal has been sent already
 *
 * @Condition(
 *   id = "rules_proposal_viewed",
 *   label = @Translation("Proposal not viewed"),
 *   category = @Translation("Node"),
 *   context = {
 *     "node" = @ContextDefinition("entity:node",
 *       label = @Translation("Node")
 *     )
 *   }
 * )
 *
 */
class ProposalViewedCondition extends ConditionPluginBase {

  /**
   * {@inheritdoc}
   */
  public function evaluate() {
    /** @var Node $node */
    $node = $this->getContextValue('node');
    if (!$node instanceof Node || $node->bundle() != 'estimate_proposal') {
      return false;
    }
  $node = (new \Drupal\concrete_finishers\Client\Client(
    $node->get('field_client_reference')->getValue(0)[0]['target_id']
  ))->getClient();

    /** @var \Drupal\taxonomy\Entity\Term $term */
    $term = \Drupal::entityTypeManager()
      ->getStorage('taxonomy_term')
      ->loadByProperties(['name' => 'Proposal Viewed']);

    $task = \Drupal::entityTypeManager()
      ->getStorage('node')
      ->loadByProperties([
        'field_client_reference' => ($node) ? $node->id() : 0,
        'field_phase' => array_shift($term)->id(),
      ]);

    return ($task) ? false : true;
  }

  /**
   * {@inheritdoc}
   */
  public function summary() {
    return $this->t('If the proposal has been sent already');
  }

}