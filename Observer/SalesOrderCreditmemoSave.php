<?php
namespace Wizpay\Wizpay\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer;
use \Wizpay\Wizpay\Helper\Data;

class SalesOrderCreditmemoSave implements ObserverInterface
{
  /**
   * @param EventObserver $observer
   * @return $this
   * @SuppressWarnings(PHPMD.CyclomaticComplexity)
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

    public function execute(Observer $observer)
    {
    
    //var_dump($observer); exit();

        $creditmemo = $observer->getEvent()->getCreditmemo();
        $order = $creditmemo->getOrder();
        $payment = $order->getPayment();

        if ($creditmemo->getBaseGrandTotal() <= 0) {
                
            $order->addStatusHistoryComment(__('Please enter a valid refund amount: $'. $creditmemo->getBaseGrandTotal()))->save(); // phpcs:ignore

            throw new \Magento\Framework\Exception\CouldNotDeleteException(__('Please enter a valid refund amount.')); // phpcs:ignore
        }

        if ($payment->getMethod() == 'wizpay') {

            $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
            $orderId = $order->getEntityId();
            $order = $objectManager->create('\Magento\Sales\Model\OrderRepository')->get($orderId); // phpcs:ignore
            $additionalInformation = $order->getPayment()->getAdditionalInformation();
            $wz_token = $additionalInformation['token'];
            $apiOrderId = $additionalInformation['transactionId'];
            $merchantReference = $additionalInformation['merchantReference'];
            $paymentEventMerchantReference = 'REF-' . $orderId;
            $wz_api_key = $this->helper->getConfig('payment/wizpay/api_key');
            $uniqid = hash('md5', time() . $orderId);
            $api_data = [
              'RequestId' => $uniqid,
              'merchantReference' => $merchantReference,
              'amount' => [
              'amount'=> $creditmemo->getBaseGrandTotal(),
              'currency'=> 'AUD'
              ],
              'paymentEventMerchantReference' => $paymentEventMerchantReference
            ];

            $wzresponse = $this->helper->orderRefundApi($wz_api_key, $api_data, $apiOrderId); // phpcs:ignore

            if (!is_array($wzresponse)) {

                $order->addStatusHistoryComment(__($wzresponse .' Amount: $' . $creditmemo->getBaseGrandTotal()))->save(); // phpcs:ignore
                throw new \Magento\Framework\Exception\CouldNotDeleteException(__($wzresponse)); // phpcs:ignore
            } else {

                // Save the invoice to the order
                $transaction = $objectManager->create('Magento\Framework\DB\Transaction') // phpcs:ignore
                ->addObject($creditmemo)
                ->addObject($creditmemo->getOrder());
                $transaction->save();

                $payment = $order->getPayment();
                //$payment->setTransactionId($apiOrderId); // phpcs:ignore
                $payment->setRefundTransactionId($apiOrderId.'- refund');

                $payment->setParentTransactionId($apiOrderId);
                $payment->addTransaction(\Magento\Sales\Model\Order\Payment\Transaction::TYPE_REFUND, null, 0);
                $payment->save();
                $order->save();

                $order->addStatusHistoryComment(__('Wizpay Payment Refund Authorised. Wizpay Transaction ID (' . $apiOrderId . ') Amount: $' . $creditmemo->getBaseGrandTotal()))->save(); // phpcs:ignore
                $this->messageManager->addSuccess(__("Refund has been created successfully."));
            }
        }
    }
}
