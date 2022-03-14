<?php declare(strict_types=1);

namespace Wizpay\Wizpay\Model\Payment\Capture;

use Wizpay\Wizpay\Model\Payment\AdditionalInformationInterface;
use Magento\Payment\Gateway\CommandInterface;
use Magento\Quote\Model\Quote;

class PlaceOrderProcessor
{
    private \Magento\Quote\Api\CartManagementInterface $cartManagement;
    private \Wizpay\Wizpay\Model\Payment\Capture\CancelOrderProcessor $cancelOrderProcessor;
    private \Wizpay\Wizpay\Model\Order\Payment\QuotePaidStorage $quotePaidStorage;
    private \Magento\Payment\Gateway\Data\PaymentDataObjectFactoryInterface $paymentDataObjectFactory;
    private \Psr\Log\LoggerInterface $logger;

    public function __construct(
        \Magento\Quote\Api\CartManagementInterface $cartManagement,
        \Wizpay\Wizpayy\Model\Payment\Capture\CancelOrderProcessor $cancelOrderProcessor,
        \Wizpay\Wizpayy\Model\Order\Payment\QuotePaidStorage $quotePaidStorage,
        \Magento\Payment\Gateway\Data\PaymentDataObjectFactoryInterface $paymentDataObjectFactory,
        \Psr\Log\LoggerInterface $logger
    ) {
        $this->cartManagement = $cartManagement;
        $this->cancelOrderProcessor = $cancelOrderProcessor;
        $this->quotePaidStorage = $quotePaidStorage;
        $this->paymentDataObjectFactory = $paymentDataObjectFactory;
        $this->logger = $logger;
    }

    public function execute(Quote $quote, CommandInterface $checkoutDataCommand, string $wizpayOrderToken): void
    {
        try {
            $quote->getPayment()->setAdditionalInformation(
                \Wizpay\Wizpay\Helper\Api\Data\CheckoutInterface::WIZPAY_TOKEN,
                $wizpayOrderToken
            );

            if (!$quote->getCustomerId()) {
                $quote->setCustomerEmail($quote->getBillingAddress()->getEmail())
                    ->setCustomerIsGuest(true)
                    ->setCustomerGroupId(\Magento\Customer\Api\Data\GroupInterface::NOT_LOGGED_IN_ID);
            }

            $checkoutDataCommand->execute(['payment' => $this->paymentDataObjectFactory->create($quote->getPayment())]);

            $this->cartManagement->placeOrder($quote->getId());
        } catch (\Throwable $e) {
            $this->logger->critical('Order placement is failed with error: ' . $e->getMessage());
            $quoteId = (int)$quote->getId();
            if ($wizpayPayment = $this->quotePaidStorage->getWizpayPaymentIfQuoteIsPaid($quoteId)) {
                $this->cancelOrderProcessor->execute($wizpayPayment);
                throw new \Magento\Framework\Exception\LocalizedException(
                    __(
                        'There was a problem placing your order. Your Wizpay order %1 is refunded.',
                        $wizpayPayment->getAdditionalInformation(AdditionalInformationInterface::WIZPAY_ORDER_ID)
                    )
                );
            }
            throw $e;
        }
    }
}