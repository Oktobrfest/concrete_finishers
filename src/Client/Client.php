<?php

namespace Drupal\concrete_finishers\Client;

use Drupal\node\Entity\Node;
use Drupal\paragraphs\Entity\Paragraph;
use Masterminds\HTML5\Exception;
use net\authorize\api\contract\v1 as AnetAPI;
use net\authorize\api\controller as AnetController;

class Client
{

    protected $clientId;

    protected $proposalId;

    protected $client;

    protected $proposal;

    public function __construct($clientId = null)
    {
        if ($clientId) {
            $this->clientId = $clientId;
        }
    }


    /**
     * Get the invoice
     *
     * @param null $clientId
     *
     * @return \Drupal\Core\Entity\EntityInterface|null
     * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
     */
    public function getClient($clientId = null)
    {
        if ($clientId) {
            $this->clientId = $clientId;
        }
        if (!$this->client && $this->clientId) {
            $this->client = \Drupal::entityTypeManager()
                ->getStorage('node')
                ->load($this->clientId);
        }
        return $this->client;
    }

    /**
     * @param $clientId
     *
     * @return $this
     */
    public function setClientId($clientId)
    {
        $this->clientId = $clientId;
        return $this;
    }

    /**
     * Get the proposal
     *
     * @param null $proposalId
     *
     * @return \Drupal\Core\Entity\EntityInterface|null
     * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
     */
    public function getProposal($proposalId = null)
    {
        if ($proposalId) {
            $this->proposalId = $proposalId;
        }
        if (!$this->proposal && $this->proposalId) {
            $this->proposal = \Drupal::entityTypeManager()
                ->getStorage('node')
                ->load($this->proposalId);
        }
        return $this->proposal;
    }


    /**
     * @return \Drupal\Core\Entity\EntityInterface|static
     * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
     * @throws \Drupal\Core\Entity\EntityStorageException
     */
    public function createProposal()
    {
        try {
            $project = \Drupal::entityTypeManager()
                ->getStorage('taxonomy_term')
                ->load($this->getClient()->field_pro->getValue(0)[0]['target_id']);

            $service = \Drupal::entityTypeManager()
                ->getStorage('taxonomy_term')
                ->load($this->getClient()->field_service_type->getValue(0)[0]['target_id']);


            $this->proposal = Node::create([
                'type' => 'estimate_proposal',
                'title' => $this->getClient()->title->value . " - " . $project->name->value,
                'field_client_reference' => [
                    $this->clientId,
                ],
                'field_line_item' => $this->createProposalLineItems($service),
                'field_processing_fee' => 0.00,
            ]);

            $this->proposal->save();
            $this->getClient()->field_proposal_reference
                ->setValue([$this->getProposal()]);
            $this->getClient()->save();
        } catch (Exception $e) {
            \Drupal::logger('task')->error($e->getMessage());
            drupal_set_message($e->getMessage(), 'error');
        }

        return $this->proposal;
    }


    /**
     * @param $proposal
     *
     * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
     * @throws \Drupal\Core\Entity\EntityStorageException
     */
    public function saveProposal($proposal)
    {
        $this->proposal = $proposal;
        $this->getClient()->get('field_proposal_reference')
            ->setValue([$proposal->id()]);
        $this->getClient()->save();
    }


    /**
     * @param $newTask
     *
     * @return bool
     * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
     * @throws \Drupal\Core\Entity\EntityStorageException
     */
    public function saveTask($newTask)
    {
        $tasks = [];
        if ($this->getClient()) {
            foreach ($this->getClient()
                         ->get('field_tasks')
                         ->getValue() as $task) {
                if ($task['target_id'] !== $newTask->id()) {
                    $tasks[] = $task;
                } else {
                    return false;
                }
            }
            $tasks[] = $newTask;
            $this->getClient()->get('field_tasks')->setValue($tasks);
            $this->getClient()->save();
        }
    }

    /**
     * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
     * @throws \Drupal\Core\Entity\EntityStorageException
     */
    public function createChecklist()
    {
        $service = $this->getClient()->field_service_type->getValue();
        /** @var \Drupal\taxonomy\Entity\Term $service */
        $service = \Drupal::entityTypeManager()
            ->getStorage('taxonomy_term')
            ->load($service[0]['target_id']);

        $terms = \Drupal::entityTypeManager()->getStorage('taxonomy_term')
            ->loadTree($service->getVocabularyId(), $service->id(), null, TRUE);
        $all = [];
        foreach ($terms as $term) {
            $checklist = Paragraph::create([
                'type' => 'checklist',
                'field_checklist_group' => $term,
                'field_checklist_item' => $this->createChecklistItems($term)
            ]);
            $checklist->save();
            $all[] = $checklist;
        }

        $this->getClient()->field_checklist_group->setValue($all);
        $this->getClient()->save();
    }


    /**
     * @param $invoice
     *
     * @return bool
     * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
     * @throws \Drupal\Core\Entity\EntityStorageException
     */
    public function saveInvoice($invoice)
    {
        $proposal = $this->getProposal($invoice->get('field_proposal_reference')
            ->getValue(0)[0]['target_id']);
        $client = $this->getClient($proposal->get('field_client_reference')
            ->getValue(0)[0]['target_id']);

        $invoices = [];
        if ($this->getClient()) {
            foreach ($this->getClient()
                         ->get('field_invoice_reference')
                         ->getValue() as $i) {
                if ($i['target_id'] !== $invoice->id()) {
                    $invoices[] = $i['target_id'];
                } else {
                    return false;
                }
            }
            $invoices[] = $invoice->id();
            $this->getClient()
                ->get('field_invoice_reference')
                ->setValue($invoices);
            $this->getClient()->save();
        }

    }


    /**
     * @param $service \Drupal\taxonomy\Entity\Term
     *
     * @return array
     * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
     */
    protected function createProposalLineItems($service)
    {
        $retval = [];

        if ($service->field_default_line_items) {
            foreach ($service->field_default_line_items->getValue() as $li) {
                /** @var Paragraph $dli */
                $dli = \Drupal::entityTypeManager()
                    ->getStorage('paragraph')
                    ->load($li['target_id']);
                $newLi = $dli->createDuplicate();
                $newLi->save();
                $retval[] = $newLi;
                foreach ($dli->field_line_item_options->getValue() as $lio) {
                    $po = \Drupal::entityTypeManager()
                        ->getStorage('paragraph')
                        ->load($lio['target_id']);

                    $newLio = $lio->createDuplicate();
                    $newLio->save();
                    $retval[] = $newLio;

                }
            }
        }

        return $retval;
    }

    /**
     * @param $service
     *
     * @return array
     * @throws \Drupal\Core\Entity\EntityStorageException
     */
    protected function createChecklistItems($service)
    {
        $retval = [];
        if ($service->field_default_checklist_items) {
            foreach ($service->field_default_checklist_items->getValue() as $dci) {
                //var_dump($dci);exit();
                $ci = Paragraph::create([
                    'type' => 'checklist_item',
                    'field_task_description' => $dci['value'],
                ]);
                $ci->save();
                $retval[] = $ci;
            }
        }

        return $retval;
    }

}