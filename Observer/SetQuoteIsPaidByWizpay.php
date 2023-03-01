<?php declare(strict_types=1);

namespace Wizpay\Wizpay\Observer;

class SetQuoteIsPaidByWizpay implements \Magento\Framework\Event\ObserverInterface
{
    private \Wizpay\Wizpay\Model\Order\Payment\QuotePaidStorage $quotePaidStorage;

    public function __construct(
        \Wizpay\Wizpay\Model\Order\Payment\QuotePaidStorage $quotePaidStorage
    ) {
        $this->quotePaidStorage = $quotePaidStorage;
    }

    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        /** @var \Magento\Sales\Model\Order\Payment $payment */
        $payment = $observer->getEvent()->getData('payment');

        if ($payment->getMethod() == \Wizpay\Wizpay\Gateway\Config\Config::CODE) {
            $this->quotePaidStorage->setWizpayPaymentForQuote((int)$payment->getOrder()->getQuoteId(), $payment);
        }
    }
}