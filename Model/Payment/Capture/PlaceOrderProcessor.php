<?php declare(strict_types=1);

namespace Wizpay\Wizpay\Model\Payment\Capture;

use Wizpay\Wizpay\Model\Payment\AdditionalInformationInterface;
use Magento\Payment\Gateway\CommandInterface;
use Magento\Quote\Model\Quote;

use \Wizpay\Wizpay\Helper\Checkout;

class PlaceOrderProcessor
{
    private \Magento\Quote\Api\CartManagementInterface $cartManagement;
    private \Wizpay\Wizpay\Model\Payment\Capture\CancelOrderProcessor $cancelOrderProcessor;
    private \Wizpay\Wizpay\Model\Order\Payment\QuotePaidStorage $quotePaidStorage;
    private \Magento\Payment\Gateway\Data\PaymentDataObjectFactoryInterface $paymentDataObjectFactory;
    private \Psr\Log\LoggerInterface $logger;
    private \Wizpay\Wizpay\Helper\Data $wizpay_data_helper;

    public function __construct(
        \Magento\Quote\Api\CartManagementInterface $cartManagement,
        \Wizpay\Wizpay\Model\Payment\Capture\CancelOrderProcessor $cancelOrderProcessor,
        \Wizpay\Wizpay\Model\Order\Payment\QuotePaidStorage $quotePaidStorage,
        \Magento\Payment\Gateway\Data\PaymentDataObjectFactoryInterface $paymentDataObjectFactory,
        \Psr\Log\LoggerInterface $logger,
        \Wizpay\Wizpay\Helper\Data $wizpay_helper
    ) {
        $this->cartManagement = $cartManagement;
        $this->cancelOrderProcessor = $cancelOrderProcessor;
        $this->quotePaidStorage = $quotePaidStorage;
        $this->paymentDataObjectFactory = $paymentDataObjectFactory;
        $this->logger = $logger;
        $this->wizpay_data_helper = $wizpay_helper;
    }

    public function execute(Quote $quote, string $wizpayOrderToken)
    {
        try {
            // get wizpay url
            $wzresponse = $this->getOrderData($quote);

            if (isset($wzresponse) && is_array($wzresponse) && $wzresponse['responseCode'] != null
                && '200' == $wzresponse['responseCode']){
                $redirect_url = $wzresponse['redirectCheckoutUrl'];
                $wzToken = $wzresponse['token'];
                $wzTxnId = $wzresponse['transactionId'];
                
                $resultRedirect = $this->resultRedirectFactory->create();
                //$redirectLink = $redirect_url;
                $resultRedirect->setUrl($redirect_url);

                // return retirect url
                return $resultRedirect;
            }else{
                throw new \Magento\Framework\Exception\LocalizedException(
                    __(
                        'There was a problem placing your order. Your Wizpay order %1 is refunded.',
                        $wizpayPayment->getAdditionalInformation(AdditionalInformationInterface::WIZPAY_ORDER_ID)
                    )
                );
            }


        } catch (\Throwable $e) {
            $this->logger->critical('Order placement is failed with error: ' . $e->getMessage());
            $quoteId = (int)$quote->getId();
            if ($wizpayPayment = $this->quotePaidStorage->getWizpayPaymentIfQuoteIsPaid($quoteId)) {
                $this->cancelOrderProcessor->execute($wizpayPayment);
                throw new \Magento\Framework\Exception\LocalizedException(
                    __(
                        'There was a problem placing your order. Your Wizpay order %1 is refunded.',
                        $wizpayPayment->getAdditionalInformation(AdditionalInformationInterface::WIZPAY_ORDER_ID)
                    )
                );
            }
            throw $e;
        }
    }




    private function getOrderData(Quote $quote)
    {

        // $orders = $this->_checkoutSession->getLastRealOrder();
        // $orderId=$orders->getEntityId();
        // $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        // $order = $objectManager->create('\Magento\Sales\Model\OrderRepository')->get($orderId); // phpcs:ignore
        // $billingaddress = $order->getBillingAddress();
        // $getStreet = $billingaddress->getStreet();
        
        // $uniqid = hash('md5', time() . $orderId);
        // $merchantReference =  'MER' . $uniqid . '-' . $orderId;
        // $successurl = $this->wizpay_data_helper->getCompleteUrl();
        // $cancelurl = $this->wizpay_data_helper->getCancelledUrl();

        // $success_url =  $successurl . '?mref=' . $merchantReference . '&orderid=' . $orderId;
        // $fail_url =  $cancelurl . '?mref=' . $merchantReference . '&orderid=' . $orderId;
        //$getStoreCurrency = $this->helper->getStoreCurrency();

        $quoteId = $quote->getId();
        $billingaddress = $quote->getBillingAddress();
        $getStreet = $billingaddress->getStreet();

        $uniqid = hash('md5', time() . $quoteId);
        $merchantReference =  'MER' . $uniqid . '-' . $quoteId;
        $successurl = $this->wizpay_data_helper->getCompleteUrl();
        $cancelurl = $this->wizpay_data_helper->getCancelledUrl();

        $success_url =  $successurl . '?mref=' . $merchantReference . '&quoteId=' . $quoteId;
        $fail_url =  $cancelurl . '?mref=' . $merchantReference . '&quoteId=' . $quoteId;


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
        $items = $quote->getAllVisibleItems();

        foreach ($items as $item) {

            if ($item->getData()) {
                $itemsdata[] = [
                    'name' => $item->getName(),
                    'sku' => $item->getSku(),
                    'quantity' => (int)$item->getQty(),
                    'price' => [
                        'amount' => number_format(floatval($item->getPrice()), 2),
                        'currency' => $getStoreCurrency
                    ]
                ];
            }
        }

        $data = [
            "amount"=> [
                "amount"=> number_format(floatval($quote->getGrandTotal()), 2),
                "currency"=> $getStoreCurrency
            ],
            "consumer"=> [
                "phoneNumber"=> $billingaddress->getTelephone(),
                "givenNames"=> $quote->getCustomerFirstname(),
                "surname"=> $quote->getCustomerLastname(),
                "email"=> $quote->getCustomerEmail()
            ],
            "billing"=> [
                "name"=> $quote->getCustomerFirstname(),
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
                "name"=> $quote->getCustomerFirstname(),
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
                        "amount"=> number_format(floatval($quote->getDiscountAmount()), 2),
                        "currency"=> $getStoreCurrency
                    ]
                    ]
                ],
            "merchant"=> [
                "redirectConfirmUrl"=> $success_url,
                "redirectCancelUrl"=> $fail_url
            ],

            "merchantReference"=> $merchantReference,
            // merchantOrderId'=> $quoteId,
            "merchantQuoteId" =>  $quoteId,

            "taxAmount"=> [
                "amount"=> number_format(floatval($quote->getTaxAmount()), 2),
                "currency"=> $getStoreCurrency
            ],
            "shippingAmount"=> [
                "amount"=> number_format(floatval($quote->getShippingAmount()), 2),
                "currency"=> $getStoreCurrency
            ]
        ];

        $get_api_key = $this->wizpay_data_helper->getConfig('payment/wizpay/api_key');
        $wzresponse = $this->wizpay_data_helper->callCcheckoutsRredirectAapi($get_api_key, $data);
        return $wzresponse;
    }
}