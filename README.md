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

And of course Dillinger itself is open source with a [public repository][dill]
 on GitHub.

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
Payment::gateway($request->gateway)->createPayment([
    "amount" => $request->amount,
    "currency" => $request->currency ?? env('PAYMENT_CURRENCY'),
    "nonce" => $request->token
    "description" => $request->description
]);
```
**gateway is stipe/payjp/paypal/...**
##### Retrieve Payment 
```sh
Payment::gateway($request->gateway)->retrievePayment($request->paymentId)
```


## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
