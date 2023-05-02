<?php

namespace Drupal\concrete_finishers\Plugin\views\field;

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
 * @ViewsField("checklist_complete_field")
 */
class ChecklistCompleteField extends FieldPluginBase {
    /**
     * @{inheritdoc}
     */
    public function query() {
        // Leave empty to avoid a query on this field.
    }
    /**
     * Define the available options
     * @return array
     */
    protected function defineOptions() {
        $options = parent::defineOptions();
        return $options;
    }

    /**
     * @param $form
     * @param \Drupal\Core\Form\FormStateInterface $form_state
     */
    public function buildOptionsForm(&$form, FormStateInterface $form_state) {
        parent::buildOptionsForm($form, $form_state);
    }


    /**
     * @param \Drupal\views\ResultRow $values
     *
     * @return array|\Drupal\Component\Render\MarkupInterface|\Drupal\views\Render\ViewsRenderPipelineMarkup|string
     */
    public function render(ResultRow $values)
    {
        //xdebug_var_dump($values->_relationship_entities['field_checklist_item']->id());
        if ($values->_relationship_entities['field_checklist_item']->field_date_completed->value == "") {
            $url = Url::fromRoute('concrete_finishers.submitCompleteForm',
                ['entity_id' => $values->_relationship_entities['field_checklist_item']->id()],
                ['fragment' => 'concrete-finishers-checklist-complete-form--' . $values->index]);
            $form = \Drupal::formBuilder()->getForm('Drupal\concrete_finishers\Form\ChecklistCompleteForm');
            $form['complete_button']['#url'] = $url;
            $form['#action'] = $url->toString();
            return $form;
        } else {
            $dt = DateTime::createFromFormat("Y-m-d\Th:i:s",
                $values->_relationship_entities['field_checklist_item']->field_date_completed->value);

            return $dt->format('m-d-Y h:iA T');
        }
    }
}