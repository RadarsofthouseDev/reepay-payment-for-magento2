<?php

namespace Radarsofthouse\Reepay\Controller\Webhooks;

/**
 * Class Index
 *
 * @package Radarsofthouse\Reepay\Controller\Webhooks
 */

class Index extends \Magento\Framework\App\Action\Action
{
    protected $resultPageFactory;
    protected $jsonHelper;
    protected $logger;
    protected $invoiceHelper;
    protected $order;
    protected $orderService;
    protected $orderInterface;
    protected $transaction;
    protected $invoiceService;
    protected $reepayHelper;
    protected $invoice;
    protected $creditmemoFactory;
    protected $creditmemoService;
    protected $resultJsonFactory;
    protected $transactionSearchResultInterfaceFactory;
    protected $reepayStatus;
    protected $reepayCharge;
    protected $reepaySurchargeFee;
    protected $reepayEmail;

    /**
     * Index constructor
     *
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Magento\Framework\View\Result\PageFactory $resultPageFactory
     * @param \Radarsofthouse\Reepay\Helper\Logger $logger
     * @param \Radarsofthouse\Reepay\Helper\Invoice $invoiceHelper
     * @param \Magento\Sales\Model\Order $order
     * @param \Magento\Sales\Model\Service\OrderService $orderService
     * @param \Magento\Sales\Api\Data\OrderInterface $orderInterface
     * @param \Magento\Framework\DB\Transaction $transaction
     * @param \Magento\Sales\Model\Service\InvoiceService $invoiceService
     * @param \Radarsofthouse\Reepay\Helper\Data $reepayHelper
     * @param \Magento\Sales\Model\Order\CreditmemoFactory $creditmemoFactory
     * @param \Magento\Sales\Model\Order\Invoice $invoice
     * @param \Magento\Sales\Model\Service\CreditmemoService $creditmemoService
     * @param \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory
     * @param \Magento\Sales\Api\Data\TransactionSearchResultInterfaceFactory $transactionSearchResultInterfaceFactory
     * @param \Radarsofthouse\Reepay\Model\Status $reepayStatus
     * @param \Radarsofthouse\Reepay\Helper\Charge $reepayCharge
     * @param \Radarsofthouse\Reepay\Helper\SurchargeFee $reepaySurchargeFee
     * @param \Radarsofthouse\Reepay\Helper\Email $reepayEmail
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Framework\View\Result\PageFactory $resultPageFactory,
        \Radarsofthouse\Reepay\Helper\Logger $logger,
        \Radarsofthouse\Reepay\Helper\Invoice $invoiceHelper,
        \Magento\Sales\Model\Order $order,
        \Magento\Sales\Model\Service\OrderService $orderService,
        \Magento\Sales\Api\Data\OrderInterface $orderInterface,
        \Magento\Framework\DB\Transaction $transaction,
        \Magento\Sales\Model\Service\InvoiceService $invoiceService,
        \Radarsofthouse\Reepay\Helper\Data $reepayHelper,
        \Magento\Sales\Model\Order\CreditmemoFactory $creditmemoFactory,
        \Magento\Sales\Model\Order\Invoice $invoice,
        \Magento\Sales\Model\Service\CreditmemoService $creditmemoService,
        \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory,
        \Magento\Sales\Api\Data\TransactionSearchResultInterfaceFactory $transactionSearchResultInterfaceFactory,
        \Radarsofthouse\Reepay\Model\Status $reepayStatus,
        \Radarsofthouse\Reepay\Helper\Charge $reepayCharge,
        \Radarsofthouse\Reepay\Helper\SurchargeFee $reepaySurchargeFee,
        \Radarsofthouse\Reepay\Helper\Email $reepayEmail
    ) {
        $this->resultPageFactory = $resultPageFactory;
        $this->logger = $logger;
        $this->invoiceHelper = $invoiceHelper;
        $this->order = $order;
        $this->orderService = $orderService;
        $this->orderInterface = $orderInterface;
        $this->transaction = $transaction;
        $this->invoiceService = $invoiceService;
        $this->reepayHelper = $reepayHelper;
        $this->invoice = $invoice;
        $this->creditmemoFactory = $creditmemoFactory;
        $this->creditmemoService = $creditmemoService;
        $this->resultJsonFactory = $resultJsonFactory;
        $this->transactionSearchResultInterfaceFactory = $transactionSearchResultInterfaceFactory;
        $this->reepayStatus = $reepayStatus;
        $this->reepayCharge = $reepayCharge;
        $this->reepaySurchargeFee = $reepaySurchargeFee;
        $this->reepayEmail = $reepayEmail;
        parent::__construct($context);

        // CsrfAwareAction Magento2.3 compatibility
        if (interface_exists("\Magento\Framework\App\CsrfAwareActionInterface")) {
            $request = $this->getRequest();

            if ($request->isPost() && empty($request->getParam('form_key'))) {
                $formKey = $this->_objectManager->get(\Magento\Framework\Data\Form\FormKey::class);
                $request->setParam('form_key', $formKey->getFormKey());
            }
        }
    }

    /**
     * @return \Magento\Framework\App\ResponseInterface|\Magento\Framework\Controller\ResultInterface
     * @throws \Exception
     */
    public function execute()
    {
        $request = $this->getRequest()->getContent();
        $receiveData = json_decode($request, true);

        $this->logger->addDebug(__METHOD__, $receiveData);

        try {
            if (!array_key_exists('event_type', $receiveData)) {
                throw new \Exception('This request event_type not found.', 404);
            }
            switch ($receiveData['event_type']) {
                case 'invoice_refund':
                    if (array_key_exists('subscription', $receiveData)) {
                        $response['status'] = 200;
                        $response['message'] = 'This request is not charge invoice.';
                    } else {
                        $response = $this->refund($receiveData);
                    }
                    $log['response'] = $response;

                    $this->logger->addDebug('Refund response', $log);

                    break;
                case 'invoice_settled':
                    if (array_key_exists('subscription', $receiveData)) {
                        $response['status'] = 200;
                        $response['message'] = 'This request is not charge invoice.';
                    } else {
                        $response = $this->settled($receiveData);
                    }
                    $log['response'] = $response;

                    $this->logger->addDebug('Settled response', $log);

                    break;
                case 'invoice_cancelled':
                    if (array_key_exists('subscription', $receiveData)) {
                        $response['status'] = 200;
                        $response['message'] = 'This request is not charge invoice.';
                    } else {
                        $response = $this->cancel($receiveData);
                    }
                    $log['response'] = $response;

                    $this->logger->addDebug('Cancel response', $log);

                    break;
                case 'invoice_authorized':
                    if (array_key_exists('subscription', $receiveData)) {
                        $response['status'] = 200;
                        $response['message'] = 'This request is not charge invoice.';
                    } else {
                        $response = $this->authorize($receiveData);
                    }
                    $log['response'] = $response;

                    $this->logger->addDebug('Authorized response', $log);

                    break;
                default:
                    $response['status'] = 200;
                    $response['message'] = 'The ' . $receiveData['event_type'] . ' event has been ignored by Magento.';
                    $log['response'] = $response;
                    $this->logger->addDebug('default', $log);

                    break;
            }

            $response['message'] = 'Magento : ' . $response['message'];
            $result = $this->resultJsonFactory->create();
            $result->setHttpResponseCode($response['status']);
            $result->setData($response);

            return  $result;
        } catch (\Magento\Framework\Exception\LocalizedException $e) {
            $log['response_error'] = [
                'error_code' => $e->getCode(),
                'message' => $e->getMessage(),
                'trace' => $e->getTrace(),
            ];
            $this->logger->addError(__METHOD__, $log);

            throw new \Exception($e->getMessage(), (!empty($e->getCode()) ? (int)$e->getCode() : 500));
        } catch (\Exception $e) {
            $log['response_error'] = [
                'error_code' => $e->getCode(),
                'message' => $e->getMessage(),
                'trace' => $e->getTrace(),
            ];
            $this->logger->addError(__METHOD__, $log);

            throw new \Exception($e->getMessage(), (!empty($e->getCode()) ? (int)$e->getCode() : 500));
        }
    }

    /**
     * Capture from Reepay
     *
     * @param array $data
     * @return array
     * @throws \Magento\framework\Exception\PaymentException
     */
    protected function settled($data)
    {
        $order_id = $data['invoice'];
        $this->logger->addDebug(__METHOD__, [$order_id]);
        $order = $this->orderInterface->loadByIncrementId($order_id);

        try {
            if (!$order->getId()) {
                $this->logger->addError('The order #' . $order_id . ' no longer exists.');

                return [
                    'status' => 500,
                    'message' => 'The order #' . $order_id . ' no longer exists.'
                ];
            }

            $apiKey = $this->reepayHelper->getApiKey($order->getStoreId());
            $reepayTransactionData = $this->invoiceHelper->getTransaction($apiKey, $order_id, $data['transaction']);

            if (!empty($reepayTransactionData['id']) && $reepayTransactionData['type'] == "settle") {
                // check the transaction has been created
                $transactions = $this->transactionSearchResultInterfaceFactory->create()->addOrderIdFilter($order->getId());
                $hasTxn = false;
                foreach ($transactions->getItems() as $transaction) {
                    if ($transaction->getTxnId() == $reepayTransactionData['id']) {
                        $hasTxn = true;
                    }
                }
                if ($hasTxn) {
                    $this->logger->addDebug("Magento have created the transaction '" . $reepayTransactionData['id'] . "' already.");

                    return [
                        'status' => 200,
                        'message' => "Magento have created the transaction '" . $reepayTransactionData['id'] . "' already.",
                    ];
                }

                $apiKey = $this->reepayHelper->getApiKey($order->getStoreId());
                $chargeRes = $this->reepayCharge->get(
                    $apiKey,
                    $order_id
                );

                $transactionID = $this->reepayHelper->addCaptureTransactionToOrder($order, $reepayTransactionData, $chargeRes);
                if ($transactionID) {
                    $this->reepayHelper->setReepayPaymentState($order->getPayment(), 'settled');
                    $order->save();

                    $this->surchargeFee($order_id, $chargeRes);

                    $this->logger->addDebug('Settled order #' . $order_id . " , transaction ID : " . $transactionID);

                    return [
                        'status' => 200,
                        'message' => 'Settled order #' . $order_id . " , transaction ID : " . $transactionID,
                    ];
                } else {
                    $this->logger->addError('Cannot create capture transaction for order #' . $order_id . " , transaction : " . $reepayTransactionData['id']);

                    return [
                        'status' => 500,
                        'message' => 'Cannot create capture transaction for order #' . $order_id . " , transaction : " . $reepayTransactionData['id'],
                    ];
                }
            } else {
                $this->logger->addError('Cannot get transaction data from Reepay : transaction ID = ' . $data['transaction']);

                return [
                    'status' => 500,
                    'message' => 'Cannot get transaction data from Reepay : transaction ID = ' . $data['transaction']
                ];
            }
        } catch (\Exception $e) {
            $this->logger->addError('settled webhook exception : ' . $e->getMessage());

            return [
                'status' => 500,
                'message' => 'settled webhook exception : ' . $e->getMessage()
            ];
        }
    }

    /**
     * Refund from Reepay
     *
     * @param array $data
     * @return void | array error message
     */
    protected function refund($data)
    {
        $order_id = $data['invoice'];
        $this->logger->addDebug(__METHOD__, [$order_id]);
        $order = $this->orderInterface->loadByIncrementId($order_id);

        try {
            if (!$order->getId()) {
                $this->logger->addError('The order #' . $order_id . ' no longer exists.');

                return [
                    'status' => 500,
                    'message' => 'The order #' . $order_id . ' no longer exists.'
                ];
            }

            $apiKey = $this->reepayHelper->getApiKey($order->getStoreId());
            $refundData = $this->invoiceHelper->getTransaction($apiKey, $order_id, $data['transaction']);

            if (!empty($refundData['id']) && $refundData['state'] == "refunded") {

                // check the transaction has been created
                $transactions = $this->transactionSearchResultInterfaceFactory->create()->addOrderIdFilter($order->getId());
                $hasTxn = false;
                foreach ($transactions->getItems() as $transaction) {
                    if ($transaction->getTxnId() == $refundData['id']) {
                        $hasTxn = true;
                    }
                }

                if ($hasTxn) {
                    $this->logger->addDebug("Magento have created the transaction '" . $refundData['id'] . "' already.");
                    return [
                        'status' => 200,
                        'message' => "Magento have created the transaction '" . $refundData['id'] . "' already.",
                    ];
                }

                // create refund transaction
                $apiKey = $this->reepayHelper->getApiKey($order->getStoreId());
                $chargeRes = $this->reepayCharge->get(
                    $apiKey,
                    $order_id
                );

//                $refundAmount = $this->reepayHelper->convertAmount($refundData['amount']);
                $transactionID = $this->reepayHelper->addRefundTransactionToOrder($order, $refundData, $chargeRes);

                if ($transactionID) {
                    $this->reepayHelper->setReepayPaymentState($order->getPayment(), 'refunded');
                    $order->save();

                    $this->logger->addDebug('Refunded order #' . $order_id . " , transaction ID : " . $transactionID);

                    return [
                        'status' => 200,
                        'message' => 'Refunded order #' . $order_id . " , transaction ID : " . $transactionID,
                    ];
                } else {
                    $this->logger->addError('Cannot create refund transaction for order #' . $order_id . " , transaction : " . $refundData['id']);

                    return [
                        'status' => 500,
                        'message' => 'Cannot create refund transaction for order #' . $order_id . " , transaction : " . $refundData['id'],
                    ];
                }
            } else {
                $this->logger->addError('Cannot get refund transaction data from Reepay : transaction ID = ' . $data['transaction']);

                return [
                    'status' => 500,
                    'message' => 'Cannot get refund transaction data from Reepay : transaction ID = ' . $data['transaction'],
                ];
            }
        } catch (\Exception $e) {
            $this->logger->addError('refund webhook exception : ' . $e->getMessage());

            return [
                'status' => 500,
                'message' => 'refund webhook exception : ' . $e->getMessage()
            ];
        }
    }

    /**
     * Cancel from Reepay
     *
     * @param array $data
     * @return array
     */
    protected function cancel($data)
    {
        $order_id = $data['invoice'];
        $this->logger->addDebug(__METHOD__, [$order_id]);
        $order = $this->orderInterface->loadByIncrementId($order_id);

        try {
            if (!$order->getId()) {
                $this->logger->addError('The order #' . $order_id . ' no longer exists.');

                return [
                    'status' => 500,
                    'message' => 'The order #' . $order_id . ' no longer exists.'
                ];
            }

            if (!$order->canCancel()) {
                $this->logger->addError('Cannot cancel this order');

                return [
                    'status' => 500,
                    'message' => 'Cannot cancel this order'
                ];
            }

            $order->cancel();
            $order->addStatusHistoryComment('Reepay : order have been cancelled by Reepay webhook');
            $order->save();

            $_payment = $order->getPayment();
            $this->reepayHelper->setReepayPaymentState($_payment, 'cancelled');
            $order->save();

            $this->logger->addDebug('cancelled order #' . $order_id);

            return [
                'status' => 200,
                'message' => 'cancelled order #' . $order_id
            ];
        } catch (\Exception $e) {
            $this->logger->addError('cancel webhook exception : ' . $e->getMessage());

            return [
                'status' => 500,
                'message' => 'cancel webhook exception : ' . $e->getMessage()
            ];
        }
    }

    /**
     * Create authorize transaction if have no the transaction
     *
     * @param array $data
     * @return array
     * @throws \Magento\framework\Exception\PaymentException
     */
    protected function authorize($data)
    {
        $order_id = $data['invoice'];
        $this->logger->addDebug(__METHOD__, [$order_id]);
        $order = $this->orderInterface->loadByIncrementId($order_id);

        try {
            // check if has reepay status row for the order, That means the order has been authorized
            $reepayStatus = $this->reepayStatus->load($order_id, 'order_id');
            if ($reepayStatus->getStatusId()) {
                $this->logger->addDebug('order #' . $order_id . ' has been authorized already');

                return [
                    'status' => 200,
                    'message' => 'order #' . $order_id . ' has been authorized already',
                ];
            }

            $apiKey = $this->reepayHelper->getApiKey($order->getStoreId());
            $chargeRes = $this->reepayCharge->get(
                $apiKey,
                $order_id
            );

            // add Reepay payment data
            $data = [
                'order_id' => $order_id,
                'first_name' => $order->getBillingAddress()->getFirstname(),
                'last_name' => $order->getBillingAddress()->getLastname(),
                'email' => $order->getCustomerEmail(),
                // 'token' => $params['id'],
                'masked_card_number' => isset($chargeRes['source']['masked_card']) ? $chargeRes['source']['masked_card'] : '',
                'fingerprint' => isset($chargeRes['source']['fingerprint']) ? $chargeRes['source']['fingerprint'] : '',
                'card_type' => isset($chargeRes['source']['card_type']) ? $chargeRes['source']['card_type'] : '',
                'status' => $chargeRes['state'],
            ];

            $newReepayStatus = $this->reepayStatus;
            $newReepayStatus->setData($data);
            $newReepayStatus->save();
            $this->logger->addDebug('save reepay status');

            $this->reepayHelper->addTransactionToOrder($order, $chargeRes);
            $this->logger->addDebug('order #' . $order_id . ' has been authorized by Reepay webhook');

            $this->surchargeFee($order_id, $chargeRes);
            return [
                'status' => 200,
                'message' => 'order #' . $order_id . ' has been authorized by Reepay webhook',
            ];
        } catch (\Exception $e) {
            $this->logger->addError('webhook authorize exception : ' . $e->getMessage());

            return [
                'status' => 500,
                'message' => 'webhook authorize error : ' . $e->getMessage(),
            ];
        }
    }

    /**
     * @param $orderIncrementId
     * @param $chargeRes
     */
    private function surchargeFee($orderIncrementId, $chargeRes)
    {
        $isSurchargeFeeEnable = $this->reepayHelper->isSurchargeFeeEnabled();
        $this->logger->addDebug(__METHOD__, ['isSurchargeFeeEnable' => $isSurchargeFeeEnable, 'orderIncrementId' => $orderIncrementId]);
        if ($isSurchargeFeeEnable) {
            //to test add 50.00
//            $chargeRes['source']['surcharge_fee'] = '5100';
            $this->logger->addDebug('updateFeeToOrder', $chargeRes);
            $this->reepaySurchargeFee->updateFeeToOrder($orderIncrementId, $chargeRes);
        } else {
            $this->logger->addDebug('NotupdateFeeToOrder', $chargeRes);
            $this->reepayEmail->sendEmail($orderIncrementId);
        }
    }
}
