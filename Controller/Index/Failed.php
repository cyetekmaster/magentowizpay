<?php

namespace Wizpay\Wizpay\Controller\Index;

use Magento\Sales\Model\Order;

/**
 * Oxipay\OxipayPaymentGateway\Controller\Checkout
 */
class Failed extends Index
{

    public function execute() // phpcs:ignore
    {
        if (!empty($this->getRequest()->getParam('orderid')) &&
        !empty($this->getRequest()->getParam('mref'))) {
            
            $orderId = $this->getRequest()->getParam('orderid');
            $merchantReference = $this->getRequest()->getParam('mref');
            $orderId = $this->getRequest()->getParam('orderid');
            $failed_url = $this->helper->getConfig('payment/wizpay/failed_url');
            $objectManager = \Magento\Framework\App\ObjectManager::getInstance();

            $order = $objectManager->create('\Magento\Sales\Model\OrderRepository')->get($orderId); // phpcs:ignore  

            $this->getCheckoutHelper()->cancelCurrentOrder("Order #".($order->getId())." Your payment through Wizpay has been cancelled."); // phpcs:ignore
            //$cartObject = $objectManager->create('Magento\Checkout\Model\Cart')->truncate();
            //$cartObject->saveQuote();
            $this->getCheckoutHelper()->restoreQuote();
            $this->getMessageManager()->addErrorMessage(__("Your payment through Wizpay has been cancelled.")); // phpcs:ignore

            if (!empty($failed_url)) {

                $this->_redirect($failed_url);
            } else {
                $this->_redirect('checkout/cart', ['_secure'=> false]);
            }
        }
    }
}
