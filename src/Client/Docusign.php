<?php

namespace Drupal\concrete_finishers\Client;

use Drupal\Component\Serialization\Json;
use DocuSign\eSign;

class Docusign
{

    var $config;

    var $apiClient;

    var $accountId;

    var $entity;

    var $pdfUrl;

    public function __construct($entity)
    {
        $this->entity = $entity;
        $this->config = new eSign\Configuration();
        $this->config->setHost('https://na2.docusign.net/restapi'); //na2 for production;

        $this->config->addDefaultHeader(
            "X-DocuSign-Authentication",
            "{\"Username\":\"" . 'xxx@xxxxxxxxx.com'//$settings->get('docusign.username')
            . "\",\"Password\":\"" . 'xxxx' //$settings->get('docusign.password')
            . "\",\"IntegratorKey\":\"" . 'xxxxxxxxxxxx' /*$settings->get('docusign.integrator_key')*/ . "\"}"
        );

        // instantiate a new docusign api client
        $this->apiClient = new eSign\ApiClient($this->config);
    }


    public function login()
    {
        try {
            //*** STEP 1 - Login API: get first Account ID and baseURL
            $authenticationApi = new eSign\Api\AuthenticationApi($this->apiClient);
            $options = new eSign\Api\AuthenticationApi\LoginOptions();
            $loginInformation = $authenticationApi->login($options);


            if (isset($loginInformation) && count($loginInformation) > 0) {
                $loginAccount = $loginInformation->getLoginAccounts()[0];
                $host = $loginAccount->getBaseUrl();
                $host = explode("/v2", $host);
                $host = $host[0];

                // UPDATE configuration object
                $this->config->setHost($host);

                // instantiate a NEW docusign api client (that has the correct baseUrl/host)
                $this->apiClient = new eSign\ApiClient($this->config);

                if (isset($loginInformation)) {
                    $this->accountId = $loginAccount->getAccountId();
                    return $this;
                } else {
                    return false;
                }
            }
        } catch (\Exception $e) {
            \Drupal::logger('docusign')->error($e->getCode() . $e->getMessage() . $e->getTraceAsString());
            return false;
        }
    }

    public function createDocusignContract()
    {
        if (empty($this->accountId)) {
            return false;
        }

        try {
            $envelopeApi = new eSign\Api\EnvelopesApi($this->apiClient);
            $envelope = $this->getEnvelope($this->entity, $this->pdfUrl);

            $summary = $envelopeApi->createEnvelope($this->accountId, $envelope,
                null);
            $summary = Json::decode($summary);

            $this->entity->field_docusign_envelope_id->setValue([
                'value' => $summary['envelopeId'],
            ]);
            $this->entity->save();
        } catch (\Exception $e) {
            \Drupal::logger('docusign')->error($e->getMessage());
            return false;
        }

        return $summary;
    }


    /**
     * Save a PDF of the contract
     *
     * @param \Drupal\Core\Entity\Entity $entity
     *   The entity of the proposal node
     *
     * @return \Symfony\Component\HttpFoundation\Response | bool
     *   The response object on error otherwise the Print is sent.
     */
    public function savePDF($engine, $builder)
    {
        try {
            ini_set('memory_limit', '512M');
            ini_set('max_execution_time', '0');

            $this->pdfUrl = file_create_url(
                $builder->savePrintable([$this->entity],
                    $engine,
                    'public',
                    'contracts/proposal-' . time() . '-' . $this->entity->id() . '.pdf',
                    true,
                    'estimate_proposal_print')
            );
        } catch (\Exception $e) {
            \Drupal::logger('docusign')->error($e->getMessage());
            return false;
        }
        return $this->pdfUrl;
    }

    /**
     * Get the envelope for DocuSign
     *
     * @param $entity
     * @param $pdfUri
     *
     * @return \DocuSign\eSign\Model\EnvelopeDefinition
     * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
     */
    protected function getEnvelope($entity, $pdfUri)
    {

        $client = \Drupal::entityTypeManager()
            ->getStorage('node')
            ->load($entity->get('field_client_reference')
                ->getValue(0)[0]['target_id']);

        $t = new eSign\Model\Tabs();
        $sh = new eSign\Model\SignHere();
        $sh->setAnchorString('Customer Signature:')
            ->setAnchorMatchWholeWord(false)
            ->setAnchorXOffset(2)
            ->setAnchorYOffset(0)
            ->setAnchorUnits('inches')
            ->setAnchorIgnoreIfNotPresent(false);
        $t->setSignHereTabs([$sh]);
        $e = new eSign\Model\EnvelopeDefinition();
        $e->setEmailSubject("Concrete Finishers Contract");
        $r = new eSign\Model\Recipients();
        $s = new eSign\Model\Signer();
        $s->setEmail($client->field_email->value)
            ->setName($client->field_contact_name->value)
            ->setRecipientId($client->id())
            ->setTabs($t);
        $r->setSigners([$s]);
        $e->setRecipients($r);
        $d = new eSign\Model\Document();
        $d->setDocumentId($entity->id())
            ->setName('Concrete Finishing Contract ' . $entity->id())
            ->setDocumentBase64(
                base64_encode(
                    file_get_contents($pdfUri)
                )
            )
            ->setFileExtension('.pdf');
        $e->setStatus('sent');
        $e->setDocuments([$d]);
        $e->setEventNotification($this->getEventNotification($entity));

        return $e;
    }


    /**
     * Set up the events so that DocuSign knows how to update
     * us on the progress
     *
     * @param $entity
     *
     * @return \DocuSign\eSign\Model\EventNotification
     */
    protected function getEventNotification($entity)
    {
        $url = \Drupal\Core\Url::fromRoute('concrete_finishers.esignComplete',
            ['entity_id' => $entity->id()],
            ['absolute' => true]
        );
        \Drupal::logger('docusign')
            ->info('Complete URL for ' . $entity->id() . ": " . $url->toString());
        $envelope_events = [
            (new eSign\Model\EnvelopeEvent())->setEnvelopeEventStatusCode("completed"),
        ];

        $event_notification = new eSign\Model\EventNotification();
        $event_notification->setUrl($url->toString());
        $event_notification->setLoggingEnabled("true");
        $event_notification->setRequireAcknowledgment("true");
        $event_notification->setUseSoapInterface("false");
        $event_notification->setIncludeCertificateWithSoap("false");
        $event_notification->setSignMessageWithX509Cert("false");
        $event_notification->setIncludeDocuments("true");
        $event_notification->setIncludeEnvelopeVoidReason("true");
        $event_notification->setIncludeTimeZone("true");
        $event_notification->setIncludeCertificateOfCompletion("true");
        $event_notification->setEnvelopeEvents($envelope_events);

        return $event_notification;
    }
}