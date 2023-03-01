<?php

namespace Wizpay\Wizpay\Observer;

use Magento\Framework\Event\Observer as EventObserver;
use Magento\Framework\Event\ObserverInterface;
use \Wizpay\Wizpay\Helper\Data;

class SalesOrderInvoicePay implements ObserverInterface
{

    /**
     * @param EventObserver $observer
     * @return $this
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */

    protected $messageManager;
    protected $resultRedirectFactory;
    protected $_request;

    public function __construct(
        Data $helper,
        \Magento\Framework\Message\ManagerInterface $messageManager,
        \Magento\Framework\Controller\Result\RedirectFactory $resultRedirectFactory,
        \Magento\Framework\App\RequestInterface $request
    ) {

        $this->helper = $helper;
        $this->messageManager = $messageManager;
        $this->resultRedirectFactory = $resultRedirectFactory;
        $this->_request = $request;
    }

    public function execute(EventObserver $observer)
    {

        $postdata = $this->_request->getPost();
        $invoice = $observer->getEvent()->getInvoice();
        $order = $invoice->getOrder();
        $payment = $order->getPayment();
        
        // $isOfflineCase = $postdata['invoice']['capture_case'];
        // if ($isOfflineCase == 'offline') {
        //     return;
        // }
        
        if ($payment->getMethod() == 'wizpay') {
            
            $isOffline = $order->getPayment()->getMethodInstance()->isOffline();
            if ($isOffline) {
                return;
            }
    
            $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
            $orderId = $order->getEntityId();
            $order = $objectManager->create('\Magento\Sales\Model\OrderRepository')->get($orderId); // phpcs:ignore
            $additionalInformation = $order->getPayment()->getAdditionalInformation();
            // print_r($additionalInformation);
            $wz_token = $additionalInformation['token'];
            $wzTxnId = $additionalInformation['transactionId'];
            $merchantReference = $additionalInformation['merchantReference'];
            $api_data = [
                        'transactionId' => $wzTxnId,
                        'token' => $wz_token,
                        'merchantReference' => $merchantReference
                    ];
    
            $wz_api_key = $this->helper->getConfig('payment/wizpay/api_key');
    
            $failed_url = $this->helper->getConfig('payment/wizpay/failed_url');
            $success_url = $this->helper->getConfig('payment/wizpay/success_url');
            $capture_settings = '1';// $this->helper->getConfig('payment/wizpay/capture');
            $wzresponse = $this->helper->getOrderPaymentStatusApi($wz_api_key, $api_data);
    
            if (!is_array($wzresponse)) {
    
                $messageconc = "Invoice was rejected by Wizpay. Transaction #$wzTxnId.";
                // $this->getCheckoutHelper()->cancelCurrentOrder("Order #".($order->getId())." ". $messageconc);
    
                // $this->getCheckoutHelper()->restoreQuote(); //restore cart
                // $this->getMessageManager()->addErrorMessage(__("There was an error in the Wizpay payment"));
                throw new \Magento\Framework\Exception\CouldNotDeleteException(__("Invoice was rejected by Wizpay. Transaction #$wzTxnId.")); // phpcs:ignore
            } else {
    
                $orderStatus = $wzresponse['transactionStatus'];
                $paymentStatus = $wzresponse['paymentStatus'];
                $apiOrderId = $wzresponse['transactionId'];
    
                //$currency = get_woocommerce_currency();
                $uniqid = hash('md5', time() . $orderId);
                $api_data = [
                    'RequestId' => $uniqid,
                    'merchantReference' => $merchantReference,
                    'amount' => [
                        'amount'=> $invoice->getBaseGrandTotal(),
                        'currency'=> 'AUD'
                    ],
                ];
    
                $wzresponse = $this->helper->orderPartialCaptureApi($wz_api_key, $api_data, $apiOrderId); // phpcs:ignore
    
                if (!is_array($wzresponse)) {
    
                       throw new \Magento\Framework\Exception\CouldNotDeleteException(__("There was an error in the partial captured amount.")); // phpcs:ignore
                } else {
    
                    // $file = fopen("/var/www/html/magento/testafter.txt","w");
                    // echo fwrite($file,print_r($wzresponse, true));
                    // fclose($file);
    
                    $payment = $order->getPayment();
    
                    // $getAdditionalInformation = $payment->getAdditionalInformation();
    
                    
    
                    $payment->setTransactionId('partial'.$invoice->getId());
                    $payment->addTransaction(\Magento\Sales\Model\Order\Payment\Transaction::TYPE_CAPTURE); // phpcs:ignore
                    $payment->save();
    
                    // Save the invoice to the order
                    $transaction = $objectManager->create('Magento\Framework\DB\Transaction') // phpcs:ignore
                        ->addObject($invoice)
                        ->addObject($invoice->getOrder());
    
                    $transaction->save();
    
                    // Magento\Sales\Model\Order\Email\Sender\InvoiceSender
                    // $this->invoiceSender->send($invoice);
    
                    $order->addStatusHistoryComment(__('Notified customer about invoice #%1.', $invoice->getId()))->save(); // phpcs:ignore
                    $this->messageManager->addSuccess(__("Invoice has been created successfully."));
                }
            }
        }


        
    }
}