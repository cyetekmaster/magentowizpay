<?php declare(strict_types=1);

namespace Wizpay\Wizpay\Observer\Payment;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer;

class PaymentMethodAvailable implements ObserverInterface {
    private $logger;
    private $wizpay_data_helper;
    private $cartManagement;
    private $session;

    public function __construct(
        \Psr\Log\LoggerInterface $logger,
        \Wizpay\Wizpay\Helper\Data $wizpay_helper,
        \Magento\Quote\Api\CartManagementInterface $cartManagement,
        \Magento\Checkout\Model\Session $session
    ) {

        $this->logger = $logger;
        $this->wizpay_data_helper = $wizpay_helper;
        $this->cartManagement = $cartManagement;
        $this->session = $session;
    }

    /**
     * @param Observer $observer
     *
     * @return void
     */
    public function execute(Observer $observer) {
        //Replace this code with your own checks.  This code is checking a customer attribute.  If they are not approved for billing terms, the purchaseorder method is turned off.

        $result = $observer->getEvent()->getResult();
        $method_instance = $observer->getEvent()->getMethodInstance()->getCode();
        $quote = $this->session->getQuote();
        if($method_instance == 'wizpay') {
            $this->logger->info("-------------------->>>>>>>>>>>>>>>>>>WIZPAY  Check PaymentMethodAvailable Start<<<<<<<<<<<<<<<<<<<<-------------------");
            // get total
            $total = floatval($quote->getGrandTotal());
            // get min and max
            // get limit
            $wizpay_minimum_payment_amount = $this->wizpay_data_helper->getConfig('payment/wizpay/min_max_wizpay/wz_min_amount');
            $wizpay_maxmum_payment_amount = $this->wizpay_data_helper->getConfig('payment/wizpay/min_max_wizpay/wz_max_amount');


            $wizpay_merchant_min_amount =  $this->wizpay_data_helper->getConfig('payment/wizpay/min_max_wizpay/merchant_min_amount');
            $wizpay_merchant_max_amount =  $this->wizpay_data_helper->getConfig('payment/wizpay/min_max_wizpay/merchant_max_amount');

            if (empty($wizpay_merchant_min_amount) || empty($wizpay_merchant_max_amount))
            {
                $wizpay_merchant_min_amount = $wizpay_minimum_payment_amount;
                $wizpay_merchant_max_amount = $wizpay_maxmum_payment_amount;
            }

            $m_min = floatval($wizpay_merchant_min_amount);
            $m_max = floatval($wizpay_merchant_max_amount);


            // check limit
            if($total < $m_min ||  $total > $m_max){
                $result->setData('is_available', false);
                $this->logger->info("wizpay turned off");
                $this->logger->info("wizpay order total=" . $total . ', Min=' . $m_min . ', Max=' . $m_max);
            }

            $this->logger->info("-------------------->>>>>>>>>>>>>>>>>>WIZPAY  Check PaymentMethodAvailable End<<<<<<<<<<<<<<<<<<<<-------------------");
        }


    }


}