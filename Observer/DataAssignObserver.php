<?php

// namespace Wizpay\Wizpay\Observer;

// use \Wizpay\Wizpay\Helper\Data;
// use Magento\Framework\Event\Observer as EventObserver;
// use Magento\Framework\Event\ObserverInterface;
// use \Magento\Framework\App\Config\ScopeConfigInterface as scopeConfig;
// use Magento\Checkout\Model\Session;
// use Magento\Backend\Model\Session\Quote as adminQuoteSession;
// use Magento\Store\Model\ScopeInterface;

// class DataAssignObserver implements ObserverInterface
// {
//     protected $_state;
//     protected $_session;
//     protected $_quote;
//     private $storeManager;
//     /**
//      * Get country path
//      */
//     const COUNTRY_CODE_PATH = 'general/country/default';

//     public function __construct(
//         \Magento\Framework\App\State $state,
//         Session $checkoutSession,
//         \Magento\Store\Model\StoreManagerInterface $storeManager,
//         Data $helper,
//         scopeConfig $scopeConfig,
//         adminQuoteSession $adminQuoteSession
//     ) {
//         $this->storeManager = $storeManager;
//         $this->_state = $state;
//         $this->scopeConfig = $scopeConfig;
//         $this->helper = $helper;
        
//         if ($state->getAreaCode() == \Magento\Framework\App\Area::AREA_ADMINHTML) {
//             $this->_session = $adminQuoteSession;
//         } else {
//             $this->_session = $checkoutSession;
//         }
//         $this->_quote = $this->_session->getQuote();
//     }
//     /**
//      * payment_method_is_active event handler.  *
//      * @param \Magento\Framework\Event\Observer $observer
//      */

//     public function execute(EventObserver $observer)
//     {

//         $billingData = $this->_quote->getBillingAddress()->getCountryId();
//         $shippingData = $this->_quote->getShippingAddress()->getCountryId();
//         $code = $observer->getEvent()->getMethodInstance()->getCode();

//         if (!$code) {
//             return ;
//         }

//         $getCurrentCurrencyCode = $this->storeManager->getStore()->getCurrentCurrencyCode();
//         $sub_totalamount = $this->_quote->getGrandTotal();
//         $method = $observer->getEvent()->getMethodInstance();
//         $getStoreCurrency = $this->helper->getStoreCurrency();
        
//         $merchant_min_amount = $this->scopeConfig->getValue('payment/wizpay/min_max_wizpay/merchant_min_amount', \Magento\Store\Model\ScopeInterface::SCOPE_STORE); // phpcs:ignore
//         $merchant_max_amount = $this->scopeConfig->getValue('payment/wizpay/min_max_wizpay/merchant_max_amount', \Magento\Store\Model\ScopeInterface::SCOPE_STORE); // phpcs:ignore

//         if (!($sub_totalamount >= $merchant_min_amount && $sub_totalamount <= $merchant_max_amount)) {
//             if ($code == 'wizpay') {

//                 $checkResult = $observer->getEvent()->getResult();
//                 $checkResult->setData('is_available', false);
//                 return;
//             }
//         }
//     }
// }
