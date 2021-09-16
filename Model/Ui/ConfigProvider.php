<?php
/**
 * Copyright © 2016 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Wizpay\Wizpay\Model\Ui;

use Magento\Checkout\Model\ConfigProviderInterface;
use Wizpay\Wizpay\Gateway\Http\Client\ClientMock;
use \Wizpay\Wizpay\Helper\Data;
use Magento\Framework\View\Asset\Repository;
use Magento\Checkout\Model\Session;
use Magento\Backend\Model\Session\Quote as adminQuoteSession;

/**
 * Class ConfigProvider
 */
class ConfigProvider implements ConfigProviderInterface // phpcs:ignore
{
    const CODE = 'wizpay';
    /**
     * @var Repository
     */
    private $assetRepository;

    /**
     * @var \Magento\Sales\Api\OrderRepositoryInterface
     */
    protected $_session;

    protected $_quote;

    /**
     * Retrieve assoc array of checkout configuration
     *
     * @return array
     */

    public function __construct(
        Repository $assetRepository,
        \Magento\Framework\App\State $state,
        Session $checkoutSession,
        Data $helper,
        adminQuoteSession $adminQuoteSession,
        \Magento\Sales\Api\OrderRepositoryInterface $orderRepository
    ) {
        $this->assetRepository = $assetRepository;
        $this->orderRepository = $orderRepository;
        $this->_checkoutSession = $checkoutSession;
        $this->helper = $helper;
        $this->_state = $state;
        if ($state->getAreaCode() == \Magento\Framework\App\Area::AREA_ADMINHTML) {
            $this->_session = $adminQuoteSession;
        } else {
            $this->_session = $checkoutSession;
        }
        $this->_quote = $this->_session->getQuote();
    }

    public function getConfig()
    {

        $sub_totalamount = (float)$this->_quote->getGrandTotal();
        //$getSubtotal = (float)$this->_quote->getSubtotal();
        $formatted_totalamount = number_format($sub_totalamount, 2, '.', '');
        //$getSubtotal1 = number_format($getSubtotal, 2, '.', '');

        $getStoreCurrency = $this->helper->getStoreCurrency();
        $banktransferLogoUrl = $this->assetRepository->getUrlWithParams('Wizpay_Wizpay::images/Group.png', []);

        return [
            'payment' => [
                'wizpay' => [
                    'wizpayLogoUrl' => $banktransferLogoUrl,
                    'urls' => $banktransferLogoUrl,
                    'subtotalamount' => $formatted_totalamount,
                    //'getSubtotal1' => $getSubtotal1,
                    'getStoreCurrency' => $getStoreCurrency,

                ]
            ]
        ];
    }
}
