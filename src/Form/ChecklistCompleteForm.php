<?php

namespace Drupal\concrete_finishers\Form;

use Drupal\Core\Ajax\AfterCommand;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\AppendCommand;
use Drupal\Core\Ajax\CloseModalDialogCommand;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Ajax\OpenModalDialogCommand;
use Drupal\Core\Ajax\RemoveCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\views\Ajax\HighlightCommand;
use DateTime;

/**
 * ExampleForm class.
 */
class ChecklistCompleteForm extends FormBase
{

    /**
     * {@inheritdoc}
     */
    public function buildForm(
        array $form,
        FormStateInterface $form_state,
        $options = null
    ) {
        $form['complete_button'] = [
            '#type' => 'link',
            '#title' => $this->t('<i class="fa fa-flag-checkered" aria-hidden="true"></i> Finish'),
            '#attributes' => [
                'class' => [
                    'use-ajax',
                    'button btn btn-primary',
                ],
            ],
        ];

        // Attach the library for pop-up dialogs/modals.
        $form['#attached']['library'][] = 'core/drupal.dialog.ajax';

        return $form;
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function getFormId()
    {
        return 'concrete_finishers_checklist_complete_form';
    }


    /**
     * @param array $form
     * @param \Drupal\Core\Form\FormStateInterface $form_state
     *
     * @return \Drupal\Core\Ajax\AjaxResponse
     * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
     */
    public function ajaxNotesFormSubmit(
        array &$form,
        FormStateInterface $form_state
    ) {
        $entity_id = \Drupal::request()->get('entity_id');
        $dt = new DateTime('now');

        try {
            $cli = \Drupal::entityTypeManager()
                ->getStorage('paragraph')->load($entity_id);
            $cli->get('field_date_completed')
                ->setValue($dt->format("Y-m-d\Th:i:s"));
            $cli->save();
        } catch (\Exception $e) {
            \Drupal::logger('checklist')->error($e->getMessage());
            drupal_set_message($e->getMessage(), 'error');
        }

        $res = new AjaxResponse();
        $res->addCommand(new CloseModalDialogCommand(false));

        $uid = $form_state->getValue('field_performed_by')[0]['target_id'];
        $user = \Drupal\user\Entity\User::load($uid);
        $name = $user->getUsername();
        $res->addCommand(
            new HtmlCommand('.checklist-' . $entity_id . ' .views-field-field-performed-by',
                $name));

        $res->addCommand(
            new HtmlCommand('.checklist-' . $entity_id . ' .views-field-checklist-complete-field',
                $dt->format('m-d-Y h:iA T')));

        $output = $form_state->getValue('field_task_notes')[0]['value'];
        $res->addCommand(new HtmlCommand('.checklist-' . $entity_id . ' span.notes-wrapper',
            $output));
        $res->addCommand(new HighlightCommand('tr.checklist-' . $entity_id));

        return $res;
    }

    /**
     * Gets the configuration names that will be editable.
     *
     * @return array
     *   An array of configuration object names that are editable if called in
     *   conjunction with the trait's config() method.
     */
    protected function getEditableConfigNames()
    {
        return ['config.concrete_finishers_checklist_complete_form'];
    }

}