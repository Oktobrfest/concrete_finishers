<?php

namespace Drupal\concrete_finishers\Form;

use Drupal\commerce_stock\Entity\Stock;
use Drupal\commerce_stock\Entity\StockLocation;
use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\concrete_finishers\Client\Client;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\AppendCommand;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Database\Database;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\inline_entity_form\Element\InlineEntityForm;
use Drupal\node\Entity\Node;

/**
 * class ToolBinRestockForm extends FormBase
 */
class ToolBinRestockForm extends FormBase
{

    /**
     * {@inheritdoc}
     */
    public function getFormId()
    {
        return 'tool_bin_restock_form';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state)
    {
        $client = '';
        $path = \Drupal::service('path.alias_manager')
            ->getPathByAlias(\Drupal::service('path.current')->getPath());
        if (preg_match('/node\/(\d+)/', $path, $matches)) {
            $cid = $matches[1];
            $client = \Drupal\node\Entity\Node::load($cid);
        }
        $clientLoc = $client->field_location->getValue()[0]['target_id'];

        $form['client_id'] = [
            '#type' => 'hidden',
            '#required' => true,
            '#value' => $client->id(),
            '#title' => 'Client ID',
        ];

        $form['client_location'] = [
            '#type' => 'hidden',
            '#required' => true,
            '#value' => $clientLoc,
            '#title' => 'Client Location',
        ];
        $form['#attributes']['class'][] = 'form-inline';
        $form['variation_id'] = [
            '#type' => 'hidden',
            '#required' => true,
            '#value' => '',
            '#title' => $this->t('Product Variation ID'),
        ];
        $form['stock_id'] = [
            '#type' => 'hidden',
            '#required' => true,
            '#value' => '',
            '#title' => $this->t('Stock ID'),
        ];


        $form['restock_quantity'] = [
            '#type' => 'item',
            '#prefix' => '<span class="restock-quantity form-inline">',
            '#suffix' => ' - </span>',
            '#markup' => '',
        ];

        $form['bin_quantity'] = [
            '#type' => 'number',
            '#placeholder' => t('Bin Qnty'),
            '#required' => true,
            '#attributes' => [
                'class' => [
                    'form-inline',
                ],
            ],
        ];

        $form['stocking_quantity'] = [
            '#type' => 'number',
            '#required' => true,
            '#placeholder' => 'Qnty to Stock',
            '#attributes' => [
                'class' => [
                    'form-inline',
                ],
            ],
            '#prefix' => '<span class="stocking-quantity form-inline"> = ',
            '#suffix' => '</span>',
            '#help' => t('Quantity to restock'),
        ];

        $form['actions'] = [
            '#type' => 'button',
            '#value' => $this->t('Restock'),
            '#attributes' => [
                'class' => [
                    'use-ajax',
                    'btn btn-primary',
                    'form-inline',
                ],
            ],
            '#ajax' => [
                'callback' => 'Drupal\concrete_finishers\Form\ToolBinRestockForm::ajaxFormSubmit',
                'event' => 'click',
                'wrapper' => 'edit-output',
                'progress' => [
                    'type' => 'throbber',
                    'message' => t('Saving'),
                ],
            ],
        ];

        return $form;
    }

    /**
     * {@inheritdoc}
     */
    public function validateForm(array &$form, FormStateInterface $form_state)
    {
        /*$user_submit = &$form_state->getValue('values');
        if (empty($user_submit)) {
            $form_state->setErrorByName('sku',
                $this->t('Please at least provide one entry'));
        }*/
    }

    public function ajaxFormSubmit(array &$form, FormStateInterface $form_state)
    {
        $html = '<i class="fa fa-check" aria-hidden="true"></i>';
        $variation_id = \Drupal::request()->get('variation_id');
        $stock_id = \Drupal::request()->get('stock_id');
        $stocking_quantity = \Drupal::request()->get('stocking_quantity');
        $client = new Client(\Drupal::request()->get('client_id'));
        $team_location = $client->getClient()->field_team->getValue(0)[0]['target_id'];
        $s = Stock::load($stock_id);
        $stock = $s->toArray();


        try {
            $mv = \Drupal::entityTypeManager()
                ->getStorage('commerce_stock_movement')
                ->create([
                    'variation_id' => $variation_id,
                    'stock_id' => $stock_id,
                    'qty' => $stocking_quantity,
                    'location_id' => ClientMaterialsToolsForm::WAREHOUSE_ID,
                    'location_to_id' => $team_location,
                    'uid' => \Drupal::currentUser()->id(),
                    'description' => '',
                ]);

            $nQuantity = $stock['quantity'][0]['value'] - $stocking_quantity;
            $s->set('quantity', $nQuantity);
            $s->setChangeReason('');

            if ($mv->save()) {
                $s->save();
            }
        } catch (\Exception $e) {
            \Drupal::logger('ToolBinRestockForm')
                ->error($e->getMessage());
            drupal_set_message($e->getMessage(), 'error');
        }

        $res = new AjaxResponse();
        $res->addCommand(new AppendCommand('.submit-' . $variation_id, $html));

        return $res;
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state)
    {
    }


}