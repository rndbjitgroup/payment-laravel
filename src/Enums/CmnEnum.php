<?php 

namespace Bjit\Payment\Enums;

abstract class CmnEnum 
{
    const LIMIT = 3;
    const OFFSET = 10;
    const ZERO = 0;
    const ONE = 1;
    const TWO = 2;
    const THREE = 3;

    const TABLE_PAYMENT_NAME = 'payments';
    const TABLE_REFUND_NAME = 'refunds';

    const PT_DIRECT_PAYMENT = 'DirectPayment'; // PT => PAYMNET TYPE 
    const PT_CHECKOUT_PAYMENT = 'CheckoutPayment';
    const PS_PAID = 'paid'; // PS => PAYMENT STATUS 
    const PS_UNPAID = 'unpaid';
    const STATUS_COMPLETE = 'complete';
    const STATUS_OPEN = 'open';
    const STATUS_PENDING = 'pending';

    const PROVIDER_STRIPE = 'stripe';
    const PROVIDER_PAYJP = 'payjp';
    const PROVIDER_PAYPAY = 'paypay';
    const PROVIDER_PAYPAL = 'paypal';
}