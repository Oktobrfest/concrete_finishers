<?php

namespace Drupal\concrete_finishers\Plugin\Condition;

use Drupal\Core\Condition\ConditionPluginBase;
use Drupal\node\Entity\Node;

/**
 * Checks whether a proposal has been sent already
 *
 * @Condition(
 *   id = "rules_has_hash",
 *   label = @Translation("Has a hashtag in the url for tracking"),
 *   category = @Translation("Node"),
 *   context = {
 *     "node" = @ContextDefinition("entity:node",
 *       label = @Translation("Node")
 *     )
 *   }
 * )
 *
 */
class HasHashCondition extends ConditionPluginBase
{

    /**
     * {@inheritdoc}
     */
    public function evaluate()
    {
        /** @var Node $node */
        $node = $this->getContextValue('node');
        $hash = hash('md5', $node->id());
        $rHash = \Drupal::request()->query->get('hash');

        /*var_dump($rHash);exit();
        \Drupal::logger('HashCondition', $rHash . " ". $hash);*/
        return ($hash === $rHash) ? true : false;
    }

    /**
     * {@inheritdoc}
     */
    public function summary()
    {
        return $this->t('If the proposal has been sent already');
    }

}