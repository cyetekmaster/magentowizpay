<?php declare(strict_types=1);

namespace Wizpay\Wizpay\Model\Payment;

interface AdditionalInformationInterface
{
    const WIZPAY_ORDER_ID = 'wizpay_order_id';
    const WIZPAY_OPEN_TO_CAPTURE_AMOUNT = 'wizpay_open_to_capture_amount';
    const WIZPAY_PAYMENT_STATE = 'wizpay_payment_state';
    const WIZPAY_AUTH_EXPIRY_DATE = 'wizpay_auth_expiry_date';
    const WIZPAY_ROLLOVER_DISCOUNT = 'wizpay_rollover_discount';
    const WIZPAY_CAPTURED_DISCOUNT = 'wizpay_captured_discount';
}