<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */
 
    'store' => [
        'in-database' => 'automatic' // automatic/manual 
        // automatic - store automatically by the package, 
        // manual - handle it based on your application 
    ],

    'stripe' => [
        'key' => env('STRIPE_KEY'),
        'secret' => env('STRIPE_SECRET') 
    ],

    'payjp' => [
        'key' => env('PAYJP_KEY'),
        'secret' => env('PAYJP_SECRET') 
    ],

    'paypay' => [
        'key' => env('PAYPAY_KEY'),
        'secret' => env('PAYPAY_SECRET'),
        'merchant_id' => env('PAYPAY_MERCHANT_ID'),
        'is_live' => env('PAYPAY_IS_LIVE', false),
        'redirect' => env('PAYPAY_REDIRECT_URL'),
        'scopes' => ['pending_payments']
    ],

    'paypal' => [
        'key' => env('PAYPAL_' . strtoupper(env('PAYPAL_MODE', 'sandbox')) . '_CLIENT_ID', ''),
        'secret' => env('PAYPAL_' . strtoupper(env('PAYPAL_MODE', 'sandbox')) . '_CLIENT_SECRET', ''),
        'mode'    => env('PAYPAL_MODE', 'sandbox'), // Can only be 'sandbox' Or 'live'. If empty or invalid, 'live' will be used.
        'sandbox' => [
            'client_id'         => env('PAYPAL_SANDBOX_CLIENT_ID', ''),
            'client_secret'     => env('PAYPAL_SANDBOX_CLIENT_SECRET', ''),
            'app_id'            => env('PAYPAL_SANDBOX_APP_ID', ''),
        ],
        'live' => [
            'client_id'         => env('PAYPAL_LIVE_CLIENT_ID', ''),
            'client_secret'     => env('PAYPAL_LIVE_CLIENT_SECRET', ''),
            'app_id'            => env('PAYPAL_LIVE_APP_ID', ''),
        ],
    
        'payment_action' => env('PAYPAL_PAYMENT_ACTION', 'Sale'), // Can only be 'Sale', 'Authorization' or 'Order'
        'currency'       => env('PAYPAL_CURRENCY', 'USD'),
        'notify_url'     => env('PAYPAL_NOTIFY_URL', ''), // Change this accordingly for your application.
        'locale'         => env('PAYPAL_LOCALE', 'en_US'), // force gateway language  i.e. it_IT, es_ES, en_US ... (for express checkout only)
        'validate_ssl'   => env('PAYPAL_VALIDATE_SSL', true), // Validate SSL when creating api client.
    ]

];
