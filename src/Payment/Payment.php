<?php

namespace Drupal\concrete_finishers\Payment;

use Drupal\concrete_finishers\Plugin\Event\InvoicePaidEvent;
use Drupal\node\Entity\Node;
use Drupal\paragraphs\Entity\Paragraph;
use net\authorize\api\contract\v1 as AnetAPI;
use net\authorize\api\controller as AnetController;
use Drupal\Core\Language\LanguageInterface;

class Payment
{

    const RESPONSE_OK = "Ok";

    const STATUS_OK = 200;

    const STATUS_FAIL = 500;

    const CC_PROCESSING_FEE = 0.03;

    const PAYMENT_CC = 'CC';

    const PAYMENT_BANK = 'BANK';

    protected $invoice_id;

    protected $invoice;

    protected $userPayment;

    /** @var \net\authorize\api\contract\v1\CreateTransactionResponse  */
    protected $captureResponse;

    protected $merchAuth;

    protected $discount;

    protected $paymentMethod;

    public function __construct($invoice_id, $userPayment)
    {
        $this->invoice_id = $invoice_id;
        $this->userPayment = $userPayment;

        $settings = \Drupal::config('concrete_finishers.settings');

        $this->merchAuth = new AnetAPI\MerchantAuthenticationType();
        $this->merchAuth->setName('66NgtS4Cp34');
        $this->merchAuth->setTransactionKey('2tp79BR5KLd92tC2');
    }


    /**
     * Get the invoice
     *
     * @return \Drupal\Core\Entity\EntityInterface|null
     */
    public function getInvoice()
    {
        try {
            if (!$this->invoice) {
                $this->invoice = \Drupal::entityTypeManager()
                    ->getStorage('node')
                    ->load($this->invoice_id);
            }
        } catch (\Exception $e) {
            \Drupal::logger('Payment')->error($e->getMessage());
            drupal_set_message($e->getMessage(), 'error');
        }

        return $this->invoice;
    }


    /**
     * Run an Auth Capture transaction on a credit card.
     * This charges the amount to the card
     *
     * @return array
     */
    public function chargeCreditCard()
    {
        $this->paymentMethod = self::PAYMENT_CC;
        // Set the transaction's refId
        $refId = 'ref' . time();

        // Create the payment data for a credit card
        $creditCard = new AnetAPI\CreditCardType();
        $creditCard->setCardNumber($this->userPayment['cc-num']);
        $creditCard->setExpirationDate($this->userPayment['exp-date']);
        $creditCard->setCardCode($this->userPayment['ccv-num']);

        // Add the payment data to a paymentType object
        $paymentOne = new AnetAPI\PaymentType();
        $paymentOne->setCreditCard($creditCard);
        $customerAddress = new AnetAPI\CustomerAddressType();
        $customerAddress->setZip($this->userPayment['zip']);
        $customerAddress->setCountry("USA");

        $order = new AnetAPI\OrderType();
        $order->setInvoiceNumber($this->getInvoice()->field_invoice_number->value);
        $order->setDescription($this->getInvoice()->title->value);

        // Add values for transaction settings
        /*$duplicateWindowSetting = new AnetAPI\SettingType();
        $duplicateWindowSetting->setSettingName("duplicateWindow");
        $duplicateWindowSetting->setSettingValue("60");*/

        $amount = $this->getInvoiceTotal();
        $this->discount = 0;

        // Create a TransactionRequestType object and add the previous objects to it
        $trt = new AnetAPI\TransactionRequestType();
        $trt->setTransactionType("authCaptureTransaction");
        $trt->setAmount($amount);
        $trt->setOrder($order);
        $trt->setPayment($paymentOne);
        $trt->setBillTo($customerAddress);
        //$transactionRequestType->setCustomer($customerData);
        //$trt->addToTransactionSettings($duplicateWindowSetting);

        // Assemble the complete transaction request
        $request = new AnetAPI\CreateTransactionRequest();
        $request->setMerchantAuthentication($this->merchAuth);
        $request->setRefId($refId);
        $request->setTransactionRequest($trt);

        // Create the controller and get the response
        $controller = new AnetController\CreateTransactionController($request);
        $this->captureResponse = $controller->executeWithApiResponse(\net\authorize\api\constants\ANetEnvironment::PRODUCTION);

        return $this->handleCaptureRespones();
    }


    /**
     * Run an auth capture transaction on a bank account
     *
     * @return array
     */
    public function debitBankAccount()
    {
        $this->paymentMethod = self::PAYMENT_BANK;
        $refId = 'ref' . time();

        // Create the payment data for a Bank Account
        $bankAccount = new AnetAPI\BankAccountType();
        $bankAccount->setAccountType('checking');
        $bankAccount->setEcheckType('WEB');
        $bankAccount->setRoutingNumber($this->userPayment['routing-num']);
        $bankAccount->setAccountNumber($this->userPayment['account-num']);
        $bankAccount->setNameOnAccount($this->userPayment['account-name']);
        $bankAccount->setBankName($this->userPayment['bank-name']);

        $paymentBank = new AnetAPI\PaymentType();
        $paymentBank->setBankAccount($bankAccount);

        $order = new AnetAPI\OrderType();
        $order->setInvoiceNumber($this->getInvoice()->field_invoice_number->value);
        $order->setDescription($this->getInvoice()->title->value);

        //create a bank debit transaction

        $trt = new AnetAPI\TransactionRequestType();
        $trt->setTransactionType("authCaptureTransaction");
        $trt->setAmount($this->getInvoiceTotal());
        $trt->setPayment($paymentBank);
        $trt->setOrder($order);
        $request = new AnetAPI\CreateTransactionRequest();
        $request->setMerchantAuthentication($this->merchAuth);
        $request->setRefId($refId);
        $request->setTransactionRequest($trt);
        $controller = new AnetController\CreateTransactionController($request);
        $this->captureResponse = $controller->executeWithApiResponse(\net\authorize\api\constants\ANetEnvironment::PRODUCTION);

        return $this->handleCaptureRespones($controller);
    }


    /**
     * Update the invoice after the payment is successful
     *
     * @param $tr
     *
     * @return mixed
     */
    protected function updateInvoice($tr)
    {
        $dt = new \DateTime('now');
        $this->getInvoice()->field_processing_fee->setValue($this->discount);
        $this->getInvoice()->field_total_cost->setValue($this->getInvoice()->field_total_cost->value - $this->discount);
        $this->getInvoice()->field_transaction_auth_code->setValue($tr->getAuthCode());
        $this->getInvoice()->field_transaction_id->setValue($tr->getTransId());
        $this->getInvoice()->field_date_paid->setValue($dt->format("Y-m-d\Th:i:s"));

        return $this->invoice->save();
    }

    /**
     * Email a receipt to the customer
     *
     * @param $tr \net\authorize\api\contract\v1\TransactionResponseType
     *
     * @return array
     */
    protected function finalizeTransaction($tr)
    {
        $this->updateInvoice($tr);
        $retval = [
            'status' => self::STATUS_OK,
            'transactionId' => $tr->getTransId(),
            'authCode' => $tr->getAuthCode(),
        ];

        try {
            $proposal = \Drupal::entityTypeManager()
                ->getStorage('node')
                ->load($this->invoice->get('field_proposal_reference')
                    ->getValue(0)[0]['target_id']);
            $client = \Drupal::entityTypeManager()->getStorage('node')
                ->load($proposal->get('field_client_reference')
                    ->getValue(0)[0]['target_id']);
        } catch (\Exception $e) {
            \Drupal::logger('Payment')->error($e->getMessage());
            drupal_set_message($e->getMessage(), 'error');
        }

        $event = new InvoicePaidEvent($client, ['client' => $client]);
        $event_dispatcher = \Drupal::service('event_dispatcher');
        $event_dispatcher->dispatch(InvoicePaidEvent::EVENT_NAME, $event);
        $module = 'concrete_finishers';
        $key = 'client_invoice_receipt';

        $to = $client->field_email->value;
        $from = \Drupal::config('system.site')->get('mail');

        $send_now = true;

        $iv = \Drupal::entityTypeManager()->getViewBuilder('node');
        $ra = $iv->view($this->getInvoice(), 'teaser');
        /** @var \Drupal\Core\Render\Markup $html */
        $html = \Drupal::service('renderer')->renderRoot($ra);
        $html = str_replace(
            'href="/', 'href="https://' . \Drupal::request()->getHost() . '/',
            $html->__toString());

        $result = \Drupal::service('plugin.manager.mail')
            ->mail($module, $key, $to,
                LanguageInterface::LANGCODE_SYSTEM,
                $html, $from, $send_now);

        return $retval;
    }

    /**
     * Handle the response from the payment gateway
     *
     * @return array
     */
    protected function handleCaptureRespones($controller)
    {
        $retval = [];
        if ($this->captureResponse != null) {

            if ($this->captureResponse->getMessages()->getResultCode() == self::RESPONSE_OK) {
                $tr = $this->captureResponse->getTransactionResponse();
                if ($tr != null && $tr->getMessages() != null) {
                    $retval = $this->finalizeTransaction($tr);
                } else {
                    $retval['status'] = self::STATUS_FAIL;
                    $retval['errorCode'] = $tr->getErrors()[0]->getErrorCode();
                    $retval['errorText'] = $tr->getErrors()[0]->getErrorText();
                    $retval['addl'] = print_r($tr->getErrors(), true);
                    \Drupal::logger('payment')->error(
                        print_r($this->captureResponse->getMessages(), true)
                    );
                }
                // Or, print errors if the API request wasn't successful
            } else {
                $tr = $this->captureResponse->getTransactionResponse();

                $retval['status'] = self::STATUS_FAIL;
                $retval['errorCode'] = $this->captureResponse->getMessages()
                    ->getResultCode();
                $retval['errorText'] = 'There was an error connecting to the payment gateway';
                $retval['addl'] = print_r($controller, true);
                \Drupal::logger('payment')->error(
                    print_r($this->captureResponse->getMessages(), true)
                );
            }
        } else {
            $retval['status'] = self::STATUS_FAIL;
            $retval['errorCode'] = self::STATUS_FAIL;
            $retval['errorText'] = "No response from merchant.  Try again later.";
            $retval['addl'] = print_r($controller, true);
            \Drupal::logger('payment')->error(
                print_r($this->captureResponse->getMessages(), true)
            );
        }
        $retval['response'] = print_r($this->captureResponse->getMessages(),
            true);

        return $retval;
    }


    /**
     * Create an invoice from a proposal
     *
     * @param $proposal_id
     *
     * @return \Drupal\Core\Entity\EntityInterface|static
     */
    public static function createInvoice($proposal_id)
    {
        $proposal = \Drupal::entityTypeManager()
            ->getStorage('node')
            ->load($proposal_id);

        $dt = new \DateTime('now');
        $invoice = Node::create([
            'type' => 'invoice',
            'title' => 'Invoice for ' . $proposal->title->value,
            'field_proposal_reference' => [
                $proposal_id,
            ],
            'field_invoice_number' => time() . "-" . $proposal_id,
            'field_total_cost' => $proposal->field_total_cost->value,
            'field_invoice_create_date' => $dt->format("Y-m-d"),
            'field_invoice_due_date' => $dt->modify('+30 days')
                ->format('Y-m-d'),
            'field_processing_fee' => $proposal->field_processing_fee->value,
            'field_service_items' => self::createSimpleLineItems($proposal),
        ]);

        $invoice->save();

        return $invoice;
    }

    /**
     * Convert the line items of the proposal to simple
     * line items for the invoice
     *
     * @param $proposal
     *
     * @return array
     */
    protected static function createSimpleLineItems($proposal)
    {
        $retval = [];

        foreach ($proposal->field_line_item->getValue() as $li) {
            $p = \Drupal::entityTypeManager()
                ->getStorage('paragraph')
                ->load($li['target_id']);
            if ($p->field_check_to_include->value) {
                $sli = Paragraph::create([
                    'type' => 'simplified_line_item',
                    'field_item_total' => $p->field_item_total->value,
                    'field_service_description' => $p->field_service->value
                        . " - " . $p->field_service_description->value,
                ]);
                $sli->save();
                $retval[] = $sli;
                foreach ($p->field_line_item_options->getValue() as $lio) {
                    $po = \Drupal::entityTypeManager()
                        ->getStorage('paragraph')
                        ->load($lio['target_id']);
                    if ($po->field_check_to_include->value) {
                        $sli = Paragraph::create([
                            'type' => 'simplified_line_item',
                            'field_item_total' => $po->field_option_price->value,
                            'field_service_description' => $po->field_service->value
                                . " - " . $po->field_service_description->value,
                        ]);
                        $sli->save();
                        $retval[] = $sli;
                    }
                }
            }
        }

        foreach ($proposal->field_additional_options->getValue() as $lio) {
            $po = \Drupal::entityTypeManager()
                ->getStorage('paragraph')
                ->load($lio['target_id']);
            if ($po->field_check_to_include->value) {
                $sli = Paragraph::create([
                    'type' => 'simplified_line_item',
                    'field_item_total' => $po->field_option_price->value,
                    'field_service_description' => $po->field_service->value
                        . " - " . $po->field_service_description->value,
                ]);
                $sli->save();
                $retval[] = $sli;
            }
        }

        //var_dump($retval);exit();

        return $retval;
    }


    protected function getInvoiceTotal()
    {
        $subtotal = 0;

        try {
            foreach ($this->getInvoice()->field_service_items->getValue() as $li) {
                $p = \Drupal::entityTypeManager()
                    ->getStorage('paragraph')
                    ->load($li['target_id']);
                $subtotal += $p->field_item_total->value;
            }
            foreach ($this->getInvoice()->field_adjustments->getValue() as $li) {
                $p = \Drupal::entityTypeManager()
                    ->getStorage('paragraph')
                    ->load($li['target_id']);
                $subtotal += $p->field_item_total->value;
            }

            $total = $subtotal;
            if ($this->paymentMethod === self::PAYMENT_BANK && !empty($this->invoice->field_processing_fee->getValue())) {
                $total = $total * (1 - self::CC_PROCESSING_FEE);
            }
            $this->getInvoice()->field_total_cost->setValue($total);
            $this->getInvoice()->save();
        } catch (\Exception $e) {
            \Drupal::logger('Payment')->error($e->getMessage());
            drupal_set_message($e->getMessage(), 'error');
        }

        return $total;
    }

}