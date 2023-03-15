<?php
/**
 *
 * @package     magento2
 * @author
 * @license     https://opensource.org/licenses/OSL-3.0 Open Software License v. 3.0 (OSL-3.0)
 * @link
 */

 namespace Wizpay\Wizpay\Controller\Index;

class WebhookComfirmUrl extends Success
{

    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Framework\View\Result\PageFactory $resultPageFactory,
        \Magento\Framework\Controller\Result\Redirect $resultRedirectFactory,
        \Magento\Sales\Api\OrderRepositoryInterface $orderRepository,
        \Magento\Sales\Model\Service\InvoiceService $invoiceService,
        \Magento\Framework\DB\Transaction $transaction,
        //StockRegistryInterface $stockRegistry,
        //\Magento\Paypal\Model\Adminhtml\ExpressFactory $authorisationFactory,
        \Magento\Sales\Model\Order\Email\Sender\InvoiceSender $invoiceSender,
        Data $helper,
        Checkout $checkoutHelper,
        Session $checkoutSession,
        OrderFactory $orderFactory,
        \Psr\Log\LoggerInterface $logger,
        \Magento\Customer\Model\Session $customerSession
    ) {
       
        
        parent::__construct($context, $resultPageFactory, $resultRedirectFactory, $orderRepository, $invoiceService, $transaction, $invoiceSender, $helper, $checkoutHelper,
    $checkoutSession, $orderFactory, $orderFactory, $logger, $customerSession);

        $this->callback_source = "WebhookComfirmUrl CALL BACK";
    }

}