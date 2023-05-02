<?php

namespace Drupal\concrete_finishers\Plugin\views\field;

use Drupal\commerce_stock\Entity\Stock;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\CloseModalDialogCommand;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element\Date;
use Drupal\Core\Render\Element\Form;
use Drupal\Core\Url;
use Drupal\node\Entity\NodeType;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;
use DateTime;

/**
 * Field handler to flag the node type.
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("tool_bin_restock_field")
 */
class ToolBinRestockField extends FieldPluginBase
{

    /**
     * @{inheritdoc}
     */
    public function query()
    {
        // Leave empty to avoid a query on this field.
    }

    /**
     * Define the available options
     *
     * @return array
     */
    protected function defineOptions()
    {
        $options = parent::defineOptions();
        return $options;
    }

    /**
     * @param $form
     * @param \Drupal\Core\Form\FormStateInterface $form_state
     */
    public function buildOptionsForm(&$form, FormStateInterface $form_state)
    {
        parent::buildOptionsForm($form, $form_state);
    }


    /**
     * @param \Drupal\views\ResultRow $values
     *
     * @return array|\Drupal\Component\Render\MarkupInterface|\Drupal\views\Render\ViewsRenderPipelineMarkup|string
     */
    public function render(ResultRow $values)
    {
        $form = \Drupal::formBuilder()
            ->getForm('Drupal\concrete_finishers\Form\ToolBinRestockForm');
        $entity = $values->_entity->toArray();
        if (!array_key_exists('field_stock', $entity)) {
            return $form;
        }
        $stock = Stock::load($entity['field_stock'][0]['target_id']);

        if (!$stock) {
            return $form;
        } else {
            $stock = $stock->toArray();
        }

        //var_dump($entity);exit();
        if (!empty($entity['field_restock_quantity'])) {
            $form['actions']['#attributes']['class'][] = 'submit-' . $entity['variation_id'][0]['value'];
            $form['restock_quantity']['#markup'] = $entity['field_restock_quantity'][0]['value'];
            $form['variation_id']['#value'] = $entity['variation_id'][0]['value'];
            $form['stock_id']['#value'] = $stock['stock_id'][0]['value'];
        }
        return $form;
    }
}