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
    private \Magento\Customer\Model\Session $customerSession;

    public function __construct(
        \Magento\Quote\Api\CartManagementInterface $cartManagement,
        \Wizpay\Wizpay\Model\Payment\Capture\CancelOrderProcessor $cancelOrderProcessor,
        \Wizpay\Wizpay\Model\Order\Payment\QuotePaidStorage $quotePaidStorage,
        \Magento\Payment\Gateway\Data\PaymentDataObjectFactoryInterface $paymentDataObjectFactory,
        \Psr\Log\LoggerInterface $logger,
        \Wizpay\Wizpay\Helper\Data $wizpay_helper,
        \Magento\Framework\View\Element\Template\Context $context,
        \Magento\Customer\Model\Session $customerSession
    ) {
        $this->cartManagement = $cartManagement;
        $this->cancelOrderProcessor = $cancelOrderProcessor;
        $this->quotePaidStorage = $quotePaidStorage;
        $this->paymentDataObjectFactory = $paymentDataObjectFactory;
        $this->logger = $logger;
        $this->wizpay_data_helper = $wizpay_helper;
        $this->customerSession = $customerSession;
    }

    public function execute(Quote $quote, string $wizpayOrderToken)
    {
        try {

            $this->logger->info("-------------->>>>>>>>>>>>>>>>Wizpay PlaceOrderProcessor start<<<<<<<<<<<<<<--------------");

            $uniqid = hash('md5', time() . $quote->getId());
            $merchantReference =  'MER' . $uniqid . '-' . $quote->getId();
            // get wizpay url
            $wzresponse = $this->getOrderData($quote, $merchantReference);

            if (isset($wzresponse) && is_array($wzresponse) && $wzresponse['responseCode'] != null
                && '200' == $wzresponse['responseCode']){
                $redirect_url = $wzresponse['redirectCheckoutUrl'];
                $wzToken = $wzresponse['token'];
                $wzTxnId = $wzresponse['transactionId'];
                
                // TODO: App payment method into quote
                $paymentMethod = $quote->getPayment();
                $data_to_store =  [
                    'token' => $wzToken,
                    'transactionId' => $wzTxnId,
                    'mer' => $merchantReference
                ];

                $paymentMethod->setTransactionId($wzTxnId);
                $paymentMethod->setParentTransactionId($paymentMethod->getTransactionId());

                $paymentMethod->setAdditionalInformation($data_to_store);
                $paymentMethod->save();
                $quote->save();


                 $this->logger->info("-------------->>>>>>>>>>>>>>>>Wizpay PlaceOrderProcessor end<<<<<<<<<<<<<<--------------");
                // return retirect url
                return $redirect_url;
            }else{
                throw new \Magento\Framework\Exception\LocalizedException(
                    __(
                        'There was a problem placing your order.'
                    )
                );
            }


        } catch (\Throwable $e) {
            $this->logger->critical('Order placement is failed with error: ' . $e->getMessage());
            throw new \Magento\Framework\Exception\LocalizedException(
                    __(
                        'There was a problem placing your order.'
                    )
            );
        }
    }




    private function getOrderData(Quote $quote, $merchantReference)
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
        $shipping_address = $quote->getShippingAddress();

        
        $successurl = $this->wizpay_data_helper->getCompleteUrl();
        $cancelurl = $this->wizpay_data_helper->getCancelledUrl();

        $success_url =  $successurl . '?mref=' . $merchantReference . '&quoteId=' . $quoteId;
        $fail_url =  $cancelurl . '?mref=' . $merchantReference . '&quoteId=' . $quoteId;

        $current_customer = $this->customerSession->getCustomer();

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

        $email = $quote->getCustomerEmail();
        if(empty($email) || $email == null){
            $email = $billingaddress->getEmail();
            $quote->setCustomerEmail($email);
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

        $first_name = $billingaddress->getFirstname();
        $last_name = $billingaddress->getLastname();

        if($first_name == null || empty($first_name)){
            $first_name = $shipping_address->getFirstname();
             $this->logger->info("firstname and last name from shipping address");
        }

        if($last_name == null || empty($last_name)){
            $last_name = $shipping_address->getLastname();
        }


        if($first_name == null || empty($first_name)){
            $first_name = $current_customer->getFirstname();
            $this->logger->info("firstname and last name from customer setting");
        }

        if($last_name == null || empty($last_name)){
            $last_name = $current_customer->getLastname();
        }

        $data = [
            "amount"=> [
                "amount"=> number_format(floatval($quote->getGrandTotal()), 2),
                "currency"=> $getStoreCurrency
            ],
            "consumer"=> [
                "phoneNumber"=> $billingaddress->getTelephone(),
                "givenNames"=> $first_name,
                "surname"=> $last_name,
                "email"=> $email
            ],
            "billing"=> [
                "name"=> $first_name,
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
                "name"=> $first_name,
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
                "amount"=> number_format(floatval($shipping_address->getShippingAmount()), 2),
                "currency"=> $getStoreCurrency
            ]
        ];

        $get_api_key = $this->wizpay_data_helper->getConfig('payment/wizpay/api_key');
        $wzresponse = $this->wizpay_data_helper->callCcheckoutsRredirectAapi($get_api_key, $data);
        return $wzresponse;
    }
}