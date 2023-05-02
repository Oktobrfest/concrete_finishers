<?php

namespace Drupal\concrete_finishers\Controller;

use Composer\DependencyResolver\Request;
use Drupal\Component\Serialization\Json;
use Drupal\concrete_finishers\Ajax\ScrollToElementCommand;
use Drupal\concrete_finishers\Client\Client;
use Drupal\concrete_finishers\Client\Docusign;
use Drupal\concrete_finishers\Form\ChecklistButtonForm;
use Drupal\concrete_finishers\Payment\Payment;
use Drupal\concrete_finishers\Plugin\Event\ContractSignedEvent;
use Drupal\concrete_finishers\Plugin\Event\InvoiceSentEvent;
use Drupal\concrete_finishers\Plugin\Event\ProposalSentEvent;
use Drupal\concrete_finishers\Plugin\Event\ServiceFinishedEvent;
use Drupal\concrete_finishers\Plugin\Event\ServiceScheduledEvent;
use Drupal\concrete_finishers\Plugin\RulesAction\ContractSignedAction;
use Drupal\concrete_finishers\Task\TaskContracted;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\CloseModalDialogCommand;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Ajax\OpenModalDialogCommand;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Form\FormBuilder;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\views\Ajax\ScrollTopCommand;
use Masterminds\HTML5\Exception;
use Symfony\Component\HttpFoundation\JsonResponse as JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Drupal\entity_print\Plugin\EntityPrintPluginManagerInterface;
use Drupal\entity_print\Plugin\ExportTypeManagerInterface;
use Drupal\entity_print\PrintBuilderInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use \Drupal\node\Entity\Node;
use Symfony\Component\HttpFoundation\RequestStack;
use Drupal\Core\Mail\MailManager;
use Drupal\Core\Language as Language;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Class ChecklistController
 *
 * @package Drupal\concrete_finishers\Controller
 */
class ChecklistController extends ControllerBase
{

    /**
     * The plugin manager for our Print engines.
     *
     * @var \Drupal\entity_print\Plugin\EntityPrintPluginManagerInterface
     */
    protected $pluginManager;

    /**
     * The export type manager.
     *
     * @var \Drupal\entity_print\Plugin\ExportTypeManagerInterface
     */
    protected $exportTypeManager;

    /**
     * The Print builder.
     *
     * @var \Drupal\entity_print\PrintBuilderInterface
     */
    protected $printBuilder;

    /**
     * The Entity Type manager.
     *
     * @var \Drupal\Core\Entity\EntityTypeManagerInterface
     */
    protected $entityTypeManager;


    /**
     * @var \Symfony\Component\HttpFoundation\RequestStack
     */
    protected $request;

    /**
     * The mail manager.
     *
     * @var \Drupal\Core\Mail\MailManagerInterface
     */
    protected $mailManager;

    /**
     * @var \Drupal\Core\Form\FormBuilder
     */
    protected $formBuilder;


    /**
     * {@inheritdoc}
     */
    public function __construct(
        EntityPrintPluginManagerInterface $plugin_manager,
        ExportTypeManagerInterface $export_type_manager,
        PrintBuilderInterface $print_builder,
        EntityTypeManagerInterface $entity_type_manager,
        RequestStack $request,
        MailManager $mm,
        FormBuilder $formBuilder
    ) {
        $this->pluginManager = $plugin_manager;
        $this->exportTypeManager = $export_type_manager;
        $this->printBuilder = $print_builder;
        $this->entityTypeManager = $entity_type_manager;
        $this->request = $request;
        $this->mailManager = $mm;
        $this->formBuilder = $formBuilder;
    }

    /**
     * {@inheritdoc}
     */
    public static function create(ContainerInterface $container)
    {
        return new static(
            $container->get('plugin.manager.entity_print.print_engine'),
            $container->get('plugin.manager.entity_print.export_type'),
            $container->get('entity_print.print_builder'),
            $container->get('entity_type.manager'),
            $container->get('request_stack'),
            $container->get('plugin.manager.mail'),
            $container->get('form_builder')
        );
    }

    /**
     * Display the markup.
     *
     * @return array
     */
    public function content()
    {
        return [
            '#type' => 'markup',
            '#markup' => $this->t('Welcome!'),
        ];
    }

    /**
     * @param $entity_id
     *
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
     * @throws \Drupal\Core\Entity\EntityMalformedException
     */
    public function createNewClientChecklist($entity_id)
    {
        try {
            $client = new Client($entity_id);
            $client->createChecklist();

            return new RedirectResponse($client->getClient()
                ->toUrl()
                ->toString());
        } catch (Exception $e) {
            \Drupal::logger('task')->error($e->getMessage());
            drupal_set_message($e->getMessage(), 'error');
        }
    }

    /**
     * @param $entity_id
     *
     * @return \Drupal\Core\Ajax\AjaxResponse
     */
    public function submitNotesForm($entity_id)
    {
        $res = new AjaxResponse();
        $res->addCommand(new CloseModalDialogCommand(false));
        $res->addCommand(new ScrollToElementCommand('.checklist-' . $entity_id));
        return $res;
    }

    /**
     * @param $entity_id
     *
     * @return \Drupal\Core\Ajax\AjaxResponse
     */
    public function submitCompleteForm($entity_id)
    {
        $dt = new \DateTime('now');
        $name = "";

        try {
            $cli = \Drupal::entityTypeManager()
                ->getStorage('paragraph')->load($entity_id);
            $cli->get('field_date_completed')
                ->setValue($dt->format("Y-m-d\Th:i:s"));
            $cli->get('field_performed_by')
                ->setValue(\Drupal::currentUser()->id());
            $cli->get('field_recorded_by')
                ->setValue(\Drupal::currentUser()->id());
            $cli->save();

            $user = \Drupal\user\Entity\User::load(\Drupal::currentUser()->id());
            $name = $user->getUsername();
        } catch (\Exception $e) {
            \Drupal::logger('checklist')->error($e->getMessage());
            drupal_set_message($e->getMessage(), 'error');
        }

        $res = new AjaxResponse();
        $res->addCommand(
            new HtmlCommand('.checklist-' . $entity_id . ' .views-field-field-performed-by',
                $name));

        $res->addCommand(
            new HtmlCommand('.checklist-' . $entity_id . ' .views-field-checklist-complete-field',
                $dt->format('m-d-Y h:iA T')));

        $res->addCommand(new ScrollToElementCommand('.checklist-' . $entity_id));
        return $res;
    }

    /**
     * @param $entity_id
     *
     * @return \Drupal\Core\Ajax\AjaxResponse
     * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
     */
    public function getNotesForm($entity_id)
    {
        $checklistItem = $this->entityTypeManager->getStorage('paragraph')
            ->load($entity_id);

        $form = \Drupal::entityTypeManager()
            ->getFormObject('paragraph', 'checklist_notes_form')
            ->setEntity($checklistItem);

        //return $response;
        $form = \Drupal::formBuilder()->getForm($form);
        //$form['actions']['submit'] = [];

        array_unshift($form, [
            '#type' => 'markup',
            '#markup' => $this->t("<h4>Task Description: </h4>"
                . $checklistItem->field_task_description->value . "<br/><br/><br/>"
            ),
        ]);

        $response = new AjaxResponse();
        $response->addCommand(new OpenModalDialogCommand('Write Note',
            $form, []));
        return $response;
    }

   /**
    * @param $entity_id
    *
    * @return \Drupal\Core\Ajax\AjaxResponse
    * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
    */
   public function getCompleteForm($entity_id)
   {
       $checklistItem = $this->entityTypeManager->getStorage('paragraph')
           ->load($entity_id);
       if (!$checklistItem->field_date_completed->value) {
           $checklistItem->field_date_completed
               ->setValue((new \DateTime('now'))->format("Y-m-d\Th:i:s"));
       }
       if (!$checklistItem->field_recorded_by->value) {
           $checklistItem->field_recorded_by
               ->setValue(\Drupal::currentUser()->id());
       }
       $form = \Drupal::entityTypeManager()
           ->getFormObject('paragraph', 'checklist_form')
           ->setEntity($checklistItem);

       //return $response;
       $form = \Drupal::formBuilder()->getForm($form);
       array_unshift($form, [
           '#type' => 'markup',
           '#markup' => $this->t("<h4>Task Description: </h4>"
               . $checklistItem->field_task_description->value . "<br/><br/><br/>"
           ),
       ]);
       $response = new AjaxResponse();
       $response->addCommand(new OpenModalDialogCommand('Mark as Finished',
           $form, []));
       return $response;

   }
}