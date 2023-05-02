<?php

namespace Drupal\concrete_finishers\Plugin\views\field;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\CloseModalDialogCommand;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element\Form;
use Drupal\Core\Url;
use Drupal\node\Entity\NodeType;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;
use Drupal\concrete_finishers\Form\ChecklistButtonForm;
use Drupal\entity_reference_revisions\EntityReferenceRevisionsFieldItemList;
/**
 * Field handler to flag the node type.
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("user_select")
 */
class UserSelect extends FieldPluginBase {
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
        $form = \Drupal::formBuilder()->getForm('Drupal\concrete_finishers\Form\ChecklistButtonForm');
        $form['edit_notes_button']['#url'] = Url::fromRoute('concrete_finishers.checklistNotesForm',
            ['entity_id' => $values->_relationship_entities['field_checklist_item']->id()]);
        return $form;
    }
}