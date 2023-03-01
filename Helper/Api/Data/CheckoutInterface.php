<?php declare(strict_types=1);

namespace Wizpay\Wizpay\Helper\Api\Data;

interface CheckoutInterface{
     /**#@+
     * Checkout result keys
     */
    const WIZPAY_TOKEN = 'wizpay_token';
    const WIZPAY_AUTH_TOKEN_EXPIRES = 'wizpay_expires';
    const WIZPAY_REDIRECT_CHECKOUT_URL = 'wizpay_redirectCheckoutUrl';


}