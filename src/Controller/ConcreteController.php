<?php

namespace Drupal\concrete_finishers\Controller;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Serialization\Json;
use Drupal\concrete_finishers\Client\Client;
use Drupal\concrete_finishers\Client\Docusign;
use Drupal\concrete_finishers\Payment\Payment;
use Drupal\concrete_finishers\Plugin\Event\ContractSignedEvent;
use Drupal\concrete_finishers\Plugin\Event\InvoiceSentEvent;
use Drupal\concrete_finishers\Plugin\Event\ProposalSentEvent;
use Drupal\concrete_finishers\Plugin\Event\ServiceFinishedEvent;
use Drupal\concrete_finishers\Plugin\Event\ServiceScheduledEvent;
use Drupal\concrete_finishers\Plugin\RulesAction\ContractSignedAction;
use Drupal\concrete_finishers\Task\TaskContracted;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Form\FormBuilder;
use Drupal\paragraphs\Entity\Paragraph;
use Masterminds\HTML5\Exception;
use Symfony\Component\HttpFoundation\JsonResponse as JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Drupal\entity_print\Plugin\EntityPrintPluginManagerInterface;
use Drupal\entity_print\Plugin\ExportTypeManagerInterface;
use Drupal\concrete_finishers\PrintBuilderInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use \Drupal\node\Entity\Node;
use Symfony\Component\HttpFoundation\RequestStack;
use Drupal\Core\Mail\MailManager;
use Drupal\Core\Language as Language;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ConcreteController extends ControllerBase
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
     * @var \Drupal\concrete_finishers\PrintBuilderInterface
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

    protected $formBuilder;


    /**
     * {@inheritdoc}
     */
    public function __construct(
        EntityPrintPluginManagerInterface $plugin_manager,
        ExportTypeManagerInterface $export_type_manager,
        \Drupal\concrete_finishers\PrintBuilderInterface $print_builder,
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
            $container->get('concrete_finishers.print_builder'),
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

    public function test($entity_id)
    {

        $entity = $this->entityTypeManager->getStorage('node')->load($entity_id);
        $use_default_css = $this->config('entity_print.settings')->get('default_css');
        $print = $this->printBuilder->printHtml($entity, $use_default_css, FALSE, $entity->bundle() . "_print");
        return new Response($print);

        $client = $this->entityTypeManager->getStorage('node')
          ->load($entity_id);
        $event = new ServiceScheduledEvent($client,
          ['client' => $client]);
        $event_dispatcher = \Drupal::service('event_dispatcher');
        $event_dispatcher->dispatch(ServiceScheduledEvent::EVENT_NAME,
          $event);*/

        $client = $this->entityTypeManager->getStorage('node')
            ->load($entity_id);
        $event = new ServiceFinishedEvent($client,
            ['client' => $client]);
        $event_dispatcher = \Drupal::service('event_dispatcher');
        $event_dispatcher->dispatch(ServiceFinishedEvent::EVENT_NAME,
            $event);

        return new JsonResponse($client->toArray());

        $invoice = $this->entityTypeManager->getStorage('node')
            ->load($entity_id);
        $module = 'concrete_finishers';
        $key = 'client_invoice_receipt';

        $to = 'xxxxxxx@gmail.com';
        $from = \Drupal::config('system.site')->get('mail');

        $send_now = true;

        $iv = \Drupal::entityTypeManager()->getViewBuilder('node');
        $ra = $iv->view($invoice, 'teaser');
        /** @var \Drupal\Core\Render\Markup $html */
        $html = \Drupal::service('renderer')->renderRoot($ra);
        $html = str_replace(
            'href="/', 'href="https://' . \Drupal::request()->getHost() . '/',
            $html->__toString());

        $result = \Drupal::service('plugin.manager.mail')
            ->mail($module, $key, $to,
                \Drupal\Core\Language\LanguageInterface::LANGCODE_SYSTEM,
                $html, $from, $send_now);
        return new JsonResponse($result);
    }

    /**
     * User submitting payment with credit card
     *
     * @param $invoice_id
     *
     * @return JsonResponse
     */
    public function payWithCreditCard($entity_id)
    {

        $post = $this->request->getCurrentRequest()->get('payment');
        $userPayment = [];
        foreach ($post as $param) {
            $userPayment[$param['name']] = $param['value'];
        }
        $userPayment['exp-date'] = $userPayment['month'] . $userPayment['year'];
        $payment = new Payment($entity_id, $userPayment);
        $transaction = $payment->chargeCreditCard();

        return new JsonResponse($transaction);
    }

    /**
     * User submitting payment with bank account
     *
     * @param $invoice_id
     *
     * @return JsonResponse
     */
    public function payWithBankAccount($entity_id)
    {
        $post = $this->request->getCurrentRequest()->get('payment');
        $userPayment = [];
        foreach ($post as $param) {
            $userPayment[$param['name']] = $param['value'];
        }
        $payment = new Payment($entity_id, $userPayment);
        $transaction = $payment->debitBankAccount();

        return new JsonResponse($transaction);
    }

    /**
     * Email the proposal to the client and update statuses
     *
     * @param $entity_id
     *
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function sendProposal($entity_id)
    {
        // All system mails need to specify the module and template key (mirrored
        // from hook_mail()) that the message they want to send comes from.
        $module = 'concrete_finishers';
        $key = 'client_proposal_notification';
        $entity = $this->entityTypeManager->getStorage('node')
            ->load($entity_id);

        $client = $this->entityTypeManager->getStorage('node')
            ->load($entity->get('field_client_reference')
                ->getValue(0)[0]['target_id']);

        $to = $client->field_email->value;
        $from = $this->config('system.site')->get('mail');

        $send_now = true;
        $url = Url::fromUri('base:' . \Drupal::service('path.alias_manager')
                ->getAliasByPath('/node/' . $entity->id()),
            ['absolute' => true]);
        $hash = hash('md5', $entity->id());
        $url->setOption('query', ['hash' => $hash]);

        $result = $this->mailManager->mail($module, $key, $to,
            Language\LanguageInterface::LANGCODE_SYSTEM,
            $url->toString(), $from, $send_now);
        if ($result['result'] == true) {
            drupal_set_message(t('Your message has been sent to @email.',
                ['@email' => $to]));
        } else {
            drupal_set_message(t('There was a problem sending your message and it was not sent.'),
                'error');
        }

        $event = new ProposalSentEvent($client, ['client' => $client]);
        $event_dispatcher = \Drupal::service('event_dispatcher');
        $event_dispatcher->dispatch(ProposalSentEvent::EVENT_NAME, $event);

        return new RedirectResponse($url->setOption('query', [])->toString());
    }

    /**
     * @param $entity_id
     *
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function sendInvoice($entity_id)
    {
        // All system mails need to specify the module and template key (mirrored
        // from hook_mail()) that the message they want to send comes from.
        $module = 'concrete_finishers';
        $key = 'client_invoice_notification';
        try {
            $invoice = $this->entityTypeManager
                ->getStorage('node')->load($entity_id);
            $proposal = $this->entityTypeManager
                ->getStorage('node')
                ->load($invoice->get('field_proposal_reference')
                    ->getValue(0)[0]['target_id']);
            $client = $this->entityTypeManager->getStorage('node')
                ->load($proposal->get('field_client_reference')
                    ->getValue(0)[0]['target_id']);
        } catch (InvalidPluginDefinitionException $e) {
            \Drupal::logger('ConcreteController')->error($e->getMessage());
            drupal_set_message($e->getMessage(), 'error');
        }


        $to = $client->field_email->value;
        $from = $this->config('system.site')->get('mail');

        $send_now = true;
        $url = Url::fromUri('base:' . \Drupal::service('path.alias_manager')
                ->getAliasByPath('/node/' . $invoice->id()),
            ['absolute' => true]);
        $hash = hash('md5', $invoice->id());
        $url->setOption('query', ['hash' => $hash]);

        $result = $this->mailManager->mail($module, $key, $to,
            Language\LanguageInterface::LANGCODE_SYSTEM,
            $url->toString(), $from, $send_now);
        if ($result['result'] == true) {
            drupal_set_message(t('Your message has been sent to @email.',
                ['@email' => $to]));
        } else {
            drupal_set_message(t('There was a problem sending your message and it was not sent.'),
                'error');
        }

        $event = new InvoiceSentEvent($client, ['client' => $client]);
        $event_dispatcher = \Drupal::service('event_dispatcher');
        $event_dispatcher->dispatch(InvoiceSentEvent::EVENT_NAME, $event);

        return new RedirectResponse($url->setOption('query', [])->toString());
    }

    /**
     * Update the proposal with the selected options from the client.  Not to be
     * confused with the regular node edit.
     *
     * @param $entity_id
     *
     * @return JsonResponse
     */
    public function updateProposal($entity_id)
    {
        $info = $this->request->getCurrentRequest()->get('info');
        $discount = $this->request->getCurrentRequest()->get('discount');

        $entity = $this->entityTypeManager
            ->getStorage('paragraph')->loadMultiple(array_keys($info));
        //watchdog_exception('Proposal', 'POST VARS', );
        $total = 0;
        $retval = [];
        foreach ($entity as $e) {
            $total += $info[$e->id()];
            $e->field_check_to_include = ($info[$e->id()]) ? 1 : 0;
            $e->save();
            $retval[] = $e->toArray();
        }

        $proposal = $this->entityTypeManager
            ->getStorage('node')->load($entity_id);
        $proposal->set('field_processing_fee', $discount);
        $proposal->set('field_total_cost', ($total - $discount));
        $proposal->save();
        $retval['total'] = ($total - $discount);
        //$retval['proposal'] = $proposal;

        return new JsonResponse($retval);
    }

    /**
     * @param $entity_id
     *
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function createInvoice($entity_id)
    {
        $request = \Drupal::request();
        $finish = $request->query->get('finish');
        $invoice = Payment::createInvoice($entity_id);

        if ($finish) {
            $client = $this->entityTypeManager->getStorage('node')
                ->load($finish);
            $event = new ServiceFinishedEvent($client, ['client' => $client]);
            $event_dispatcher = \Drupal::service('event_dispatcher');
            $event_dispatcher->dispatch(ServiceFinishedEvent::EVENT_NAME,
                $event);
        }

        $url = Url::fromUri(
            'base:' . \Drupal::service('path.alias_manager')
                ->getAliasByPath('/node/' . $invoice->id() . '/edit'),
            ['absolute' => true]);

        return new RedirectResponse($url->toString());

    }

    /**
     * @param $entity_id
     *
     * @return array
     */
    public function createProposal($entity_id)
    {
        $client = new Client($entity_id);
        $proposal = $client->createProposal();

        $form = \Drupal::entityTypeManager()
            ->getFormObject('node', 'default')
            ->setEntity($proposal);

        return $form = \Drupal::formBuilder()->getForm($form);
        $url = Url::fromUri(
            'base:' . \Drupal::service('path.alias_manager')
                ->getAliasByPath('/node/' . $proposal->id() . '/edit'),
            ['absolute' => true]);

        return new RedirectResponse($url->toString());

    }


    /**
     * Create the PDF and send it off to DocuSign for signature
     *
     * @param $entity_id
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function esignSign($entity_id)
    {
        $entity = $this->entityTypeManager->getStorage('node')
            ->load($entity_id);
        $ds = new Docusign($entity);
        $pdfUrl = $ds->savePDF(
            $this->pluginManager->createSelectedInstance('pdf'),
            $this->printBuilder
        );

        if ($pdfUrl) {
            if ($ds->login()) {
                $summary = $ds->createDocusignContract();

                if ($summary) {
                    return new JsonResponse([
                        'status' => 200,
                        'statusMessage' => 'Contract successfully created',
                        'pdfUrl' => $pdfUrl,
                        'summary' => $summary,
                    ]);
                }
            }
            return new JsonResponse([
                'status' => 500,
                'statusMessage' => 'There was an issue sending the proposal to docusign',
            ]);

        } else {
            return new JsonResponse([
                'status' => 500,
                'statusMessage' => 'There was an issue creating the document',
            ]);
        }

    }


    /**
     * Preview the proposal before agreeing and sending off to DocuSign
     *
     * @param $entity_id
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function esignPreview($entity_id)
    {
        $entity = $this->entityTypeManager->getStorage('node')
            ->load($entity_id);
        $use_default_css = true;
        return new Response($this->printBuilder->printHtml($entity,
            $use_default_css, false, $entity->bundle() . "_print"));
    }

    /**
     * A non-PDF printable version of the proposal
     *
     * @param $entity_id
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function esignPrint($entity_id)
    {
        /** @var \Drupal\Core\Entity\Entity $entity */
        try {
            $entity = $this->entityTypeManager->getStorage('node')
                ->load($entity_id);
        } catch (InvalidPluginDefinitionException $e) {
            \Drupal::logger('ConcreteController')->error($e->getMessage());
            drupal_set_message($e->getMessage(), 'error');
        }

        ini_set('memory_limit', '512M');
        ini_set('max_execution_time', '0');
        // Create the Print engine plugin.
        $config = $this->config('entity_print.settings');

        $print_engine = $this->pluginManager->createSelectedInstance('pdf');

        return (new StreamedResponse(function () use (
            $entity,
            $print_engine,
            $config,
            $entity
        ) {
            // The Print is sent straight to the browser.
            $this->printBuilder->deliverPrintable([$entity], $print_engine,
                $config->get('force_download'), true,
                $entity->bundle() . "_print");
        }))->send();
    }

    /**
     * Webhook for DocuSign to call when the PDF has been signed and completed
     *
     * @param $entity_id
     *
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
     */
    public function esignComplete($entity_id)
    {
        ini_set('memory_limit', '512M');
        ini_set('max_execution_time', '0');

        try {
            $data = file_get_contents('php://input');
            $xml = simplexml_load_string($data, "SimpleXMLElement",
                LIBXML_PARSEHUGE);
            if ((string)$xml->EnvelopeStatus->Status === "Completed") {
                // Loop through the DocumentPDFs element, storing each document.
                foreach ($xml->DocumentPDFs->DocumentPDF as $pdf) {
                    $filename = 'SIGNED-' . $entity_id . '.pdf';
                    $file = file_save_data(
                        base64_decode((string)$pdf->PDFBytes),
                        'public://signed-contracts/' . $filename,
                        FILE_EXISTS_REPLACE
                    );

                    $node = Node::load($entity_id);
                    $node->field_contract_pdf->setValue([
                        'target_id' => $file->id(),
                    ]);
                    $node->save();

                    $client = $this->entityTypeManager->getStorage('node')
                        ->load($node->get('field_client_reference')
                            ->getValue(0)[0]['target_id']);
                    $event = new ContractSignedEvent($client,
                        ['client' => $client]);
                    $event_dispatcher = \Drupal::service('event_dispatcher');
                    $event_dispatcher->dispatch(ContractSignedEvent::EVENT_NAME,
                        $event);
                }
            }
        } catch (Exception $e) {
            \Drupal::logger('docusign')->error($e->getMessage());
        }

        return new JsonResponse(['success']);
    }



}