<?php declare(strict_types=1);

namespace Wizpay\Wizpay\Model\Order\Payment;

use Magento\Sales\Model\Order\Payment;

class QuotePaidStorage
{
    private array $quotesOrderPayments = [];

    public function setWizpayPaymentForQuote(int $quoteId, Payment $wizpayPayment): self
    {
        $this->quotesOrderPayments[$quoteId] = $wizpayPayment;
        return $this;
    }

    public function getWizpayPaymentIfQuoteIsPaid(int $quoteId): ?Payment
    {
        return $this->quotesOrderPayments[$quoteId] ?? null;
    }
}