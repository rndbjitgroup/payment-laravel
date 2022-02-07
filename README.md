# BJIT Payment 
## _This is the common Payment System for multiple payments_

BJIT Payment System is a payment system which is handled a common way for multiple gateways.

- Stripe
- PayJP 
- Paypal 
- Paypay

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
    'amount' => 500,
    'currency' => 'USD',
    'nonce' => 'payment gateway card token',
    'description' => 'This is the test description'
]);
```
_**gateway**  is stipe/payjp/paypal/..._

##### Retrieve Payment  

```sh
use Bjit\Payment\Facades\Payment;
Payment::gateway($request->gateway)->retrievePayment($request->paymentId)
```

##### Update Payment 

```sh
use Bjit\Payment\Facades\Payment;
Payment::gateway($request->gateway)->updatePayment($paymentId, [
    'description' => 'This is the test description',
    'metadata' => ['orderId' => 'ORD#1001']
]);
```

##### Capture Payment 

```sh
use Bjit\Payment\Facades\Payment;
Payment::gateway($request->gateway)->capturePayment($paymentId, $options);
```
_**$options** is optional_

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
Payment::gateway($request->gateway)->retrieveCheckout($paymentId)
```

##### Refund Payment   
```sh
use Bjit\Payment\Facades\Payment;
Payment::gateway($request->gateway)->refundPayment($paymentId, [
    'amount' => 500,
    'refund_reason' => 'refund reason'
]);
```

##### Create Customer 
```sh 
use Bjit\Payment\Facades\Payment;
Payment::gateway($request->gateway)->createCustomer([
    'email' => 'example@example.com', 
    'description' => 'Test payment from bjitgroup.com.', 
    //'nonce' => 'card-token', // If you want to add card with customer, uncomment it. 
    //'metadata' => ['key' => 'value'],  //Arbitrary data of key value like ['key' => 'value']
]);
```

##### Retrieve Customer 
```sh
use Bjit\Payment\Facades\Payment;
Payment::gateway($request->gateway)->retrieveCustomer($customerId);
```

##### Update Customer 
```sh 
use Bjit\Payment\Facades\Payment;
Payment::gateway($request->gateway)->updateCustomer($customerId, [
    'email' => 'example@example.com', 
    'description' => 'Test payment from bjitgroup.com.', 
    //'nonce' => 'card-token', // If you want to add card with customer, uncomment it. 
    //'default_nonce' => 'car_dae1c...', // If you want to add card with customer, uncomment it. 
    //'metadata' => ['test' => 1],  //Arbitrary data of key value like ['key' => 'value']
]);
```

##### Delete Customer 
```sh
use Bjit\Payment\Facades\Payment;
Payment::gateway($request->gateway)->deleteCustomer($customerId);
```

##### Retrieve All Customers 
```sh
use Bjit\Payment\Facades\Payment;
Payment::gateway($request->gateway)->allCustomers();
```

##### Create Card 
```sh 
use Bjit\Payment\Facades\Payment;
Payment::gateway($request->gateway)->createCard([
    'nonce' => 'card-token',
    //'default' => true, // Uncomment it if this card is default card 
    'metadata' => ['nothing_id' => 101],  //Arbitrary data of key value like ['key' => 'value']
], $customerId);
```

##### Retrieve Card 
```sh
use Bjit\Payment\Facades\Payment;
Payment::gateway($request->gateway)->retrieveCard($customerId, $cardId);
```

##### Update Card 
```sh 
use Bjit\Payment\Facades\Payment;
Payment::gateway($request->gateway)->updateCard($customerId, $cardId, [
    'address_state' => 'Tokyo', // State/County/Province/Region/Prefectures
    'address_city' => 'Chuo-ku', //City/District/Suburb/Town/Village/municipalities.
    'address_line1' => '3-10, Kyobashi 2-chome', //Street address/PO Box/Company name
    'address_line2' => 'Kyobashi MID Bldg', //Apartment/Suite/Unit/Building
    'address_zip' => '104-0031', //ZIP or postal code.
    'country' => 'JP', // 2-digit ISO code (eg JP/US)
    'name' => 'Mr. X', //Cardholder name.
    //'metadata' => ['key' => 'value'],  //Arbitrary data of key value like ['key' => 'value']
]);
```

##### Delete Card 
```sh
use Bjit\Payment\Facades\Payment;
Payment::gateway($request->gateway)->deleteCard($customerId, $cardId);
```

##### Retrieve All Cards 
```sh
use Bjit\Payment\Facades\Payment;
Payment::gateway($request->gateway)->allCards();
```

##### Create Plan 
```sh 
use Bjit\Payment\Facades\Payment;
Payment::gateway($request->gateway)->createPlan([
    'name' => 'Plan 1001',  //Plan name
    'amount' => 600,
    'currency' => 'jpy', //Three-letter ISO currency code, in lowercase. Must be a supported currency.
    'interval' => 'month', //Specifies billing frequency. Either day, week, month or year
    //'metadata' => ['key' => 'value'],  //Arbitrary data of key value like ['key' => 'value']
]);
```

##### Retrieve Plan 
```sh
use Bjit\Payment\Facades\Payment;
Payment::gateway($request->gateway)->retrievePlan($planId);
```

##### Update Plan 
```sh 
use Bjit\Payment\Facades\Payment;
Payment::gateway($request->gateway)->updatePlan($planId, [
    'name' => 'Plan 1001 update',  //Plan name 
    //'metadata' => ['test' => 1],  //Arbitrary data of key value like ['key' => 'value']
]);
```

##### Delete Plan 
```sh
use Bjit\Payment\Facades\Payment;
Payment::gateway($request->gateway)->deletePlan($planId);
```

##### Retrieve All Plans 
```sh
use Bjit\Payment\Facades\Payment;
Payment::gateway($request->gateway)->allPlans();
```

##### Create Subscription 
```sh 
use Bjit\Payment\Facades\Payment;
Payment::gateway($request->gateway)->createSubscription([
    'provider_customer_id' => 'cus_310...',  
    'provider_plan_id' => 'pln_910...', 
]);
```

##### Retrieve Subscription 
```sh
use Bjit\Payment\Facades\Payment;
Payment::gateway($request->gateway)->retrieveSubscription($subscriptionId);
```

##### Update Subscription 
```sh 
use Bjit\Payment\Facades\Payment;
Payment::gateway($request->gateway)->updateSubscription($subscriptionId, [
    'provider_plan_id' => 'pln_b88...',
]);
```
##### Pause Subscription 
```sh
use Bjit\Payment\Facades\Payment;
Payment::gateway($request->gateway)->pauseSubscription($subscriptionId);
```

##### Resume Subscription 
```sh
use Bjit\Payment\Facades\Payment;
Payment::gateway($request->gateway)->resumeSubscription($subscriptionId);
```

##### Cancle Subscription 
```sh
use Bjit\Payment\Facades\Payment;
Payment::gateway($request->gateway)->cancelSubscription($subscriptionId);
```

##### Delete Subscription 
```sh
use Bjit\Payment\Facades\Payment;
Payment::gateway($request->gateway)->deleteSubscription($subscriptionId);
```

##### Retrieve All Subscriptions 
```sh
use Bjit\Payment\Facades\Payment;
Payment::gateway($request->gateway)->allSubscriptions();
```

## License

MIT
