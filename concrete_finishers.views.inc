<?php


/**
 * Implements hook_views_data_alter().
 *
 * @param array $data
 */
function concrete_finishers_views_data_alter(array &$data)
{

    //var_dump($data);exit();
    $data['paragraphs_item']['checklist_notes_field'] = [
        'title' => t('Checklist Editable Notes'),
        'field' => [
            'title' => t('Checklist Editable Notes'),
            'help' => t('Checklist Editable Notes'),
            'id' => 'checklist_notes_field',
        ],
    ];
    $data['paragraphs_item']['checklist_complete_field'] = [
        'title' => t('Checklist Complete Items'),
        'field' => [
            'title' => t('Checklist Complete Items'),
            'help' => t('Checklist Complete Items'),
            'id' => 'checklist_complete_field',
        ],
    ];

    $data['commerce_product_variation']['tool_bin_restock_field'] = [
        'title' => t('Tool Bin Restock Field'),
        'field' => [
            'title' => t('Tool Bin Restock'),
            'help' => t('Used to restock the team tool bins'),
            'id' => 'tool_bin_restock_field',
        ],
    ];
}

/**
 * Implementation of hook_views_data()
 */
function concrete_finishers_views_data() {
    $data['views']['client_materials_tools'] = array(
        'title' => t('Client Materials and Tools'),
        'help' => t('Client Materials and Tools Form'),
        'area' => array(
            'id' => 'client_materials_tools',
        ),
    );

    return $data;
}

function concrete_finishers_views_query_alter(\Drupal\views\ViewExecutable $view, \Drupal\views\Plugin\views\query\QueryPluginBase $query) {
    if ($view->getTitle() == 'Stock movement') {
        foreach ($query->where as &$condition_group) {
            $condition_group['type'] = 'OR';
        }
    }
}

/**
 * @file: MY_MODULE.module
 *
 * Implemented hook_views_api().
 */
function concrete_finishers_views_api() {
    return array(
        'api' => 3,
    );
}