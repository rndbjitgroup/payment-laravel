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
    const TRUE = true;
    const FLASE = false;
    const EMPTY_NULL = null;

    const TABLE_PAYMENT_NAME = 'ps_payments';
    const TABLE_REFUND_NAME = 'ps_refunds';
    const TABLE_CUSTOMER_NAME = 'ps_customers';
    const TABLE_CARD_NAME = 'ps_cards';
    const TABLE_PRODUCT_NAME = 'ps_products';
    const TABLE_PLAN_NAME = 'ps_plans';
    const TABLE_SUBSCRIPTION_NAME = 'ps_subscriptions'; 

    
    const PT_DIRECT_PAYMENT = 'DirectPayment'; // PT => PAYMNET TYPE 
    const PT_CHECKOUT_PAYMENT = 'CheckoutPayment';
    const PT_CARD = 'card';
    const PS_PAID = 'paid'; // PS => PAYMENT STATUS 
    const PS_UNPAID = 'unpaid';
    const RS_REFUNDED = 'refunded'; // PS => REFUND STATUS 
    const RS_NOT_REFUNDED = 'not-refunded';

    const STATUS_SUCCEEDED = 'succeeded'; 
    const STATUS_COMPLETE = 'completed';
    const STATUS_OPEN = 'open';
    const STATUS_PENDING = 'pending';
    const STATUS_PAYPAL_COMPLETED = 'COMPLETED';
    const STATUS_PAYPAY_CREATED = 'CREATED';
    const STATUS_PAYPAY_CODE = 'SUCCESS';

    const STATUS_STRIPE_COMPLETE = 'complete';

    const PROVIDER_STRIPE = 'stripe';
    const PROVIDER_PAYJP = 'payjp';
    const PROVIDER_PAYPAY = 'paypay';
    const PROVIDER_PAYPAL = 'paypal';
    const PROVIDER_PAYGENT = 'paygent';

    const STRIPE_PAYMENT_INTENT_PREFIX = 'pi_';
    const DEFAULT_STRIPE_PAYMENT_MODE = 'payment';
    const REFUND_STRIPE_REASONS = ['duplicate', 'fraudulent', 'requested_by_customer'];

    const STORE_IN_DB_AUTOMATIC = 'automatic';
    const STORE_IN_DB_MANUAL = 'manual';

    const PAYPAY_ORDER_TYPE = 'ORDER_QR';
    const PAYPAY_REDIRECT_TYPE_WEB_LINK = 'WEB_LINK';

    const PAYGENT_PAYMENT_TERM_MIN = 30;
    const PAYGENT_PAYMENT_CLASS = 0;
    const PAYGENT_USE_CARD_CONF_NUMBER = 1;
    
}