<?php
/**
 *
 * @package     magento2
 * @author
 * @license     https://opensource.org/licenses/OSL-3.0 Open Software License v. 3.0 (OSL-3.0)
 * @link
 */

 namespace Wizpay\Wizpay\Controller\Index;


class Index implements \Magento\Framework\App\Action\HttpGetActionInterface
{
    const CHECKOUT_STATUS_CANCELLED = 'CANCELLED';
    const CHECKOUT_STATUS_SUCCESS = 'SUCCESS';

    private \Magento\Framework\App\RequestInterface $request;
    private \Magento\Checkout\Model\Session $session;
    private \Magento\Framework\Controller\Result\RedirectFactory $redirectFactory;
    private \Magento\Framework\Message\ManagerInterface $messageManager;
    private \Wizpay\Wizpay\Model\Payment\Capture\PlaceOrderProcessor $placeOrderProcessor;
    private \Magento\Payment\Gateway\CommandInterface $validateCheckoutDataCommand;
    private \Psr\Log\LoggerInterface $logger;

    public function __construct(
        \Magento\Framework\App\RequestInterface $request,
        \Magento\Checkout\Model\Session $session,
        \Magento\Framework\Controller\Result\RedirectFactory $redirectFactory,
        \Magento\Framework\Message\ManagerInterface $messageManager,
        \Wizpay\Wizpay\Model\Payment\Capture\PlaceOrderProcessor $placeOrderProcessor,
        \Psr\Log\LoggerInterface $logger
    ) {
        $this->request = $request;
        $this->session = $session;
        $this->redirectFactory = $redirectFactory;
        $this->messageManager = $messageManager;
        $this->placeOrderProcessor = $placeOrderProcessor;
        $this->logger = $logger;
    }

    public function execute()
    {
        if ($this->request->getParam('status') == self::CHECKOUT_STATUS_CANCELLED) {
            $this->messageManager->addErrorMessage(
                (string)__('You have cancelled your Wizpay payment. Please select an alternative payment method.')
            );
            return $this->redirectFactory->create()->setPath('checkout/cart');
        }

        if ($this->request->getParam('status') != self::CHECKOUT_STATUS_SUCCESS) {
            $this->messageManager->addErrorMessage(
                (string)__('Wizpay payment is failed. Please select an alternative payment method.')
            );
            return $this->redirectFactory->create()->setPath('checkout/cart');
        }

        try {
            $quote = $this->session->getQuote();
            $wizpayOrderToken = 'orderToken';//$this->request->getParam('orderToken');
            
            // go to wizpay to process order
            $wizpay_payment_url = $this->placeOrderProcessor->execute($quote, $wizpayOrderToken);   

            return $this->redirectFactory->create()->setPath($wizpay_payment_url);
        } catch (\Throwable $e) {
            $this->logger->info("-------------->>>>>>>>>>>>>>>>Wizpay index error<<<<<<<<<<<<<<--------------");
            $this->logger->info($e->getMessage());
            $this->logger->info("-------------->>>>>>>>>>>>>>>>Wizpay index error<<<<<<<<<<<<<<--------------");
            $errorMessage = (string)__('Payment is failed');
            $this->messageManager->addErrorMessage($errorMessage);
            return $this->redirectFactory->create()->setPath('checkout/cart');
        }

        $this->messageManager->addSuccessMessage((string)__('Wizpay Transaction Completed'));
        return $this->redirectFactory->create()->setPath('checkout/onepage/success');
    }
}
