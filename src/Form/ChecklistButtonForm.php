<?php

namespace Drupal\concrete_finishers\Form;

use Drupal\Core\Ajax\AfterCommand;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\AppendCommand;
use Drupal\Core\Ajax\CloseModalDialogCommand;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Ajax\OpenModalDialogCommand;
use Drupal\Core\Ajax\RemoveCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\views\Ajax\HighlightCommand;

/**
 * ExampleForm class.
 */
class ChecklistButtonForm extends FormBase {

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state, $options = NULL) {
        $form['edit_notes_button'] = [
            '#type' => 'link',
            '#title' => $this->t('<i class="fa fa-pencil" aria-hidden="true"></i>'),
            '#attributes' => [
                'class' => [
                    'use-ajax',
                    'button',
                ],
            ],
        ];

        // Attach the library for pop-up dialogs/modals.
        $form['#attached']['library'][] = 'core/drupal.dialog.ajax';
        $form['#attached']['library'][] = 'concrete_finishers/concrete_finishers.commands';

        return $form;
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state) {}

    /**
     * {@inheritdoc}
     */
    public function getFormId() {
        return 'concrete_finishers_checklist_button_form';
    }

    public function ajaxNotesFormSubmit(array &$form, FormStateInterface $form_state)
    {
        $res = new AjaxResponse();
        $res->addCommand(new CloseModalDialogCommand(false));
        $entity_id = \Drupal::request()->get('entity_id');
        $output = $form_state->getValue('field_task_notes')[0]['value'];
        $res->addCommand(new HtmlCommand('.checklist-' . $entity_id . ' span.notes-wrapper', $output));

        return $res;
    }

    /**
     * Gets the configuration names that will be editable.
     *
     * @return array
     *   An array of configuration object names that are editable if called in
     *   conjunction with the trait's config() method.
     */
    protected function getEditableConfigNames() {
        return ['config.concrete_finishers_checklist_button_form'];
    }

}