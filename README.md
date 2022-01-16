# BJIT Payment 
## _This is the common Payment System for multiple payments_

BJIT Payment System is a payment system which is handled a common way for multiple gateways.

- Stripe
- PayJP 

## Features

- Create, Retrieve, Update and Capture Payment.
- Create, Expire, Retrieve and All (List) Checkout.
- Refund Payment, Update Refund and Update Refund. 
 

## Tech

BJIT Payment System uses a number of open source projects to work properly:

- [Laravel](https://laravel.com/docs/8.x) - Laravel 8.x for the package!

 

## Installation

BJIT Payment requires [Laravel](https://laravel.com/docs/8.x) v8+ to run.

Install the dependencies and devDependencies and start the server.

```sh
composer create-project laravel/laravel ps-demo
cd ps-demo
composer require bjitgroup/payment-laravel
```

For environments...

```sh
php artisan serve
```
```sh
127.0.0.1:8000
```

## Payment API (Details)
### Payments 
##### Create Payment 

```sh
use Bjit\Payment\Facades\Payment;
Payment::gateway($request->gateway)->createPayment([
    "amount" => $request->amount,
    "currency" => $request->currency ?? env('PAYMENT_CURRENCY'),
    "nonce" => $request->token
    "description" => $request->description
]);
```
_**gateway**  is stipe/payjp/paypal/..._

##### Retrieve Payment  

```sh
use Bjit\Payment\Facades\Payment;
Payment::gateway($request->gateway)->retrievePayment($request->paymentId)
```

##### Create Checkout 
**Not Supported Payment Gateway:** _PayJP_
```sh
use Bjit\Payment\Facades\Payment;
Payment::gateway($request->gateway)->createCheckout([ 
    'payment_method_types' => $request->paymentMethodTypes,
    'order_items' => $request->orderItems, 
    'mode' => $request->payment_mode,
    'payment_intent_data' => [
        'capture_method' => 'automatic', // automatic/manual
    ],
    'success_url' => route($request->gateway . '.success'), 
    'cancel_url' => route($request->gateway . '.cancel'),
    'state' => sha1(md5(sha1(Auth::user()->id ?? rand(1111, 9999)))) // unique id
]); 
```

##### Retrieve Checkout  
**Not Supported Payment Gateway:** _PayJP_
```sh
use Bjit\Payment\Facades\Payment;
Payment::gateway($request->gateway)->retrieveCheckout($request->paymentId)
```

## License

MIT
