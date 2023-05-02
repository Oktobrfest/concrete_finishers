<?php

namespace Drupal\concrete_finishers\Form;

use Drupal\commerce_stock\Entity\Stock;
use Drupal\commerce_stock\Entity\StockLocation;
use Drupal\commerce_stock\Entity\StockMovement;
use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Database\Database;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\Entity\Node;
use Drupal\views\Annotation\ViewsCache;
use Drupal\views\Entity\View;

/**
 * UpdateClientInventoryForm class.
 */
class ClientMaterialsToolsForm extends FormBase
{

    const WAREHOUSE_ID = 1;

    /**
     * {@inheritdoc}
     */
    public function getFormId()
    {
        return 'client_materials_tools_form';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state)
    {
        $form['#theme'] = ['commerce_stock_inventory_control_form'];
        $ls = [];
        try {
            $lids = \Drupal::entityQuery('commerce_stock_location')
                ->condition('location_type', '298')
                ->execute();
            $locs = \Drupal::entityTypeManager()
                ->getStorage('commerce_stock_location')
                ->loadMultiple($lids);
            //var_dump($locs);exit();

            foreach ($locs as $id => $loc) {
                $ls[$id] = $loc->get('name')->value;
            }
        } catch (\Exception $e) {

        }

        $client = '';
        $path = \Drupal::service('path.alias_manager')->getPathByAlias(\Drupal::service('path.current')->getPath());
        if(preg_match('/node\/(\d+)/', $path, $matches)) {
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

        $form['location_type'] = [
            '#default_value' => $form_state->getValue("location_type"),
            '#type' => 'radios',
            '#required' => true,
            '#options' => $ls,
            '#title' => $this->t('Team'),
        ];

        // If we have user submitted values, that means this is triggered by form rebuild because of SKU not found
        $user_submit = $form_state->getValue('values');
        if (isset($user_submit)) {
            $invalidSKUPos = $form_state->getStorage();
            foreach ($user_submit as $pos => $row) {
                $value_form = &$form['values'][$pos];
                $value_form = [
                    '#parents' => ['values', $pos],
                ];
                $value_form['sku'] = [
                    '#type' => 'textfield',
                    '#default_value' => $row['sku'],
                    '#required' => true,
                    '#attributes' => ['readonly' => 'readonly'],
                    '#prefix' => '<div class="sku">',
                    '#suffix' => '</div>',
                ];
                if (isset($invalidSKUPos[$pos]) && $invalidSKUPos[$pos]) {
                    $value_form['sku']['#attributes']['class'][] = 'error';
                }
                $value_form['quantity'] = [
                    '#type' => 'number',
                    '#default_value' => $row['quantity'],
                    '#required' => true,
                    '#prefix' => '<div class="quantity">',
                    '#suffix' => '</div>',
                ];
                $value_form['remove'] = [
                    '#markup' => '<div type="button" class="button delete-item-button">Remove</div>',
                ];
            }
        }

        $form['values'] = [
            '#type' => 'table',
            '#header' => [
                $this->t('SKU'),
                $this->t('Name'),
                $this->t('Quantity'),
                $this->t('Operations'),
            ],
            '#weight' => 1,
        ];

        $form['sku'] = [
            '#type' => 'textfield',
            '#autocomplete_route_name' => 'commerce_stock.sku_autocomplete',
            '#placeholder' => t('Scan or Type SKU number...'),
            '#required' => false,
            '#title' => $this->t('Add Materials'),
            '#weight' => 999,
        ];

        $form['description'] = [
            '#type' => 'textarea',
            '#default_value' => null,
            '#required' => false,
            '#placeholder' => t('Please provide a log entry...'),
            '#title' => $this->t('Description'),
        ];

        $form['actions']['submit'] = [
            '#type' => 'submit',
            '#value' => $this->t('Transfer'),
            '#attributes' => [
                'class' => [
                    'btn btn-primary',
                ],
            ],
        ];
        $form['actions']['submit']['#attributes']['class'][] = 'use-ajax submit-btn';
        $form['actions']['submit']['#attributes']['data-dialog-type'][] = 'modal';
        $form['actions']['submit']['#ajax'] = [
            'callback' => '::submitForm',
            'event' => 'click',
            'wrapper' => 'edit-output',
            'progress' => [
                'type' => 'throbber',
                'message' => t('Saving'),
            ],
        ];*/

        return $form;
    }

    /**
     * {@inheritdoc}
     */
    public function validateForm(array &$form, FormStateInterface $form_state)
    {
        $user_submit = &$form_state->getValue('values');
        if (empty($user_submit)) {
            $form_state->setErrorByName('sku',
                $this->t('Please at least provide one entry'));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state)
    {
        $formSkus = &$form_state->getValue('values');
        $location = &$form_state->getValue('location_type');
        $desc =  &$form_state->getValue('description');
        $cl =  &$form_state->getValue('client_location');
        $cid =  &$form_state->getValue('client_id');
        $quantities = [];

        $client = Node::load($cid);
        $client->set('field_team', $location);
        try {
            $client->save();
        } catch (\Exception $e) {
            \Drupal::logger('ClientMaterialsToolsForm')
                ->error($e->getMessage());
            drupal_set_message($e->getMessage(), 'error');
        }

        $skus = [];
        foreach ($formSkus as $sku) {
            $skus[$sku['variation_id']] = [
                'quantity' => $sku['quantity'],
                'variation_id' => $sku['variation_id'],
            ];
        }
        $stock = $this->getStocks(array_keys($skus), self::WAREHOUSE_ID);

        /** @var Stock $s */
        if (!empty($stock)) {
            foreach ($stock as $s) {
                try {
                    $mv = \Drupal::entityTypeManager()
                        ->getStorage('commerce_stock_movement')
                        ->create([
                            'variation_id' => $skus[$s->id()]['variation_id'],
                            'stock_id' => $s->id(),
                            'qty' => $skus[$s->id()]['quantity'],
                            'location_id' => self::WAREHOUSE_ID,
                            'location_to_id' => $cl,
                            'uid' => \Drupal::currentUser()->id(),
                            'description' => $desc,
                        ]);

                    $nQuantity = $s->get('quantity')->value - $skus[$s->id()]['quantity'];
                    $s->set('quantity', $nQuantity);
                    $s->setChangeReason($desc);

                    if ($mv->save()) {
                        $s->save();
                    }
                } catch (\Exception $e) {
                    \Drupal::logger('ClientMaterialsToolsForm')
                        ->error($e->getMessage());
                    drupal_set_message($e->getMessage(), 'error');
                    continue;
                }
                $cache = View::load('stock_movement');
                $cache->invalidateCaches();
                var_dump($cache->getCacheTagsToInvalidate());exit();
                array(1) { [0]=> string(32) "config:views.view.stock_movement" }
            }
        }
    }


    /**
     * @param $sku
     *
     * @return bool
     */
    protected function validateSku($sku)
    {
        $result = \Drupal::entityQuery('commerce_product_variation')
            ->condition('sku', $sku)
            ->condition('status', 1)
            ->execute();

        return $result ? true : false;
    }


    /**
     * @param $sku
     * @param $location_id
     *
     * @return \Drupal\Core\Entity\EntityInterface[]|null
     */
    public function getStocks($sku, $location_id)
    {
        //var_dump($sku);exit();
        try {
            $connection = Database::getConnection('default', null);
            $query = $connection->select('commerce_product_variation__field_stock',
                'cs');
            $query->join('commerce_product_variation_field_data', 'cr',
                'cr.variation_id=cs.entity_id');
            $query->join('commerce_stock_field_data', 'csf',
                'csf.stock_id=cs.field_stock_target_id');
            $query->fields('cs', ['field_stock_target_id']);
            //$query->addField('cr', 'sku', 'sku');
            $query->condition('csf.stock_id', $sku, 'IN');
            $query->condition('csf.stock_location', $location_id);

            $ids = $query->execute()->fetchCol();

            if ($ids) {
                return \Drupal::entityTypeManager()
                    ->getStorage('commerce_stock')
                    ->loadMultiple($ids);
            } else {
                return null;
            }
        } catch (\Exception $e) {
            \Drupal::logger('ClientMaterialsToolsForm')
                ->error("GET STOCKS " . $e->getMessage());
            drupal_set_message($e->getMessage(), 'error');
        }

    }

}