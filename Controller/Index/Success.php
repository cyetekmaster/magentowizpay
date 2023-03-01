<?php

namespace Wizpay\Wizpay\Controller\Index;

use Magento\Sales\Model\Order;

/**
 * Oxipay\OxipayPaymentGateway\Controller\Checkout
 */
class Success extends Index
{

    private $logger;


    public function __construct(
        \Psr\Log\LoggerInterface $logger
    ) {
        $this->logger = $logger;
    }
    
    public function execute() // phpcs:ignore
    {


        $this->logger->info("-------------------->>>>>>>>>>>>>>>>>>WIZPAY CALL BACK START<<<<<<<<<<<<<<<<<<<<-------------------");

        if (!empty($this->getRequest()->getParam('orderid')) &&  !empty($this->getRequest()->getParam('mref'))) {
           
            $orderId = $this->getRequest()->getParam('orderid');
            $merchantReference = $this->getRequest()->getParam('mref');

            $this->logger->info("callback_request_orderId->" . $orderId);
            $this->logger->info("callback_request_merchantReference->" . $merchantReference);

            $objectManager = \Magento\Framework\App\ObjectManager::getInstance();

            $order = $objectManager->create('\Magento\Sales\Model\OrderRepository')->get($orderId); // phpcs:ignore

            $additionalInformation = $order->getPayment()->getAdditionalInformation();
            $wz_token = $additionalInformation['token'];
            $wzTxnId = $additionalInformation['transactionId'];

            $additionalInformation['tocken'] =  $wz_token;
            $additionalInformation['merchantReference'] =  $merchantReference;
            $additionalInformation['transactionId'] =  $wzTxnId;

            $api_data = [
                'transactionId' => $wzTxnId,
                'token' => $wz_token,
                'merchantReference' => $merchantReference
            ];

            $payment = $order->getPayment();
            $payment->setAdditionalInformation($additionalInformation);
            $payment->save();

            $wz_api_key = $this->helper->getConfig('payment/wizpay/api_key');

            $failed_url = $this->helper->getConfig('payment/wizpay/failed_url');
            $success_url = $this->helper->getConfig('payment/wizpay/success_url');
            $capture_settings = '1';// $this->helper->getConfig('payment/wizpay/capture');
            $wzresponse = $this->helper->getOrderPaymentStatusApi($wz_api_key, $api_data);

            if (!is_array($wzresponse)) {
                $this->logger->info("-------------------->>>>>>>>>>>>>>>>>>WIZPAY CALL getOrderPaymentStatusApi START<<<<<<<<<<<<<<<<<<<<-------------------");
                $messageconc = "was rejected by Wizpay. Transaction #$wzTxnId.";
                $this->getCheckoutHelper()->cancelCurrentOrder("Order #".($order->getId())." ". $messageconc);

                $this->getCheckoutHelper()->restoreQuote(); //restore cart
                $this->getMessageManager()->addErrorMessage(__("There was an error in the Wizpay payment"));

                if (!empty($failed_url)) {

                    $this->_redirect($failed_url);
                } else {
                    $this->_redirect('checkout', ['_secure'=> false]);
                }

            } else {

                $orderStatus = $wzresponse['transactionStatus'];
                $paymentStatus = $wzresponse['paymentStatus'];
                $apiOrderId = $wzresponse['transactionId'];
                ;
         
                if (
                    ("APPROVED" == $orderStatus &&
                    "AUTH_APPROVED" == $paymentStatus)
                    ||
                    ("COMPLETED" == $orderStatus &&
                    "CAPTURED" == $paymentStatus)
                ) {
                   

                    $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
                    $order = $objectManager->create('\Magento\Sales\Model\Order')->load($orderId); // phpcs:ignore
                    $currentStatus = $order->getState();
                    $status = 'pending_capture';
                    $comment = 'In order to capture this transaction, please make the partial capture manually.';
                    $comment .= ' Wizpay Transaction ID ('. $wzTxnId .')';
                    $order->addStatusToHistory('pending_capture', $comment, false);
                    $isNotified = false;
                    $order->setState($status)->setStatus($status);
                    $payment = $order->getPayment();
                    $payment->setTransactionId($wzTxnId);
                    $payment->setIsTransactionClosed(false);
                    $payment->addTransaction(\Magento\Sales\Model\Order\Payment\Transaction::TYPE_AUTH, null, 0);
                    $order->save();
                    
                    /*$order = $object_Manager->create('\Magento\Sales\Model\Order')->load($orderId);
                    $order->addStatusToHistory('pending', 'Put your comment here', false);
                    $order->save();*/

                    $this->logger->info("-------------------->>>>>>>>>>>>>>>>>>WIZPAY CALL BACK END 115<<<<<<<<<<<<<<<<<<<<-------------------");

                    if (!empty($success_url)) {
                        $this->_redirect($success_url);
                    } else {
                        $this->_redirect('checkout/onepage/success', ['_secure'=> false]);
                    }
                   
                }
            } // End of [ if ($orderStatus == 'APPROVED' && $paymentStatus == 'AUTH_APPROVED')]
        }//if (isset($_REQUEST['orderid']) && isset($_REQUEST['mref'] ) ) {
    }

    private function customAdminEmail($orderId, $out_of_stock_p_details)
    {
        // $email = $this->helper->getConfig('trans_email/ident_general/email');
        // $mailmsg = $out_of_stock_p_details . ' from the order are not in stock, so payment was not captured. You need to capture the payment manually after it is back in stock.'; // phpcs:ignore
        // $mailTransportFactory = $this->helper->mailTransportFactory();
        // $message = new \Magento\Framework\Mail\Message();
        // /*$message->setFrom($email);*/ // phpcs:ignore
        // $message->addTo($email);
        // $message->setSubject('New Order #' . $orderId . ' Placed With Out Of Stock Items');
        // $message->setBody($mailmsg);
        // $transport = $mailTransportFactory->create(['message' => $message]);
        // //print_r($transport);
        // return;
        // $transport->sendMessage(); // phpcs:ignore
    }

    private function statusExists($orderStatus)
    {
        $statuses = $this->getObjectManager()
            ->get('Magento\Sales\Model\Order\Status') // phpcs:ignore
            ->getResourceCollection()
            ->getData();
        foreach ($statuses as $status) {
            if ($orderStatus === $status["status"]) {
                return true;
            }
        }
        return false;
    }

    private function invoiceOrder($order, $transactionId)
    {
        if (!$order->canInvoice()) {
                throw new \Magento\Framework\Exception\LocalizedException(
                    __('Cannot create an invoice.')
                );
        }
        
        $invoice = $this->getObjectManager()
            ->create('Magento\Sales\Model\Service\InvoiceService') // phpcs:ignore
            ->prepareInvoice($order);
        
        if (!$invoice->getTotalQty()) {
            throw new \Magento\Framework\Exception\LocalizedException(
                __('You can\'t create an invoice without products.')
            );
        }
        
        /*
         * Look Magento/Sales/Model/Order/Invoice.register() for CAPTURE_OFFLINE explanation.
         * Basically, if !config/can_capture and config/is_gateway and CAPTURE_OFFLINE and
         * Payment.IsTransactionPending => pay (Invoice.STATE = STATE_PAID...)
         */
        $invoice->setTransactionId($transactionId);
        $invoice->setRequestedCaptureCase(Order\Invoice::CAPTURE_OFFLINE);
        $invoice->register();

        $transaction = $this->getObjectManager()->create('Magento\Framework\DB\Transaction') // phpcs:ignore
            ->addObject($invoice)
            ->addObject($invoice->getOrder());
        $transaction->save();
    }
}
