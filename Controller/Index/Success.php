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

    private \Magento\Framework\App\Request\Http $request;
    private \Magento\Checkout\Model\Session $session;
    private \Magento\Framework\Controller\Result\RedirectFactory $redirectFactory;
    private \Magento\Framework\Message\ManagerInterface $messageManager;
    private \Wizpay\Wizpay\Model\Payment\Capture\PlaceOrderProcessor $placeOrderProcessor;
    private \Magento\Quote\Api\CartManagementInterface $cartManagement;
    private \Psr\Log\LoggerInterface $logger;
    private \Magento\Quote\Model\QuoteFactory $quoteFactory;
    private \Magento\Payment\Gateway\Data\PaymentDataObjectFactoryInterface $paymentDataObjectFactory;
    private \Wizpay\Wizpay\Helper\Data $wizpay_data_helper;
    private \Magento\Sales\Model\Order $order;
    private \Wizpay\Wizpay\Helper\Checkout $checkoutHelper;

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
        \Wizpay\Wizpay\Helper\Checkout $checkout
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
    }

    public function execute()
    {
        $callback_request_quote_id = $this->request->getParam("quoteId");
        $callback_request_mref = $this->request->getParam("mref");
        // get quote
        $quote = $this->quoteFactory
            ->create()
            ->loadByIdWithoutStore($callback_request_quote_id);

        $this->logger->info("quote.getGrandTotal->" . $quote->getGrandTotal());
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

        if (!is_array($wzresponse)) {
            $errorMessage = "was rejected by Wizpay. Transaction #$wzTxnId.";
            $this->messageManager->addErrorMessage($errorMessage);
            return $this->redirectFactory->create()->setPath("checkout/cart");
        } else {
            $orderStatus = $wzresponse["transactionStatus"];
            $paymentStatus = $wzresponse["paymentStatus"];
            $apiOrderId = $wzresponse["transactionId"];
            if (
                "APPROVED" == $orderStatus &&
                "AUTH_APPROVED" == $paymentStatus
            ) {
                // convert quote to order
                $orderId = $this->cartManagement->placeOrder($quote->getId());
                // get order
                $order = $this->order->load($orderId);

                // update order id to api
                $this->wizpay_data_helper->getOrderPaymentStatusApi(
                    $wz_api_key,
                    $wzTxnId,
                    $orderId
                );

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
                    $total = floatval($alldata["row_total_incl_tax"]); // Total without tax (discounted)
                    $product_title = substr($item->getName(), 0, 4);
                    $product_out_stock = $alldata["qty_backordered"];
                    $ordered_status = $item->getStatus();
                    $qty_ordered = $alldata["qty_ordered"];

                    if ("Backordered" == $ordered_status) {
                        $backordered++;
                    }
                    if ("Ordered" == $ordered_status) {
                        $ordered++;
                    }

                    if (!empty($product_out_stock)) {
                        $qty_invoiced = $qty_ordered - $product_out_stock;
                        // $item->setData('qty_invoiced', $qty_invoiced);
                        $product_out_stocks[] = $product_out_stock;
                        $price_total[] = $total;

                        $all_items[] =
                            "Item #" .
                            $product_id .
                            "- " .
                            $product_title .
                            "...";

                        if ($qty_invoiced == 0) {
                            continue;
                        } else {
                            $qty_ordered = $qty_invoiced;
                        }
                    }

                    $itemsarray[$product_id] = $qty_ordered;
                }

                $price_total_sum = array_sum($price_total);
                $out_of_stock_p_details = implode(", ", $all_items);

                if (!empty($product_out_stocks)) {
                    $get_subtotal = floatval($order->getGrandTotal());
                    $capture_amount = $get_subtotal - $price_total_sum;

                    if ($backordered > 0 && $ordered == 0) {
                        //$apicaptureOrderId = $wzresponse['transactionId'];

                        $messageconc =
                            "from the order are not in stock, so payment was not captured. ";

                        $messageconc .=
                            "You need to capture the payment manually ";
                        $messageconc .= "after it is back in stock. ";
                        $messageconc .=
                            "Wizpay Transaction ID (" . $apiOrderId . ")";

                        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
                        $order = $objectManager
                            ->create("\Magento\Sales\Model\Order")
                            ->load($orderId); // phpcs:ignore
                        $mailmsg = $out_of_stock_p_details . " " . $messageconc;
                        if (count($product_out_stocks) > 1) {
                            $order->addStatusToHistory(
                                "pending_capture",
                                $out_of_stock_p_details . " " . $messageconc,
                                false
                            );
                        } else {
                            $order->addStatusToHistory(
                                "pending_capture",
                                $out_of_stock_p_details . " " . $messageconc,
                                false
                            );
                        }

                        $currentStatus = $order->getState();
                        $status = "pending_capture";
                        $order->setState($status)->setStatus($status);

                        $payment = $order->getPayment();
                        $payment->setTransactionId($apiOrderId);
                        $payment->setIsTransactionClosed(false);
                        $payment->addTransaction(
                            \Magento\Sales\Model\Order\Payment\Transaction::TYPE_AUTH,
                            null,
                            0
                        );

                        $payment->save();
                        $order->save();

                        $this->customAdminEmail(
                            $orderId,
                            $out_of_stock_p_details
                        );

                        if (!empty($success_url)) {
                            $this->_redirect($success_url);
                        } else {
                            $this->_redirect("checkout/onepage/success", [
                                "_secure" => false,
                            ]);
                        }
                    } else {
                        //$currency = get_woocommerce_currency();
                        $uniqid = hash("md5", time() . $orderId);
                        $api_data = [
                            "RequestId" => $uniqid,
                            "merchantReference" => $merchantReference,
                            "amount" => [
                                "amount" => $capture_amount,
                                "currency" => "AUD",
                            ],
                        ];

                        $wzresponse = $this->wizpay_data_helper->orderPartialCaptureApi(
                            $wz_api_key,
                            $api_data,
                            $apiOrderId
                        );

                        if (!is_array($wzresponse)) {
                            $this->checkoutHelper->cancelCurrentOrder(
                                "Order #" .
                                    $order->getId() .
                                    " was rejected by Wizpay. Transaction #$wzTxnId."
                            );
                            $this->checkoutHelper->restoreQuote(); //restore cart
                            $this->messageManager->addErrorMessage(
                                __(
                                    "There was an error in the partial captured amount"
                                )
                            );

                            if (!empty($failed_url)) {
                                $this->_redirect($failed_url);
                            } else {
                                $this->_redirect("checkout/cart", [
                                    "_secure" => false,
                                ]);
                            }
                        } else {
                            $msg =
                                " from the order are not in stock, so payment was not captured.";
                            $msg .=
                                " You need to capture the payment manually after it is back in stock.";
                            if (count($product_out_stocks) > 1) {
                                $order->addStatusHistoryComment(
                                    $out_of_stock_p_details . $msg
                                );
                            } else {
                                $order->addStatusHistoryComment(
                                    $out_of_stock_p_details . $msg
                                );
                            }
                            // $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
                            /* $order = $objectManager->create('Magento\Sales\Model\Order')->load($orderId); */ // phpcs:ignore
                            if ($order->canInvoice()) {
                                // Create invoice for this order
                                $invoice = $objectManager
                                    ->create(
                                        "Magento\Sales\Model\Service\InvoiceService"
                                    )
                                    ->prepareInvoice($order, $itemsarray); // phpcs:ignore

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

                                $getAdditionalInformation = $payment->getAdditionalInformation();

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
                                    ->save(); // phpcs:ignore
                            }
                            $this->customAdminEmail(
                                $orderId,
                                $out_of_stock_p_details
                            );

                            if (!empty($success_url)) {
                                $this->_redirect($success_url);
                            } else {
                                $this->_redirect("checkout/onepage/success", [
                                    "_secure" => false,
                                ]);
                            }
                        }
                    } //if (empty($inStockitems ))
                } else {
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
                        if (!empty($success_url)) {
                            $this->_redirect($success_url);
                        } else {
                            $this->_redirect("checkout/onepage/success", [
                                "_secure" => false,
                            ]);
                        }
                    } // API response check
                } // End check if(!empty( $product_out_stocks ))
            } else {
                $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
                $order = $objectManager
                    ->create("\Magento\Sales\Model\Order")
                    ->load($orderId); // phpcs:ignore
                $currentStatus = $order->getState();
                $status = "pending_capture";
                $comment =
                    "In order to capture this transaction, please make the partial capture manually.";
                $comment .= " Wizpay Transaction ID (" . $wzTxnId . ")";
                $order->addStatusToHistory("pending_capture", $comment, false);
                $isNotified = false;
                $order->setState($status)->setStatus($status);
                $payment = $order->getPayment();
                $payment->setTransactionId($wzTxnId);
                $payment->setIsTransactionClosed(false);
                $payment->addTransaction(
                    \Magento\Sales\Model\Order\Payment\Transaction::TYPE_AUTH,
                    null,
                    0
                );
                $order->save();

                /*$order = $object_Manager->create('\Magento\Sales\Model\Order')->load($orderId);
                        $order->addStatusToHistory('pending', 'Put your comment here', false);
                        $order->save();*/
                if (!empty($success_url)) {
                    $this->_redirect($success_url);
                } else {
                    $this->_redirect("checkout/onepage/success", [
                        "_secure" => false,
                    ]);
                }
            }
        }

        $this->messageManager->addSuccessMessage(
            (string) __("Wizpay Transaction Completed")
        );
        return $this->redirectFactory
            ->create()
            ->setPath("checkout/onepage/success");
    }

    private function customAdminEmail($orderId, $out_of_stock_p_details)
    {
        $email = $this->wizpay_data_helper->getConfig("trans_email/ident_general/email");
        $mailmsg =
            $out_of_stock_p_details .
            " from the order are not in stock, so payment was not captured. You need to capture the payment manually after it is back in stock."; // phpcs:ignore
        $mailTransportFactory = $this->wizpay_data_helper->mailTransportFactory();
        $message = new \Magento\Framework\Mail\Message();
        /*$message->setFrom($email);*/ // phpcs:ignore
        $message->addTo($email);
        $message->setSubject(
            "New Order #" . $orderId . " Placed With Out Of Stock Items"
        );
        $message->setBody($mailmsg);
        $transport = $mailTransportFactory->create(["message" => $message]);
        //print_r($transport);
        return;
        $transport->sendMessage(); // phpcs:ignore
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
