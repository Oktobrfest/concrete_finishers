<?php

namespace Drupal\concrete_finishers\Controller;

use Composer\DependencyResolver\Request;
use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Serialization\Json;
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
 * Class Inventory Controller
 *
 * @package Drupal\concrete_finishers\Controller
 */
class InventoryController extends ControllerBase
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
            '#markup' => $this->t('Hello, World!'),
        ];
    }


    /**
     *
     */
    public function getMaterials()
    {
        try {
            $nodes = $this->entityTypeManager->getStorage('commerce_product_variation')
                ->loadMultiple();
        } catch (InvalidPluginDefinitionException $e) {
            \Drupal::logger('InventoryController')->error($e->getMessage());
            drupal_set_message($e->getMessage(), 'error');

        }

        $retval = [];
        /** @var \Drupal\Core\Entity\Entity $node */
        foreach ($nodes as $node) {
            $retval[] = $node->toArray();
        }

        return new JsonResponse($retval);
    }

    /**
     *
     */
    public function getMaterialDetails($entity_id)
    {
        try {
            $node = $this->entityTypeManager->getStorage('commerce_product_variation')
                ->load($entity_id)->toArray();
        } catch (InvalidPluginDefinitionException $e) {
            \Drupal::logger('InventoryController')->error($e->getMessage());
            drupal_set_message($e->getMessage(), 'error');
        }

        return new JsonResponse($node);
    }

    /**
     *
     */
    public function getMachines()
    {
        try {
            $nodes = \Drupal::entityTypeManager()
                ->getStorage('commerce_product_variation')->loadMultiple();
        } catch (\Exception $e) {
            \Drupal::logger('InventoryController')->error($e->getMessage());
            drupal_set_message($e->getMessage(), 'error');
        }

        $retval = [];
        /** @var \Drupal\Core\Entity\Entity $node */
        foreach ($nodes as $node) {
            //$a = $node->referencedEntities();
            $retval[] = $node->toArray();
        }

        return new JsonResponse($retval);
    }

    /**
     *
     */
    public function getMachineDetails($entity_id)
    {
        try {
            $node = $this->entityTypeManager->getStorage('commerce_product_variation')
                ->load($entity_id)->toArray();
        } catch (\Exception $e) {
            \Drupal::logger('InventoryController')->error($e->getMessage());
            drupal_set_message($e->getMessage(), 'error');
        }

        return new JsonResponse($node);
    }

    /**
     *
     */
    public function getServices()
    {
        try {
            $nodes = $this->entityTypeManager->getStorage('taxonomy_term')
                ->loadTree('services', 0, NULL, true);
        } catch (InvalidPluginDefinitionException $e) {
            \Drupal::logger('InventoryController')->error($e->getMessage());
            drupal_set_message($e->getMessage(), 'error');
        }
        /** @var \Drupal\taxonomy\Entity\Term $m */
        try {
            $m = $this->entityTypeManager->getStorage('taxonomy_term')
                ->load();
        } catch (InvalidPluginDefinitionException $e) {
            \Drupal::logger('InventoryController')->error($e->getMessage());
            drupal_set_message($e->getMessage(), 'error');
        }
        
        $retval = [];
        /** @var \Drupal\Core\Entity\Entity $node */
        foreach ($nodes as $node) {
            $p = $m->loadParents($node->id());
            $node = $node->toArray();
            $node['parent'] = array_keys($p);
            $retval[] = $node;
        }

        return new JsonResponse($retval);
    }

    /**
     *
     */
    public function getServiceDetails($entity_id)
    {
        try {
            $node = $this->entityTypeManager->getStorage('taxonomy_term')
                ->load($entity_id)->toArray();
        } catch (InvalidPluginDefinitionException $e) {
            \Drupal::logger('InventoryController')->error($e->getMessage());
            drupal_set_message($e->getMessage(), 'error');

        }

        return new JsonResponse($node);
    }

    public function updateClientInventory()
    {
    }

    /**
     *
     */
    public function getClientList()
    {
        $nodes = $this->entityTypeManager->getStorage('node')
            ->loadByProperties(['type' => 'new_client_form']);

        $retval = [];
        /** @var \Drupal\Core\Entity\Entity $node */
        foreach ($nodes as $node) {
            $retval[] = $node->toArray();
        }

        return new JsonResponse($retval);
    }

    /**
     *
     */
    public function getClientDetails($entity_id)
    {
        $node = $this->entityTypeManager->getStorage('node')
            ->load($entity_id)->toArray();

        return new JsonResponse($node);
    }
}