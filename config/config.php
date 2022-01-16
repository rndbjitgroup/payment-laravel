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

];
