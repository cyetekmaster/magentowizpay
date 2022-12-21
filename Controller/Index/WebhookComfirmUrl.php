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
        \Magento\Framework\App\Request\Http $request,
        \Magento\Checkout\Model\Session $session,
        \Magento\Framework\Controller\Result\RedirectFactory $redirectFactory,
        \Magento\Framework\Message\ManagerInterface $messageManager,
        \Wizpay\Wizpay\Model\Payment\Capture\PlaceOrderProcessor $placeOrderProcessor,
        \Psr\Log\LoggerInterface $logger,
        \Magento\Quote\Model\QuoteFactory $quoteFactory,
        \Magento\Quote\Api\CartManagementInterface $cartManagement,
        \Wizpay\Wizpay\Helper\Data $wizpay_helper,
        \Magento\Sales\Model\Order $order,
        \Wizpay\Wizpay\Helper\Checkout $checkout,
        \Magento\Sales\Model\Order\Email\Sender\InvoiceSender $invoiceSender,
        \Magento\Quote\Api\CartRepositoryInterface $quoteRepository,
        \Magento\Catalog\Api\ProductRepositoryInterface $productRepository,
        \Magento\Customer\Api\CustomerRepositoryInterface $customerRepository
    ) {
        $this->request = $request;
        $this->session = $session;
        $this->redirectFactory = $redirectFactory;
        $this->messageManager = $messageManager;
        $this->placeOrderProcessor = $placeOrderProcessor;
        $this->cartManagement = $cartManagement;
        $this->logger = $logger;
        $this->quoteFactory = $quoteFactory;
        $this->wizpay_data_helper = $wizpay_helper;
        $this->order = $order;
        $this->checkoutHelper = $checkout;
        $this->invoiceSender = $invoiceSender;
        $this->quoteRepository = $quoteRepository;
        $this->productRepository = $productRepository;
        $this->customerRepository = $customerRepository;
        $this->callback_source = "WebhookComfirmUrl CALL BACK";
    }

}
