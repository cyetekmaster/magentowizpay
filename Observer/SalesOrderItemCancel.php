<?php
namespace Wizpay\Wizpay\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use \Wizpay\Wizpay\Helper\Data;

class SalesOrderItemCancel implements ObserverInterface
{
    /**
     * Set forced CanCancel flag
     *
     * @param \Magento\Framework\Event\Observer $observer
     * @return $this
     */

    protected $messageManager;
    protected $resultRedirectFactory;

    public function __construct(
        Data $helper,
        \Magento\Framework\Message\ManagerInterface $messageManager,
        \Magento\Framework\Controller\Result\RedirectFactory $resultRedirectFactory
    ) {
        $this->helper = $helper;
        $this->messageManager = $messageManager;
        $this->resultRedirectFactory = $resultRedirectFactory;
    }

    public function execute(\Magento\Framework\Event\Observer $observer) // phpcs:ignore
    {

        $order = $observer->getOrder();
        $payment = $order->getPayment();

        if ($payment->getMethod() == 'wizpay') {

            $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
            $orderId = $order->getEntityId();
            $order = $objectManager->create('\Magento\Sales\Model\OrderRepository')->get($orderId); // phpcs:ignore
            $additionalInformation = $order->getPayment()->getAdditionalInformation();

            $apiOrderId = $additionalInformation['transactionId'];
            $wz_api_key = $this->helper->getConfig('payment/wizpay/api_key');
            
            //$wzresponse = $this->helper->orderVoidApi($wz_api_key, $apiOrderId); // phpcs:ignore

            // if (!is_array($wzresponse)) {

            //     $order->addStatusHistoryComment(__('Cancel' .$wzresponse))->save(); // phpcs:ignore
            //     throw new \Magento\Framework\Exception\CouldNotDeleteException(__($wzresponse)); // phpcs:ignore

            // } else {

                // if ('VOIDED' == $wzresponse['paymentStatus'] || 'CAPTURED' == $wzresponse['paymentStatus']) {

                    $order->addStatusToHistory(
                        'canceled',
                        'Wizpay Payment Cancel Authorised. Wizpay Transaction ID (' . $apiOrderId . ')',
                        false
                    );
                    $payment = $order->getPayment();
                    $payment->setTransactionId($apiOrderId.'-void');
                    $payment->setparentTransactionId($apiOrderId);
                    $payment->addTransaction(\Magento\Sales\Model\Order\Payment\Transaction::TYPE_VOID, null, 0);
                    $payment->save();
                    $order->save();

                    $this->messageManager->addSuccess(__("Cancel has been created successfully."));
                // }
            // }
        }
        return $this;
    } // phpcs:ignore
}
