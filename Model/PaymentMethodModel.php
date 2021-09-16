<?php

namespace Wizpay\Wizpay\Model;

class PaymentMethodModel extends \Magento\Payment\Model\Method\AbstractMethod
{
    const CODE = 'wizpay';
    public $_code = self::CODE;
    public $_isGateway = true;
    public $_canRefund = true;
    public $_canRefundInvoicePartial = true;
    public $_canCapture = true;
    public $_canCapturePartial = true;
    public $_can_void = true;
    public $_canAuthorize = true;
}
