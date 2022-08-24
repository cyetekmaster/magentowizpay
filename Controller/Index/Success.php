<?php
/**
 *
 * @package     magento2
 * @author
 * @license     https://opensource.org/licenses/OSL-3.0 Open Software License v. 3.0 (OSL-3.0)
 * @link
 */

namespace Wizpay\Wizpay\Controller\Index;

class Success implements \Magento\Framework\App\Action\HttpGetActionInterface
{
    const CHECKOUT_STATUS_CANCELLED = "CANCELLED";
    const CHECKOUT_STATUS_SUCCESS = "SUCCESS";

    private $request;
    private $session;
    private $redirectFactory;
    private $messageManager;
    private $placeOrderProcessor;
    private $cartManagement;
    private $logger;
    private $quoteFactory;
    private $paymentDataObjectFactory;
    private $wizpay_data_helper;
    private $order;
    private $checkoutHelper;
    private $invoiceSender;
    protected $quoteRepository;
    private $productRepository; 

    public function __construct(
        \Magento\Framework\App\Request\Http $request,
        \Magento\Checkout\Model\Session $session,
        \Magento\Framework\Controller\Result\RedirectFactory $redirectFactory,
        \Magento\Framework\Message\ManagerInterface $messageManager,
        \Wizpay\Wizpay\Model\Payment\Capture\PlaceOrderProcessor $placeOrderProcessor,
        \Psr\Log\LoggerInterface $logger,
        \Magento\Quote\Model\QuoteFactory $quoteFactory,
        \Magento\Quote\Api\CartManagementInterface $cartManagement,
        \Wizpay\Wizpay\Helper\Data $wizpay_helper,
        \Magento\Sales\Model\Order $order,
        \Wizpay\Wizpay\Helper\Checkout $checkout,
        \Magento\Sales\Model\Order\Email\Sender\InvoiceSender $invoiceSender,
        \Magento\Quote\Api\CartRepositoryInterface $quoteRepository,
        \Magento\Catalog\Api\ProductRepositoryInterface $productRepository
    ) {
        $this->request = $request;
        $this->session = $session;
        $this->redirectFactory = $redirectFactory;
        $this->messageManager = $messageManager;
        $this->placeOrderProcessor = $placeOrderProcessor;
        $this->cartManagement = $cartManagement;
        $this->logger = $logger;
        $this->quoteFactory = $quoteFactory;
        $this->wizpay_data_helper = $wizpay_helper;
        $this->order = $order;
        $this->checkoutHelper = $checkout;
        $this->invoiceSender = $invoiceSender;
        $this->quoteRepository = $quoteRepository;
        $this->productRepository = $productRepository;
    }

    public function execute()
    {
        $this->logger->info("-------------------->>>>>>>>>>>>>>>>>>WIZPAY CALL BACK START<<<<<<<<<<<<<<<<<<<<-------------------");
        
        $callback_request_quote_id = $this->request->getParam("quoteId");
        $callback_request_mref = $this->request->getParam("mref");
        // get quote
        // $quote = $this->quoteFactory
        //     ->create()
        //     ->loadByIdWithoutStore($callback_request_quote_id);
        $quote = $this->quoteRepository->get($callback_request_quote_id);

        $this->logger->info("callback_request_quote_id->" . $callback_request_quote_id);
        $paymentMethod = $quote->getPayment();
        $additionalInformation = $paymentMethod->getAdditionalInformation();

        // call api to get payment detail
        $wz_token = $additionalInformation["token"];
        $wzTxnId = $additionalInformation["transactionId"];
        $merchantReference  = $additionalInformation["mer"];

        $api_data = [
            "transactionId" => $wzTxnId,
            "token" => $wz_token,
            "merchantReference" => $merchantReference
        ];

        $wz_api_key = $this->wizpay_data_helper->getConfig(
            "payment/wizpay/api_key"
        );

        $failed_url = $this->wizpay_data_helper->getConfig(
            "payment/wizpay/failed_url"
        );
        $success_url = $this->wizpay_data_helper->getConfig(
            "payment/wizpay/success_url"
        );
        $capture_settings = "1"; // $this->wizpay_data_helper->getConfig('payment/wizpay/capture');
        $wzresponse = $this->wizpay_data_helper->getOrderPaymentStatusApi(
            $wz_api_key,
            $api_data
        );
        
        

        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();

        if (!is_array($wzresponse)) {
            $errorMessage = "was rejected by Wizpay. Transaction #$wzTxnId.";
            $this->messageManager->addErrorMessage($errorMessage);
            $this->logger->info("-------------------->>>>>>>>>>>>>>>>>>WIZPAY CALL BACK END 108<<<<<<<<<<<<<<<<<<<<-------------------");
            return $this->redirectFactory->create()->setPath("checkout/cart");
        } else {
            $orderStatus = $wzresponse["transactionStatus"];
            $paymentStatus = $wzresponse["paymentStatus"];
            $apiOrderId = $wzresponse["transactionId"];
            if ("APPROVED" == $orderStatus && "AUTH_APPROVED" == $paymentStatus)
            {
                $this->logger->info("Before order create");
                // 1. check customer email address and other info
                if($quote->getCustomerEmail() == null || empty($quote->getCustomerEmail())){
                    // get customer email from response
                    if($wzresponse["transactionDetails"] != null && $wzresponse["transactionDetails"]["consumer"] != null && $wzresponse["transactionDetails"]["consumer"]["email"] != null
                        && !empty($wzresponse["transactionDetails"]["consumer"]["email"])){
                            $quote->setCustomerEmail($wzresponse["transactionDetails"]["consumer"]["email"]);
                    }else{
                        $errorMessage = "No custmer has been found. Transaction #$wzTxnId.";
                        $this->messageManager->addErrorMessage($errorMessage);
                        $this->logger->info("-------------------->>>>>>>>>>>>>>>>>>WIZPAY CALL BACK END 126<<<<<<<<<<<<<<<<<<<<-------------------");
                        return $this->redirectFactory->create()->setPath("checkout/cart");
                    }
                }
                // 2. check order items
                if($wzresponse["transactionDetails"] != null && $wzresponse["transactionDetails"]["items"] != null && is_array($wzresponse["transactionDetails"]["items"])){
                    $order_items = $quote->getAllVisibleItems();
                    foreach($wzresponse["transactionDetails"]["items"] as $wiz_item){
                        $order_item_found = false;
                        foreach ($order_items as $item) {
                            if ($item->getData() && $item->getSku() == $wiz_item['sku']) {
                                $order_item_found = true;
                                break;
                            }
                        }

                        // if not found in quote then add it
                        if(!$order_item_found){
                            // add product into quote
                            $this->logger->info("add ->Product:" . $wiz_item['sku'] . ' to quote.');
                            $product = $this->productRepository->get($wiz_item['sku']);
                            if(isset($product)){
                                $quote->addProduct($product, intval($wiz_item['quantity'], 1));
                            }
                        }
                    }                    
                }
                
                // update quote
                $this->quoteRepository->save($quote);


                // 3. convert quote to order
                $orderId = $this->cartManagement->placeOrder($quote->getId());
                $this->logger->info("After order create");
                // get order
                $order = $this->order->load($orderId);

                // update order id to api
                $this->wizpay_data_helper->updateOrderIdApi(
                    $wz_api_key,
                    $wzTxnId,
                    $orderId
                );

                
                    $capture_amount = floatval($order->getGrandTotal());
                    $price_total_sum = 0;

                    // order items inStocks Call immediatePaymentCapture()
                    $api_data = [
                        "token" => $wz_token,
                        "merchantReference" => $merchantReference,
                    ];

                    $wzresponse = $this->wizpay_data_helper->immediatePaymentCapture(
                        $wz_api_key,
                        $api_data
                    );
                    

                    if (!is_array($wzresponse)) {
                        $this->checkoutHelper->cancelCurrentOrder(
                            "Order #" .
                                $order->getId() .
                                " was rejected by Wizpay. Transaction ID" .
                                $apiOrderId
                        ); // phpcs:ignore
                        $this->checkoutHelper->restoreQuote(); //restore cart
                        $this->messageManager->addErrorMessage(
                            __("There was an error in the Wizpay payment")
                        );
                        $this->logger->info("-------------------->>>>>>>>>>>>>>>>>>WIZPAY CALL BACK END 420<<<<<<<<<<<<<<<<<<<<-------------------");
                        if (!empty($failed_url)) {
                            $this->_redirect($failed_url);
                        } else {
                            $this->_redirect("checkout", ["_secure" => false]);
                        }
                    } else {
                        if ($order->canInvoice()) {
                            // Create invoice for this order
                            $invoice = $objectManager
                                ->create(
                                    "Magento\Sales\Model\Service\InvoiceService"
                                )
                                ->prepareInvoice($order); // phpcs:ignore

                            // Make sure there is a qty on the invoice
                            if (!$invoice->getTotalQty()) {
                                throw new \Magento\Framework\Exception\LocalizedException(
                                    __(
                                        'You can\'t create an invoice without products.'
                                    )
                                );
                            }

                            // Register as invoice item
                            $invoice->setRequestedCaptureCase(
                                \Magento\Sales\Model\Order\Invoice::CAPTURE_ONLINE
                            ); // phpcs:ignore
                            $invoice->register();
                            $payment = $order->getPayment();
                            $payment->setTransactionId($apiOrderId);
                            $payment->addTransaction(
                                \Magento\Sales\Model\Order\Payment\Transaction::TYPE_CAPTURE
                            ); // phpcs:ignore
                            $payment->save();

                            // Save the invoice to the order
                            $transaction = $objectManager
                                ->create("Magento\Framework\DB\Transaction") // phpcs:ignore
                                ->addObject($invoice)
                                ->addObject($invoice->getOrder());

                            $transaction->save();
                            // Magento\Sales\Model\Order\Email\Sender\InvoiceSender
                            $this->invoiceSender->send($invoice);

                            $order
                                ->addStatusHistoryComment(
                                    __(
                                        "Notified customer about invoice #%1.",
                                        $invoice->getId()
                                    )
                                )
                                ->setIsCustomerNotified(true)
                                ->save();
                        }

                        $order->addStatusToHistory(
                            "processing",
                            "Your payment with Wizpay is complete. Wizpay Transaction ID: " .
                                $apiOrderId,
                            false
                        );

                        $order->save();
                        $this->logger->info("-------------------->>>>>>>>>>>>>>>>>>WIZPAY CALL BACK END 485<<<<<<<<<<<<<<<<<<<<-------------------");

                        $this->messageManager->addSuccessMessage(
                            (string) __("Wizpay Transaction Completed")
                        );

                        if (!empty($success_url)){
                            return $this->redirectFactory
                                ->create()
                                ->setPath($success_url);
                        }else{
                            return $this->redirectFactory
                                ->create()
                                ->setPath("checkout/onepage/success");
                        }
                    } // API response check
                 // End check if(!empty( $product_out_stocks ))
            } 
            else if("COMPLETED" == $orderStatus &&  "CAPTURED" == $paymentStatus){
                // do nothing 
                $this->logger->info("-------------------->>>>>>>>>>>>>>>>>>WIZPAY CALL BACK END 244<<<<<<<<<<<<<<<<<<<<-------------------");

                $this->messageManager->addSuccessMessage(
                    (string) __("Wizpay Transaction Completed")
                );

                if (!empty($success_url)){
                    return $this->redirectFactory
                        ->create()
                        ->setPath($success_url);
                }else{
                    return $this->redirectFactory
                        ->create()
                        ->setPath("checkout/onepage/success");
                }
            }
        }

        // all other statuc return failed
        $errorMessage = "was rejected by Wizpay. Transaction #$wzTxnId.";
        $this->messageManager->addErrorMessage($errorMessage);
        $this->logger->info("-------------------->>>>>>>>>>>>>>>>>>WIZPAY CALL BACK END 508<<<<<<<<<<<<<<<<<<<<-------------------");
        return $this->redirectFactory->create()->setPath("checkout/cart");

        
    }

    private function customAdminEmail($orderId, $out_of_stock_p_details)
    {
        // $email = $this->wizpay_data_helper->getConfig("trans_email/ident_general/email");
        // $mailmsg =
        //     $out_of_stock_p_details .
        //     " from the order are not in stock, so payment was not captured. You need to capture the payment manually after it is back in stock."; // phpcs:ignore
        // $mailTransportFactory = $this->wizpay_data_helper->mailTransportFactory();
        // $message = new \Magento\Framework\Mail\Message();
        // /*$message->setFrom($email);*/ // phpcs:ignore
        // $message->addTo($email);
        // $message->setSubject(
        //     "New Order #" . $orderId . " Placed With Out Of Stock Items"
        // );
        // $message->setBody($mailmsg);
        // $transport = $mailTransportFactory->create(["message" => $message]);
        // //print_r($transport);
        // return;
        // $transport->sendMessage(); // phpcs:ignore
    }

    private function statusExists($orderStatus)
    {
        $statuses = $this->getObjectManager()
            ->get("Magento\Sales\Model\Order\Status") // phpcs:ignore
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
                __("Cannot create an invoice.")
            );
        }

        $invoice = $this->getObjectManager()
            ->create("Magento\Sales\Model\Service\InvoiceService") // phpcs:ignore
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

        $transaction = $this->getObjectManager()
            ->create("Magento\Framework\DB\Transaction") // phpcs:ignore
            ->addObject($invoice)
            ->addObject($invoice->getOrder());
        $transaction->save();
    }
}
