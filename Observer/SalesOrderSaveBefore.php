<?php
namespace Wizpay\Wizpay\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

class SalesOrderSaveBefore implements ObserverInterface
{
    /**
     * Set forced canCreditmemo flag
     *
     * @param \Magento\Framework\Event\Observer $observer
     * @return $this
     */
    public function execute(\Magento\Framework\Event\Observer $observer) // phpcs:ignore
    {

      /* $order = $observer->getOrder();

      $shipping_address = $order->getShippingAddress();
      echo "<pre>"; print_r($shipping_address);exit;
      $order = $observer->getEvent()->getOrder();
      $orderId = $order->getEntityId();
      echo "<pre>"; print_r($orderId);exit;

      echo "saasd";exit; */
    } // phpcs:ignore
}
