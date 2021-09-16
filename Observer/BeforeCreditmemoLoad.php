<?php
namespace Wizpay\Wizpay\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer;
use \Wizpay\Wizpay\Helper\Data;

class BeforeCreditmemoLoad implements ObserverInterface
{
    //protected $_helper;
    protected $_layout;
    protected $_registry;

    public function __construct(
        Data $helper,
        \Magento\Framework\View\LayoutInterface $layout,
        \Magento\Framework\Registry $registry
    ) {
        $this->helper = $helper;
        $this->_layout = $layout;
        $this->_registry = $registry;
    }

    public function execute(Observer $observer)
    {
        $block = $observer->getEvent()->getBlock();
        $layout = $block->getLayout();

        $creditmemo = $this->_registry->registry('current_creditmemo');

        if ($creditmemo) {

            $order = $creditmemo->getOrder();
            $payment = $order->getPayment();

            if ($payment->getMethod() == 'wizpay') {

                $block->unsetChild('submit_offline');
            }
        }
    }
}
