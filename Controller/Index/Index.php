<?php
/**
 *
 * @package     magento2
 * @author
 * @license     https://opensource.org/licenses/OSL-3.0 Open Software License v. 3.0 (OSL-3.0)
 * @link
 */

namespace Wizpay\Wizpay\Controller\Index;

use \Wizpay\Wizpay\Helper\Data;
use \Wizpay\Wizpay\Helper\Checkout;
use Magento\Checkout\Model\Session;
use Magento\Sales\Model\OrderFactory;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\View\Result\PageFactory;
use Magento\CatalogInventory\Api\StockRegistryInterface;
use Magento\CatalogInventory\Api\Data\StockItemInterface;

class Index extends Action
{
    /**
     * @var PageFactory
     */
    protected $resultRedirectFactory;
    /**
     * @var \Magento\Sales\Api\OrderRepositoryInterface
     */
    protected $orderRepository;

    private $_checkoutHelper;

    private $_messageManager;

    /**
     * @var StockRegistryInterface|null
     */
    private $stockRegistry;
     /**
      * @var \Magento\Framework\View\Result\PageFactory
      */
    protected $resultPageFactory;
    /**
     * Index constructor.
     * @param PageFactory $resultRedirectFactory
     * @param \Magento\Framework\App\Action\Context       $context
     * @param \Magento\Framework\View\Result\PageFactory  $resultPageFactory
     * @param \Magento\Sales\Api\OrderRepositoryInterface $orderRepository
     */
     
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Framework\View\Result\PageFactory $resultPageFactory,
        \Magento\Framework\Controller\Result\Redirect $resultRedirectFactory,
        \Magento\Sales\Api\OrderRepositoryInterface $orderRepository,
        \Magento\Sales\Model\Service\InvoiceService $invoiceService,
        \Magento\Framework\DB\Transaction $transaction,
        StockRegistryInterface $stockRegistry,
        //\Magento\Paypal\Model\Adminhtml\ExpressFactory $authorisationFactory,
        \Magento\Sales\Model\Order\Email\Sender\InvoiceSender $invoiceSender,
        Data $helper,
        Checkout $checkoutHelper,
        Session $checkoutSession,
        OrderFactory $orderFactory
    ) {
        $this->invoiceSender = $invoiceSender;
        $this->resultRedirectFactory = $resultRedirectFactory;
        $this->resultPageFactory = $resultPageFactory;
        $this->_checkoutSession = $checkoutSession;
        $this->_orderFactory = $orderFactory;
        $this->helper = $helper;
        $this->_transaction = $transaction;
        $this->_messageManager = $context->getMessageManager();
        $this->_checkoutHelper = $checkoutHelper;
        $this->orderRepository = $orderRepository;
        $this->_invoiceService = $invoiceService;
        //$this->authorisationFactory = $authorisationFactory;
        $this->stockRegistry = $stockRegistry;
        parent::__construct($context);
    }

    protected function invoiceSender()
    {
        return $this->invoiceSender;
    }

    protected function getCheckoutHelper()
    {
        return $this->_checkoutHelper;
    }

    protected function getMessageManager()
    {
        return $this->_messageManager;
    }

    protected function orderRepository()
    {
        return $this->orderRepository;
    }

    /* protected function authorisationFactory()
    {
        return $this->authorisationFactory;
    } */
    
    protected function invoiceService()
    {
        return $this->_invoiceService;
    }

    protected function transaction()
    {
        return $this->_transaction;
    }

    /**
     * get stock status
     *
     * @param int $productId
     * @return bool
     */
    protected function getStockStatus($productId)
    {
        /** @var StockItemInterface $stockItem */
        $stockItem = $this->stockRegistry->getStockItem($productId);
        $isInStock = $stockItem ? $stockItem->getIsInStock() : false;
        return $isInStock;
    }

    /**
     * Execute action based on request and return result
     *
     * Note: Request will be added as operation argument in future
     *
     * @return \Magento\Framework\Controller\ResultInterface|ResponseInterface
     * @throws \Magento\Framework\Exception\NotFoundException
     */
    public function execute()
    {
        $orders = $this->_checkoutSession->getLastRealOrder();
        $orderId = $orders->getEntityId();
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $order = $objectManager->create('\Magento\Sales\Model\OrderRepository')->get($orderId); // phpcs:ignore
        // echo $orderId;
        $wzresponse = $this->getOrderData();
        // print_r($order); exit();
        if (is_array($wzresponse)) {

            if ('200' == $wzresponse['responseCode']) {

                $redirect_url = $wzresponse['redirectCheckoutUrl'];
                $wzToken = $wzresponse['token'];
                $wzTxnId = $wzresponse['transactionId'];
                
                $payment = $order->getPayment();
                $data_to_store =  [
                    'token' => $wzToken,
                    'transactionId' => $wzTxnId
                ];
                $payment->setTransactionId($wzTxnId);
                $payment->setParentTransactionId($payment->getTransactionId());

                $payment->setAdditionalInformation($data_to_store);
                $payment->save();
                $resultRedirect = $this->resultRedirectFactory->create();
                //$redirectLink = $redirect_url;
                $resultRedirect->setUrl($redirect_url);
                return $resultRedirect;
            }
        } else {

            $_checkoutSession = $objectManager->create('\Magento\Checkout\Model\Session'); // phpcs:ignore
            $_quoteFactory = $objectManager->create('\Magento\Quote\Model\QuoteFactory'); // phpcs:ignore

            $quote = $_quoteFactory->create()->loadByIdWithoutStore($orders->getQuoteId());
            if ($quote->getId()) {
                $quote->setIsActive(1)->setReservedOrderId(null)->save();
                $_checkoutSession->replaceQuote($quote);
                $resultRedirect = $this->resultRedirectFactory->create();
                $resultRedirect->setPath('checkout/cart');
                $messageconc = "Something went wrong while finalising your payment. Wizpay ";
                $this->messageManager->addError(__($messageconc . $wzresponse));
                //$this->messageManager->addWarningMessage('Payment Failed.');
                return $resultRedirect;
            }
        }
    }

    private function getOrderData()
    {

        $orders = $this->_checkoutSession->getLastRealOrder();
        $orderId=$orders->getEntityId();
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $order = $objectManager->create('\Magento\Sales\Model\OrderRepository')->get($orderId); // phpcs:ignore
        $billingaddress = $order->getBillingAddress();
        $getStreet = $billingaddress->getStreet();
        
        $uniqid = hash('md5', time() . $orderId);
        $merchantReference =  'MER' . $uniqid . '-' . $orderId;
        $successurl = $this->helper->getCompleteUrl();
        $cancelurl = $this->helper->getCancelledUrl();

        $success_url =  $successurl . '?mref=' . $merchantReference . '&orderid=' . $orderId;
        $fail_url =  $cancelurl . '?mref=' . $merchantReference . '&orderid=' . $orderId;
        //$getStoreCurrency = $this->helper->getStoreCurrency();

        $getStoreCurrency = 'AUD';
        /*if ($getStoreCurrency != 'AUD'){
            return;
        }*/
        if (!isset($billingaddress)) {

            return;
        }

        if (!isset($getStreet[0])) {
            return;

        } else {

            $addlineOne = $getStreet[0];
        }
        
        if (empty($getStreet[1])) {

            $addrs = explode(' ', $getStreet[0]);
            $addlineTwo = $addrs[count($addrs) - 1];

        } else {

            $addlineTwo = $getStreet[1];
        }

        //Loop through each item and fetch data
        $items = $order->getAllVisibleItems();

        foreach ($items as $item) {

            if ($item->getData()) {
                $itemsdata[] = [
                    'name' => $item->getName(),
                    'sku' => $item->getSku(),
                    'quantity' => (int)$item->getQtyOrdered(),
                    'price' => [
                        'amount' => number_format($item->getPrice(), 2),
                        'currency' => $getStoreCurrency
                    ]
                ];
            }
        }

        $data = [
            "amount"=> [
                "amount"=> number_format($order->getGrandTotal(), 2),
                "currency"=> $getStoreCurrency
            ],
            "consumer"=> [
                "phoneNumber"=> $billingaddress->getTelephone(),
                "givenNames"=> $order->getCustomerFirstname(),
                "surname"=> $order->getCustomerLastname(),
                "email"=> $order->getCustomerEmail()
            ],
            "billing"=> [
                "name"=> $order->getCustomerFirstname(),
                "line1"=> $addlineOne,
                "line2"=> $addlineTwo,
                "area1"=> $billingaddress->getCity(),
                "area2"=> null,
                "region"=> "NSW",
                "postCode"=> $billingaddress->getPostCode(),
                "countryCode"=> $billingaddress->getCountryId(),
                "phoneNumber"=> $billingaddress->getTelephone()
            ],
            "shipping"=> [
                "name"=> $order->getCustomerFirstname(),
                "line1"=> $addlineOne,
                "line2"=> $addlineTwo,
                "area1"=> $billingaddress->getCity(),
                "area2"=> null,
                "region"=> "NSW",
                "postCode"=>$billingaddress->getPostCode(),
                "countryCode"=> $billingaddress->getCountryId(),
                "phoneNumber"=> $billingaddress->getTelephone()
            ],
            /*"courier"=> array(
                "shippedAt"=> "2018-09-22T00:00:00",
                "name"=> null,
                "tracking"=> "TRACK_800",
                "priority"=> null
            ),*/
            "description"=> "Test orde 2",
            'items' => $itemsdata,
            "discounts" =>[
                    [
                
                    "displayName"=> null,
                    "discountNumber"=> 0,
                    "amount"=> [
                        "amount"=> number_format($order->getDiscountAmount(), 2),
                        "currency"=> $getStoreCurrency
                    ]
                    ]
                ],
            "merchant"=> [
                "redirectConfirmUrl"=> $success_url,
                "redirectCancelUrl"=> $fail_url
            ],

            "merchantReference"=> $merchantReference,
            'merchantOrderId'=> $orderId,

            "taxAmount"=> [
                "amount"=> number_format($order->getTaxAmount(), 2),
                "currency"=> $getStoreCurrency
            ],
            "shippingAmount"=> [
                "amount"=> number_format($order->getShippingAmount(), 2),
                "currency"=> $getStoreCurrency
            ]
        ];

        $get_api_key = $this->helper->getConfig('payment/wizpay/api_key');
        $wzresponse = $this->helper->callCcheckoutsRredirectAapi($get_api_key, $data);
        return $wzresponse;
    }
}
