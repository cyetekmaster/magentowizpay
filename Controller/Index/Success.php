<?php

namespace Wizpay\Wizpay\Controller\Index;

use Magento\Sales\Model\Order;

/**
 * Oxipay\OxipayPaymentGateway\Controller\Checkout
 */
class Success extends Index
{

    public $logger;


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
                
         
                if (
                    ("APPROVED" == $orderStatus &&
                    "AUTH_APPROVED" == $paymentStatus)
                    ||
                    ("COMPLETED" == $orderStatus &&
                    "CAPTURED" == $paymentStatus)
                ) {

                    if ($capture_settings == '1') {
                        //Loop through each item and fetch data
                        // get order item out of stock data
                        $all_items = [];
                        $product_out_stocks = [];
                        $price_total = [];
                        $backordered = 0;
                        $ordered = 0;
                        $itemsarray = [];
                        
                        foreach ($order->getAllItems() as $item) {
                            //Get the product ID
                            $alldata = $item->getData();
                            $product_id = $item->getId();
                            $total     = floatval($alldata['row_total_incl_tax']); // Total without tax (discounted)
                            $product_title = substr($item->getName(), 0, 4);
                            $product_out_stock = $alldata['qty_backordered'];
                            $ordered_status = $item->getStatus();
                            $qty_ordered = $alldata['qty_ordered'];
                            
                            if ('Backordered' == $ordered_status) {

                                $backordered++;
                            }
                            if ('Ordered' == $ordered_status) {

                                $ordered++;
                            }

                            if (!empty($product_out_stock)) {

                                $qty_invoiced = $qty_ordered - $product_out_stock;
                                // $item->setData('qty_invoiced', $qty_invoiced);
                                $product_out_stocks[] = $product_out_stock;
                                $price_total[] = $total;

                                $all_items[] = 'Item #' . $product_id . '- ' . $product_title . '...';

                                if ($qty_invoiced == 0) {
                                    continue;
                                } else {

                                    $qty_ordered = $qty_invoiced;
                                }
                            }

                            $itemsarray[$product_id] = $qty_ordered;

                        }

                        $price_total_sum = array_sum($price_total);
                        $out_of_stock_p_details = implode(', ', $all_items);
                                   

                        $this->logger->info("product_out_stocks->" . json_encode($product_out_stocks));
                        $this->logger->info("get_subtotal->" . floatval($order->getGrandTotal()));
                        $this->logger->info("capture_amount->" . (floatval($order->getGrandTotal()) - $price_total_sum));
                        $this->logger->info("backordered->" . $backordered);
                        $this->logger->info("ordered->" . $ordered);


                        $capture_amount = floatval($order->getGrandTotal());
                        $price_total_sum = 0;

                        // order items inStocks Call immediatePaymentCapture()
                        $api_data = [
                            'token' => $wz_token,
                            'merchantReference' => $merchantReference
                        ];

                        $wzresponse = $this->helper->immediatePaymentCapture($wz_api_key, $api_data);

                        if (!is_array($wzresponse)) {

                            $this->getCheckoutHelper()->cancelCurrentOrder(
                            "Order #".($order->getId())." was rejected by Wizpay. Transaction ID" . $apiOrderId); // phpcs:ignore
                            $this->getCheckoutHelper()->restoreQuote(); //restore cart
                            $this->getMessageManager()->addErrorMessage(
                                __(
                                    "There was an error in the Wizpay payment"
                                )
                            );

                            $this->logger->info("-------------------->>>>>>>>>>>>>>>>>>WIZPAY CALL BACK END 334<<<<<<<<<<<<<<<<<<<<-------------------");

                            if (!empty($failed_url)) {

                                $this->_redirect($failed_url);
                            } else {
                                $this->_redirect('checkout', ['_secure'=> false]);
                            }
                        } else {

                            if ($order->canInvoice()) {
                                // Create invoice for this order
                                $invoice = $objectManager->create('Magento\Sales\Model\Service\InvoiceService')->prepareInvoice($order); // phpcs:ignore

                                // Make sure there is a qty on the invoice
                                if (!$invoice->getTotalQty()) {
                                    throw new \Magento\Framework\Exception\LocalizedException(
                                        __('You can\'t create an invoice without products.')
                                    );
                                }

                                // Register as invoice item
                                $invoice->setRequestedCaptureCase(\Magento\Sales\Model\Order\Invoice::CAPTURE_ONLINE); // phpcs:ignore
                                $invoice->register();
                                $payment = $order->getPayment();
                                $payment->setTransactionId($apiOrderId);
                                $payment->addTransaction(\Magento\Sales\Model\Order\Payment\Transaction::TYPE_CAPTURE); // phpcs:ignore
                                $payment->save();

                                // Save the invoice to the order
                                $transaction = $objectManager->create('Magento\Framework\DB\Transaction') // phpcs:ignore
                                    ->addObject($invoice)
                                    ->addObject($invoice->getOrder());

                                $transaction->save();
                                // Magento\Sales\Model\Order\Email\Sender\InvoiceSender
                                $this->invoiceSender->send($invoice);

                                $order->addStatusHistoryComment(
                                    __(
                                        'Notified customer about invoice #%1.',
                                        $invoice->getId()
                                    )
                                )->setIsCustomerNotified(true)
                                ->save();
                            }

                            $order->addStatusToHistory(
                                'processing',
                                'Your payment with Wizpay is complete. Wizpay Transaction ID: '
                                . $apiOrderId,
                                false
                            );
                            
                            $order->save();

                            $this->logger->info("-------------------->>>>>>>>>>>>>>>>>>WIZPAY CALL BACK END 390<<<<<<<<<<<<<<<<<<<<-------------------");

                            if (!empty($success_url)) {
                                $this->_redirect($success_url);
                            } else {
                                $this->_redirect('checkout/onepage/success', ['_secure'=> false]);
                            }
                        }  // API response check
                        // End check if(!empty( $product_out_stocks ))
                    } else {

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

                        $this->logger->info("-------------------->>>>>>>>>>>>>>>>>>WIZPAY CALL BACK END 420<<<<<<<<<<<<<<<<<<<<-------------------");

                        if (!empty($success_url)) {
                            $this->_redirect($success_url);
                        } else {
                            $this->_redirect('checkout/onepage/success', ['_secure'=> false]);
                        }
                   // }
                     }
                } // End of [ if ($orderStatus == 'APPROVED' && $paymentStatus == 'AUTH_APPROVED')]
            } //if (isset($_REQUEST['orderid']) && isset($_REQUEST['mref'] ) ) {
        }
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
