<?php
namespace Wizpay\Wizpay\Crons;

use Wizpay\Wizpay\Helper\Data;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

class ExecuteCronJob
{
    /**
     * @var \Magento\Config\Model\ResourceModel\Config
     */

    protected $resourceConfig;
    protected $scopeConfig;

    /**
     * @param \Magento\Config\Model\ResourceModel\Config $resourceConfig
     */

    public function __construct(Data $helper, ScopeConfigInterface $scopeConfig, \Magento\Config\Model\ResourceModel\Config $resourceConfig) // phpcs:ignore
    {
        $this->helper = $helper;
        $this->scopeConfig = $scopeConfig;
        $this->resourceConfig = $resourceConfig;
    }

    public function execute()
    {

        $get_api_key = $this->helper->getConfig('payment/wizpay/api_key');
        $oldwmin = $this->helper->getConfig('payment/wizpay/min_max_wizpay/wz_min_amount');
        $oldwmax = $this->helper->getConfig('payment/wizpay/min_max_wizpay/wz_max_amount');

        if (!empty($oldwmin) && !empty($oldwmax)) {

            $wzresponse = $this->helper->call_limit_api($get_api_key);

            if (!is_array($wzresponse)) {

                throw new \Magento\Framework\Exception\ValidatorException(__($wzresponse));

            } else {

                $merchant_minimum_amount =
                $this->helper->getConfig('payment/wizpay/min_max_wizpay/merchant_min_amount');
                $merchant_maximum_amount =
                $this->helper->getConfig('payment/wizpay/min_max_wizpay/merchant_max_amount');
                $merchant_min_old =
                $this->helper->getConfig('payment/wizpay/min_max_wizpay/merchant_min_amount');
                $merchant_max_old =
                $this->helper->getConfig('payment/wizpay/min_max_wizpay/merchant_max_amount');

                $wmin = $wzresponse['minimumAmount'];
                $wmax = $wzresponse['maximumAmount'];

                //$wmin = 300;
                //$wmax = 800;

                if ($oldwmin < $wmin && $wmin < $oldwmax || $merchant_min_old < $wmin && $wmin < $merchant_max_old) {

                    $merchant_minimum_amount = $wmin;

                    $this->helper->initiateWizpayLogger('Cron Scheduler Called and Updated Wizpay minimumAmount value: '
                        .json_encode($merchant_minimum_amount));
                }

                if ($oldwmax > $wmax && $wmax > $oldwmin || $merchant_max_old > $wmax && $wmax > $merchant_min_old) {

                    $merchant_maximum_amount = $wmax;
                    $this->helper->initiateWizpayLogger(
                        'Cron Scheduler Called and Updated Wizpay maximumAmount value: '
                        .json_encode(
                            $merchant_maximum_amount
                        )
                    );
                }

                if (($oldwmin != $wmin || $oldwmax != $wmax ) ||
                     ($merchant_min_old != $merchant_minimum_amount ||
                     $merchant_max_old != $merchant_maximum_amount )) {

                    $this->resourceConfig->saveConfig(
                        'payment/wizpay/min_max_wizpay/wz_min_amount',
                        $wmin,
                        \Magento\Framework\App\ScopeInterface::SCOPE_DEFAULT,
                        0
                    );

                    $this->resourceConfig->saveConfig(
                        'payment/wizpay/min_max_wizpay/wz_max_amount',
                        $wmax,
                        \Magento\Framework\App\ScopeInterface::SCOPE_DEFAULT,
                        0
                    );

                    $recipientEmail = $this->scopeConfig->getValue(
                        'trans_email/ident_support/email',
                        ScopeInterface::SCOPE_STORE
                    );

                    $from = $recipientEmail;
                    $nameFrom = "Wizpay";
                    $nameTo = "Wizpay";
                    $message = "Hello Admin, Wizpay minimum and maximum order amount limits have been changed. 
		            Please login to your Magento store and reset Merchant Minimum Amount and Merchant Maximum Amount. 
		            Thank you!";

                    $to = [$recipientEmail, $recipientEmail];
                    $email = new \Zend_Mail();
                    $email->setSubject("Wizpay Transaction Limits Change Notification On Your Magento Store");
                    $email->setBodyText($message);
                    $email->setFrom($from, $nameFrom);
                    $email->addTo($recipientEmail, $nameTo);
                    $email->send();

                    $this->helper->initiateWizpayLogger(
                        'Notification Email sent successfully to Magento Store Admin: '
                        .json_encode(
                            $email
                        )
                    );

                    /*throw new \Magento\Framework\Exception\CouldNotDeleteException(__(
                        'Warning: Wizpay minimum and maximum order amount limits have been changed.'
                    ));*/
                }
            }
        }
    }
}
