<?php

namespace Wizpay\Wizpay\Helper;

require_once('access.php');

use \Magento\Framework\App\Helper\AbstractHelper;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\Locale\ResolverInterface;
use Magento\Payment\Helper\Data as PaymentData;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Framework\Mail\TransportInterfaceFactory;




class Data extends AbstractHelper
{
    protected $logger;

    /**
     * @var \Magento\Framework\ObjectManagerInterface
     */
    protected $_objectManager;
    /**
     * @var \Magento\Payment\Helper\Data
     */
    protected $_paymentData;
    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $_storeManager;
    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $_scopeConfig;
    /**
     * @var \Magento\Framework\Locale\ResolverInterface
     */
    protected $_localeResolver;

    protected $curlClient;

    private $wizpay_url_manager;

    /**
     * @param \Magento\Framework\ObjectManagerInterface $objectManager
     * @param \Magento\Framework\App\Helper\Context $context
     * @param \Magento\Payment\Helper\Data $paymentData
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager,
     * @param \Magento\Framework\Locale\ResolverInterface $localeResolver
     */
    public function __construct(
        ObjectManagerInterface $objectManager,
        Context $context,
        PaymentData $paymentData,
        StoreManagerInterface $storeManager,
        ResolverInterface $localeResolver,
        TransportBuilder $transportBuilder,
        TransportInterfaceFactory $mailTransportFactory,
        \Magento\Framework\HTTP\Client\Curl $curl,
        \Psr\Log\LoggerInterface $logger
    ) {
        //$this->_gatewayConfig = $gatewayConfig;
        $this->_objectManager = $objectManager;
        $this->_paymentData   = $paymentData;
        $this->_storeManager  = $storeManager;
        $this->_localeResolver = $localeResolver;
        $this->curlClient = $curl;
        $this->transportBuilder = $transportBuilder;
        $this->mailTransportFactory = $mailTransportFactory;
        $this->_scopeConfig   = $context->getScopeConfig();

        $this->logger = $logger;

        $this->wizpay_url_manager = new WizpayUrlAccessManager();

        parent::__construct($context);
    }

    public function initiateWizpayLogger($log)
    {
        $this->logger->info($log);
    }

    public function createWcog($apiresult)
    {
        $capture = $this->getConfig('payment/wizpay/capture');
        $getAmount = @$apiresult['originalAmount']; // phpcs:ignore
        $amount = @$getAmount['amount']; // phpcs:ignore
        $logdata = ['CaptureSettings' =>$capture,
            'merchantReference'     => @$apiresult['merchantReference'], // phpcs:ignore
            'WZTransactionID'       => @$apiresult['transactionId'], // phpcs:ignore
            'paymentDescription'    => @$apiresult['paymentDescription'], // phpcs:ignore
            'responseCode'          => @$apiresult['responseCode'], // phpcs:ignore
            'errorCode'             => @$apiresult['errorCode'], // phpcs:ignore
            'Amount'                => '$'. $amount,
            'errorMessage'          => @$apiresult['errorMessage'], // phpcs:ignore
            'transactionStatus'     => @$apiresult['transactionStatus'], // phpcs:ignore
            'paymentStatus'         => @$apiresult['paymentStatus']]; // phpcs:ignore
        
        $this->initiateWizpayLogger(json_encode($logdata));
    }

    public function getStoreCurrency()
    {

        return $this->_storeManager->getStore()->getBaseCurrencyCode();
    }
    
    public function getConfig($config_path)
    {

        return $this->scopeConfig->getValue($config_path, \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
    }

    public function transaction()
    {
        return $this->_transaction;
    }

    public function transportBuilder()
    {
        return $this->transportBuilder;
    }

    public function mailTransportFactory()
    {
        return $this->mailTransportFactory;
    }

    /**
     * Get an Instance of the Magento Store Manager
     * @return \Magento\Store\Model\StoreManagerInterface
     */
    protected function getStoreManager()
    {
        return $this->_storeManager;
    }

    /**
     * @throws NoSuchEntityException If given store doesn't exist.
     * @return string
     */
    public function getCompleteUrl()
    {
        return $this->getStoreManager()->getStore()->getBaseUrl() . 'wizpay/index/success';
    }

    /**
     * @param string
     * @throws NoSuchEntityException If given store doesn't exist.
     * @return string
     */
    public function getCancelledUrl()
    {
        return $this->getStoreManager()->getStore()->getBaseUrl() . "wizpay/index/failed";
    }
    // private function apiUrl() {
    
    //  return 'https://uatapi.wizardpay.com.au/v1/api/';
    // }
    private function apiUrl()
    {        
        return $this->wizpay_url_manager->GetApiUrl();
    }

    public function getCurlClient()
    {
        return $this->curlClient;
    }

    private function getWizpayapi($url, $apikey)
    {
        
        try {

            //$api_key = $this->getConfig('payment/opmc_wizpay/api_key');
            
            // $this->getCurlClient()->setOption(CURLOPT_RETURNTRANSFER, true);
            $this->getCurlClient()->setOption(CURLOPT_SSL_VERIFYHOST, false);
            $this->getCurlClient()->setOption(CURLOPT_SSL_VERIFYPEER, false);

            // $curl_options[CURLOPT_SSL_VERIFYHOST] = false;
            // $curl_options[CURLOPT_SSL_VERIFYPEER] = false;
    
            $headers = ["Content-Type" => "application/json", "Api-Key" => $apikey];
            $this->getCurlClient()->setHeaders($headers);

            $this->getCurlClient()->get($url);

            $response = $this->getCurlClient()->getBody();

            $finalresult = json_decode($response, true);
                
            // // echo "<pre>";
            // var_dump($finalresult);
            // var_dump($response);
            // var_dump($headers);
            // die('asfs');
            if (json_last_error() !== JSON_ERROR_NONE) {
                $error = true;
                $errormessage = 'Error: Invalid Json Format received from Wizpay API. Please contact customer support in this regard!!'; // phpcs:ignore
                return $errormessage;
            }
            return $finalresult;

        } catch (\Exception $e) {
            return $e->getMessage();
        }
    }

    private function postWizpayapi($url, $requestbody, $apikey)
    {
        $this->getCurlClient()->setOption(CURLOPT_RETURNTRANSFER, true);
        $this->getCurlClient()->setOption(CURLOPT_SSL_VERIFYHOST, false);
        $this->getCurlClient()->setOption(CURLOPT_SSL_VERIFYPEER, false);


        try {
            $postdata = json_encode($requestbody);
            $headers = ["Content-Type" => "application/json", "Api-Key" => $apikey];
            $this->getCurlClient()->setOption(CURLOPT_RETURNTRANSFER, true);
            $this->getCurlClient()->setHeaders($headers);
            $this->getCurlClient()->post($url.'?timeout=80&sslverify=false', $postdata);
            $response = $this->getCurlClient()->getBody();
            $finalresult = json_decode($response, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                $error = true;
                $errormessage = 'Error: Invalid Json Format received from Wizpay API. Please contact customer support in this regard!!'; // phpcs:ignore
                return $errormessage;
            }

            return $finalresult;

        } catch (\Exception $e) {

            return $e->getMessage();
        }
    }

    public function callLimitapi($apikey)
    {
        $error = false;
        $actualapicall = 'GetPurchaseLimit';
        $finalapiurl = $this->apiUrl() . $actualapicall;
        //$finalapiurl = 'http://mywp.preyansh.in/wzapi.php';
        $apiresult = $this->getWizpayapi($finalapiurl, $apikey);
        $this->initiateWizpayLogger('callLimitapi() function called'.PHP_EOL);
        // echo $finalapiurl;
        // echo "<Pre>";
        // print_r($apiresult);
        // die('asd');
        if ('' == $apiresult) {
            $error = true;
            $errormessage = 'Error: Looks like your Website IP Address is not white-listed in Wizpay. Please connect with Wizpay support team!'; // phpcs:ignore
            $apiresult = $errormessage;
            $this->initiateWizpayLogger('failure:'.$apiresult);

        } elseif (false !== $apiresult && '200' == $apiresult['responseCode']) {

            $this->initiateWizpayLogger('success:'.json_encode($apiresult));

        } elseif ('402' == $apiresult['responseCode'] || '412' == $apiresult['responseCode']) {
            $error = true;
            $errormessage = 'Call Transaction Limit Error: ' . $apiresult['errorCode'] . ' - ' . $apiresult['errorMessage']; // phpcs:ignore
            
            $apiresult = $errormessage;
            $this->initiateWizpayLogger('failure:'.$apiresult);
            
        } else {
            $error = true;
            $errormessage = 'Error: Please enter a valid Wizpay API Key!';
            $apiresult = $errormessage;
            $this->initiateWizpayLogger('failure:'.$apiresult);
        }
        return $apiresult;
    }

    public function callCcheckoutsRredirectAapi($apikey, $requestbody)
    {
        $error = false;
        $actualapicall = 'transactioncheckouts';
        $finalapiurl = $this->apiUrl() . $actualapicall;
        //$finalapiurl = 'http://mywp.preyansh.in/wzapi.php';
        
        $apiresult = $this->postWizpayapi($finalapiurl, $requestbody, $apikey);

        $this->initiateWizpayLogger('callCcheckoutsRredirectAapi() function called'.PHP_EOL);
        $this->createWcog($apiresult);

        if (isset($apiresult['errors']) && $apiresult['status'] == '400') {

            $error = true;
            $errormessage = 'Checkout Redirect Error: ' . 'Invalid address or One or more validation errors occurred.';
            
            $apiresult = $errormessage;
            $this->initiateWizpayLogger('failure:'.$apiresult);
            
        } elseif (isset($apiresult) && '200' == $apiresult['responseCode'] && isset($apiresult['responseCode'])) {
            $this->initiateWizpayLogger('success:'.json_encode($apiresult));

            $this->initiateWizpayLogger('API return success' . PHP_EOL);

        } elseif ('402' == $apiresult['responseCode'] || '412' == $apiresult['responseCode'] && isset($apiresult['responseCode'])) { // phpcs:ignore

            $error = true;
            $errormessage = 'Checkout Redirect Error: ' . $apiresult['errorCode'] . ' - ' . $apiresult['errorMessage'];
            
            $apiresult = $errormessage;
            $this->initiateWizpayLogger('failure:'.$apiresult);
            
        } else {
            $error = true;
            $errormessage = 'Checkout Redirect Error: ' . $apiresult['responseCode'];
            $apiresult = $errormessage;
            $this->initiateWizpayLogger('failure:'.$apiresult);
        }
        return $apiresult;
    }

    public function getOrderPaymentStatusApi($apikey, $requestbody)
    {
        $actualapicall = 'Payment/transactionstatus';
        $finalapiurl = $this->apiUrl() . $actualapicall;
        //print_r($apikey);
        $apiresult = $this->postWizpayapi($finalapiurl, $requestbody, $apikey);
        $this->initiateWizpayLogger('TransactionStatus api called'.PHP_EOL);
        $this->createWcog($apiresult);

        if (false !== $apiresult && '200' == $apiresult['responseCode']) {
            //print_r($apiresult);
            $errormessage = '';
            $responseerror = $this->handleOrderPaymentStatusApiError($apiresult, $errormessage);

            if (!empty($responseerror)) {
                
                $apiresult = $responseerror;
                $this->initiateWizpayLogger('Order Status Error: '.$apiresult);
            } else {
               
                $this->initiateWizpayLogger('API return success' . PHP_EOL);
            }

        } elseif ('402' == $apiresult['responseCode'] || '412' == $apiresult['responseCode']) {
            $error = true;
            $errormessage = 'Order Status Error: ' . $apiresult['errorCode'] . ' - ' . $apiresult['errorMessage'] . ' - ' . $apiresult['paymentDescription']; // phpcs:ignore
            $apiresult = $errormessage;
             $this->initiateWizpayLogger('Order Status Error: '.$apiresult);
        } else {
            $error = true;
            $errormessage = 'Order Status Error: ' . $apiresult['errorCode'] . ' - ' . $apiresult['errorMessage'];
            $apiresult = $errormessage;
            $this->initiateWizpayLogger('Order Status Error: '.$apiresult);
        }
        return $apiresult;
    }

    public function handleOrderPaymentStatusApiError($apiresult, $errormessage)
    {
        $errormessage = '';
        $apiOrderId = $apiresult['transactionId'];
        if ('APPROVED' != $apiresult['transactionStatus'] && 'COMPLETED' != $apiresult['transactionStatus']) {

            $errormessage = 'Wizpay Payment Failed. Wizpay Transaction ' . $apiOrderId . ' has been Declined';
        }

        if ('AUTH_APPROVED' != $apiresult['paymentStatus'] && 'CAPTURED' != $apiresult['paymentStatus'] && 'PARTIALLY_CAPTURED' != $apiresult['paymentStatus']) { // phpcs:ignore
            $orderMessage = '';
            if ('AUTH_DECLINED' == $apiresult['paymentStatus']) {
                $errormessage = 'Wizpay Payment Failed. Wizpay Transaction ' . $apiOrderId . ' has been Declined';
            } elseif ('CAPTURE_DECLINED' == $apiresult['paymentStatus']) {
                $errormessage = 'Wizpay Transaction Wizpay Transaction ' . $apiOrderId . ' Capture Attempt has been declined'; // phpcs:ignore
            } elseif ('VOIDED' == $apiresult['paymentStatus']) {
                $errormessage = 'Wizpay Transaction ' . $apiOrderId . ' VOID';
            } else {
                $errormessage = 'Wizpay Transaction ' . $apiOrderId . ' Payment Failed.';
            }
        }
        return $errormessage;
    }

    public function immediatePaymentCapture($apikey, $requestbody)
    {
        $actualapicall = 'Payment/transactioncapture';
        $finalapiurl = $this->apiUrl() . $actualapicall;
        
        $apiresult = $this->postWizpayapi($finalapiurl, $requestbody, $apikey);

        $this->initiateWizpayLogger('TransactionCapture (Immediate Capture) api called' . PHP_EOL);
        $this->createWcog($apiresult);

        if (false !== $apiresult && '200' == $apiresult['responseCode']) {
            
            $errormessage = '';
            $responseerror = $this->handleImmediatePaymentCaptureError($apiresult, $errormessage);

            if (!empty($responseerror)) {
                
                $apiresult = $responseerror;
                $this->initiateWizpayLogger('Order Status Error: '.$apiresult);
            } else {
               
                $this->initiateWizpayLogger('API return success' . PHP_EOL);
            }

        } elseif ('402' == $apiresult['responseCode'] || '412' == $apiresult['responseCode']) {
            $error = true;
            $errormessage = 'Immediate Payment Capture Error: ' . $apiresult['errorCode'] . ' - ' . $apiresult['errorMessage'] . ' - ' . $apiresult['paymentDescription']; // phpcs:ignore
            $this->initiateWizpayLogger($errormessage);
            $apiresult = $errormessage;
        } else {
            $error = true;
            $errormessage = 'Immediate Payment Capture Error: ' . $apiresult['errorCode'] . ' - ' . $apiresult['errorMessage'] . ' - ' . $apiresult['paymentDescription']; // phpcs:ignore
            $this->initiateWizpayLogger($errormessage);
            $apiresult = $errormessage;
        }
        return $apiresult;
    }

    public function handleImmediatePaymentCaptureError($apiresult, $errormessage)
    {
        $error = true;
        $apiOrderId = $apiresult['transactionId'];
        if ('APPROVED' != $apiresult['transactionStatus'] && 'COMPLETED' != $apiresult['transactionStatus']) {

            $errormessage = 'Wizpay Payment Failed. Wizpay Transaction ' . $apiOrderId . ' has been Declined';
            $this->initiateWizpayLogger($errormessage);
        }

        if ('3005' == $apiresult['errorCode']) {

            $errormessage = 'Wizpay Payment Failed. Wizpay Transaction ' . $apiOrderId . ' Reason: ' . $apiresult['errorMessage']; // phpcs:ignore
            $this->initiateWizpayLogger($errormessage);

        }

        if ('3008' == $apiresult['errorCode']) {

            $errormessage = 'Wizpay Payment Failed. Wizpay Transaction ' . $apiOrderId . ' Reason: ' . $apiresult['errorMessage']; // phpcs:ignore
            $this->initiateWizpayLogger($errormessage);

        }

        if ('3006' == $apiresult['errorCode']) {

            $errormessage = 'Wizpay Payment Failed. Wizpay Transaction ' . $apiOrderId . ' Reason: ' . $apiresult['errorMessage']; // phpcs:ignore
            $this->initiateWizpayLogger($errormessage);
            
        }

        if ('AUTH_APPROVED' != $apiresult['paymentStatus'] &&
        'CAPTURED' != $apiresult['paymentStatus'] &&
        'CAPTURE_DECLINED' != $apiresult['paymentStatus']) {
            $orderMessage = '';
            if ('AUTH_DECLINED' == $apiresult['paymentStatus']) {
                $errormessage = 'Wizpay Payment Failed. Wizpay Transaction ' . $apiOrderId . ' has been Declined';
            } elseif ('CAPTURE_DECLINED' == $apiresult['paymentStatus']) {
                $errormessage = 'Wizpay Transaction ' . $apiOrderId . ' Capture Attempt has been declined';
            } elseif ('VOIDED' == $apiresult['paymentStatus']) {
                $errormessage = 'Wizpay Transaction ' . $apiOrderId . ' VOID';
            } else {
                $errormessage = 'Wizpay Transaction ' . $apiOrderId . ' Payment Failed.';
            }
            $this->initiateWizpayLogger($errormessage);
            
        }
        return $errormessage;
    }

    public function orderPartialCaptureApi($apikey, $requestbody, $apiOrderId)
    {
        $actualapicall = 'Payment/transactioncapture/' . $apiOrderId;
        $finalapiurl = $this->apiUrl() . $actualapicall;
        
        $apiresult = $this->postWizpayapi($finalapiurl, $requestbody, $apikey);
       
        $this->initiateWizpayLogger('TransactionCapture (Partial Capture) api called' . PHP_EOL);
        $this->createWcog($apiresult);

        if (false !== $apiresult && '200' == $apiresult['responseCode']) {
            
            $errormessage = '';
            $responseerror = $this->handlePartialPaymentCaptureError($apiresult, $errormessage);

            if (!empty($responseerror)) {
                
                $apiresult = $responseerror;
                $this->initiateWizpayLogger('Order Status Error: '.$apiresult);
            } else {
               
                $this->initiateWizpayLogger('API return success' . PHP_EOL);
            }

        } elseif ('402' == $apiresult['responseCode'] || '412' == $apiresult['responseCode']) {
            $error = true;
            $errormessage = 'Partial Payment Capture Error: ' . $apiresult['errorCode'] . ' - ' . $apiresult['errorMessage'] . ' - ' . $apiresult['paymentDescription']; // phpcs:ignore
            $this->initiateWizpayLogger($errormessage);
            $apiresult = $errormessage;
        } else {
            $error = true;
            $errormessage = 'Partial Payment Capture Error: ' . $apiresult['errorCode'] . ' - ' . $apiresult['errorMessage'] . ' - ' . $apiresult['paymentDescription']; // phpcs:ignore
            $this->initiateWizpayLogger($errormessage);
            $apiresult = $errormessage;
        }
        return $apiresult;
    }

    public function handlePartialPaymentCaptureError($apiresult, $errormessage)
    {
        $error = true;
        $apiOrderId = $apiresult['transactionId'];
        if ('APPROVED' != $apiresult['transactionStatus'] && 'COMPLETED' != $apiresult['transactionStatus']) {

            $errormessage = 'Wizpay Payment Failed. Wizpay Transaction ' . $apiOrderId . ' has been Declined';
            $this->initiateWizpayLogger($errormessage);
        }

        if ('3005' == $apiresult['errorCode']) {

            $errormessage = 'Wizpay Payment Failed. Wizpay Transaction ' . $apiOrderId . ' Reason: ' . $apiresult['errorMessage']; // phpcs:ignore
            $this->initiateWizpayLogger($errormessage);

        }

        if ('3008' == $apiresult['errorCode']) {

            $errormessage = 'Wizpay Payment Failed. Wizpay Transaction ' . $apiOrderId . ' Reason: ' . $apiresult['errorMessage']; // phpcs:ignore
            $this->initiateWizpayLogger($errormessage);

        }

        if ('3006' == $apiresult['errorCode']) {

            $errormessage = 'Wizpay Payment Failed. Wizpay Transaction ' . $apiOrderId . ' Reason: ' . $apiresult['errorMessage']; // phpcs:ignore
            $this->initiateWizpayLogger($errormessage);
            
        }

        if ('AUTH_APPROVED' != $apiresult['paymentStatus'] && 'PARTIALLY_CAPTURED' != $apiresult['paymentStatus'] && 'CAPTURED' != $apiresult['paymentStatus'] && 'CAPTURE_DECLINED' != $apiresult['paymentStatus']) { // phpcs:ignore
            $orderMessage = '';
            if ('AUTH_DECLINED' == $apiresult['paymentStatus']) {
                $errormessage = 'Wizpay Payment Failed. Wizpay Transaction ' . $apiOrderId . ' has been Declined';
            } elseif ('CAPTURE_DECLINED' == $apiresult['paymentStatus']) {
                $errormessage = 'Wizpay Transaction ' . $apiOrderId . ' Capture Attempt has been declined';
            } elseif ('VOIDED' == $apiresult['paymentStatus']) {
                $errormessage = 'Wizpay Transaction ' . $apiOrderId . ' VOID';
            } else {
                $errormessage = 'Wizpay Transaction ' . $apiOrderId . ' Payment Failed.';
            }
            $this->initiateWizpayLogger($errormessage);
            
        }
        return $errormessage;
    }

    public function orderRefundApi($apikey, $requestbody, $apiOrderId)
    {

        $actualapicall = 'Payment/refund/' . $apiOrderId;
        $finalapiurl = $this->apiUrl() . $actualapicall;

        $apiresult = $this->postWizpayapi($finalapiurl, $requestbody, $apikey);
        $this->initiateWizpayLogger('Refund api called' . PHP_EOL);
        $this->createWcog($apiresult);

        if (false !== $apiresult && '200' == $apiresult['responseCode']) {
            
            $errormessage = '';

            $responseerror = $this->handleOrderRefundApiError($apiresult, $errormessage);

            if (!empty($responseerror)) {
                
                $apiresult = $responseerror;
                $this->initiateWizpayLogger('Order Refund Error: '.$apiresult);
            } else {
               
                $this->initiateWizpayLogger('API return success' . PHP_EOL);
            }

        } elseif ('402' == $apiresult['responseCode'] || '412' == $apiresult['responseCode']) {
            $error = true;
            $errormessage = 'Error: ' . $apiresult['errorCode']
            . ' - ' . $apiresult['errorMessage']
            . ' - ' . $apiresult['paymentDescription'];
            $this->initiateWizpayLogger($errormessage);
            $apiresult = $errormessage;
        } else {
            $error = true;
            $errormessage = 'Error: ' . $apiresult['errorCode'] . ' - ' . $apiresult['errorMessage'];
            $this->initiateWizpayLogger($errormessage);
            $apiresult = $errormessage;
        }
        return $apiresult;
    }

    public function handleOrderRefundApiError($apiresult, $errormessage)
    {
        $error = true;
        $apiOrderId = $apiresult['transactionId'];
        if ('APPROVED' != $apiresult['transactionStatus'] && 'COMPLETED' != $apiresult['transactionStatus']) {

            $errormessage = 'Wizpay Payment Failed. Wizpay Transaction ' . $apiOrderId . ' has been Declined';
            $this->initiateWizpayLogger($errormessage);
        }

        if ('AUTH_APPROVED' != $apiresult['paymentStatus'] &&
            'PARTIALLY_CAPTURED' != $apiresult['paymentStatus'] &&
            'CAPTURED' != $apiresult['paymentStatus']) {
            $orderMessage = '';
            if ('AUTH_DECLINED' == $apiresult['paymentStatus']) {
                $errormessage = 'Wizpay Payment Failed. Wizpay Transaction '
                . $apiOrderId . ' has been Declined';
           
            } elseif ('VOIDED' == $apiresult['paymentStatus']) {
                $errormessage = 'Wizpay Transaction ' . $apiOrderId . ' VOID';
            } else {
                $errormessage = 'Wizpay Transaction ' . $apiOrderId . ' Payment Failed.';
            }
            $this->initiateWizpayLogger($errormessage);
        }
        return $errormessage;
    }

    public function orderVoidApi($apikey, $wz_txn_id)
    {
        
        $actualapicall = 'Payment/voidtransaction/' . $wz_txn_id;
        $finalapiurl = $this->apiUrl() . $actualapicall;
        
        $apiresult = $this->postWizpayapi($finalapiurl, $wz_txn_id, $apikey);
        $this->initiateWizpayLogger('Cancel api called' . PHP_EOL);
        $this->createWcog($apiresult);

        if (false !== $apiresult && '200' == @$apiresult['responseCode']) { // phpcs:ignore

            $errormessage = '';
            $responseerror = $this->handleOrderVoidedApiError($apiresult, $errormessage);
            if (!empty($responseerror)) {
                
                $apiresult = $responseerror;
                $this->initiateWizpayLogger('Order Cancel Error: '.$apiresult);
            } else {
               
                $this->initiateWizpayLogger('API return success' . PHP_EOL);
            }

        } elseif ('412' == @$apiresult['responseCode']) { // phpcs:ignore
            $error = true;
            $errormessage = 'Cancel attempt failed because payment has already been captured for this order';
            $this->initiateWizpayLogger($errormessage);
            $apiresult = $errormessage;

        } elseif ('402' == @$apiresult['responseCode']) { // phpcs:ignore
            $error = true;
            $errormessage = 'Error: ' . @$apiresult['errorCode'] . ' - ' . @$apiresult['errorMessage'] . ' - ' . @$apiresult['paymentDescription']; // phpcs:ignore
            $this->initiateWizpayLogger($errormessage);
            $apiresult = $errormessage;
        } else {
            $error = true;
            $errormessage = 'Error: ' . @$apiresult['errorCode'] . ' - ' . @$apiresult['errorMessage']; // phpcs:ignore
            $this->initiateWizpayLogger($errormessage);
            $apiresult = $errormessage;
        }
        return $apiresult;
    }

    public function handleOrderVoidedApiError($apiresult, $errormessage)
    {
        $error = true;
        $apiOrderId = $apiresult['transactionId'];
        if ('COMPLETED' != $apiresult['transactionStatus'] &&
            'COMPLETED' != $apiresult['transactionStatus']) {

            $errormessage = "Wizpay Payment cancel doesn't authorised. Wizpay Transaction " . $apiOrderId . '  has been Declined!'; // phpcs:ignore
            $this->initiateWizpayLogger($errormessage);
        }

        if ('VOIDED' != $apiresult['paymentStatus'] && 'CAPTURED' != $apiresult['paymentStatus']) {
            $orderMessage = '';
               
            $errormessage = 'Wizpay Transaction ' . $apiOrderId . ' Payment Cancel Failed';
            $this->initiateWizpayLogger($errormessage);
        }
        return $errormessage;
    }
}
