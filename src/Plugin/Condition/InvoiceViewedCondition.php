<?php

namespace Drupal\concrete_finishers\Plugin\Condition;

use Drupal\concrete_finishers\Client\Client;
use Drupal\Core\Condition\ConditionPluginBase;
use Drupal\node\Entity\Node;

/**
 * Checks whether an invoice has been viewed already
 *
 * @Condition(
 *   id = "rules_invoice_viewed",
 *   label = @Translation("Invoice not viewed"),
 *   category = @Translation("Node"),
 *   context = {
 *     "node" = @ContextDefinition("entity:node",
 *       label = @Translation("Node")
 *     )
 *   }
 * )
 *
 */
class InvoiceViewedCondition extends ConditionPluginBase
{

    /**
     * {@inheritdoc}
     */
    public function evaluate()
    {
        /** @var Node $node */
        $node = $this->getContextValue('node');
        if (!$node instanceof Node || $node->bundle() != 'invoice') {
            return false;
        }
        $client = new Client();
        $p = $client->getProposal($node->get('field_proposal_reference')->getValue(0)[0]['target_id']);
        //print_r($p->toArray());exit();

        /** @var \Drupal\taxonomy\Entity\Term $term */
        $term = \Drupal::entityTypeManager()
            ->getStorage('taxonomy_term')
            ->loadByProperties(['name' => 'Invoice Viewed']);

        $task = \Drupal::entityTypeManager()
            ->getStorage('node')
            ->loadByProperties([
                'field_client_reference' => $p->get('field_client_reference')->getValue(0)[0]['target_id'],
                'field_phase' => array_shift($term)->id(),
            ]);

        return ($task) ? false : true;
    }

    /**
     * {@inheritdoc}
     */
    public function summary()
    {
        return $this->t('If the proposal has been sent already');
    }

}