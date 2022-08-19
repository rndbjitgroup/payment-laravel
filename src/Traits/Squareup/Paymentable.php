<?php


namespace Bjit\Payment\Traits\Squareup;

use Bjit\Payment\Enums\CmnEnum;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Square\Models\Money;
use Square\Models\CreatePaymentRequest;
use Square\Models\CashPaymentDetails;
use Square\Models\Payment;
use Square\Models\UpdatePaymentRequest;
use Square\Models\CompletePaymentRequest;


trait Paymentable
{
    private function formatPaymentListInput($options)
    {
        $input = [];

        $input[] = $options['begin_time'] ?? CmnEnum::EMPTY_NULL;
        $input[] = $options['end_time'] ?? CmnEnum::EMPTY_NULL;
        $input[] = $options['sort_order'] ?? CmnEnum::EMPTY_NULL;
        $input[] = $options['cursor'] ?? CmnEnum::EMPTY_NULL;
        $input[] = $options['location_id'] ?? CmnEnum::EMPTY_NULL;
        $input[] = $options['total'] ?? CmnEnum::EMPTY_NULL;
        $input[] = $options['last_4'] ?? CmnEnum::EMPTY_NULL;
        $input[] = $options['card_brand'] ?? CmnEnum::EMPTY_NULL;
        $input[] = $options['limit'] ?? CmnEnum::EMPTY_NULL; 

        return $input;
    }

    public function formatPaymentInput($options)
    {
        $amountMoney = new Money();
        $amountMoney->setAmount($options['amount']);
        $amountMoney->setCurrency($options['currency']); //JPY

        if (isset($options['app_fee']) && !empty($options['app_fee'])) {
            $appFeeMoney = new Money();
            $appFeeMoney->setAmount($options['app_fee']);
            $appFeeMoney->setCurrency($options['currency']);
        }

        $sourceId = $options['card_id'] ?? CmnEnum::EMPTY_NULL;
        $idempotencyKey = uniqid('stored', true);
        //$sourceCategory = 'ON File';
        if (isset($options['nonce']) && !empty($options['nonce'])) {
            $sourceId = $options['nonce'];
            $idempotencyKey = uniqid('direct', true);
        //$sourceCategory = 'Direct';
        }

        $body = new CreatePaymentRequest(
            $sourceId, $idempotencyKey, $amountMoney
        );

        if (isset($options['app_fee']) && !empty($options['app_fee'])) {
            $body->setAppFeeMoney($appFeeMoney);
        }

        if (isset($options['capture_method']) && $options['capture_method'] == CmnEnum::CAPTURE_METHOD_AUTOMATIC) {
            $body->setAutocomplete(CmnEnum::TRUE);
        } else {
            $body->setAutocomplete(CmnEnum::FLASE);
        }

        if (isset($options['customer_id']) && !empty($options['customer_id'])) {
            $body->setCustomerId($options['customer_id']);
        }

        if (isset($options['squareup_order_id']) && !empty($options['squareup_order_id'])) {
            $body->setOrderId($options['squareup_order_id']);
        }

        if (isset($options['squareup_location_id']) && !empty($options['squareup_location_id'])) {
            $body->setLocationId($options['squareup_location_id']);
        }

        if (isset($options['reference_id']) && !empty($options['reference_id'])) {
            $body->setReferenceId($options['reference_id']);
        }
        //$body->setNote('Brief description');

        return [
            'idempotency_key' => $idempotencyKey,
            'body' => $body 
        ];
    }
    public function formatUpdatePaymentInput($options)
    {
        if (isset($options['amount'])) {
            $amountMoney = new Money();
            $amountMoney->setAmount($options['amount']);
            $amountMoney->setCurrency($options['currency']);
        }

        if (isset($options['tip_amount'])) {
            $tipMoney = new Money();
            $tipMoney->setAmount($options['tip_amount']);
            $tipMoney->setCurrency($options['currency']);
        }

        if (isset($options['app_fee_amount'])) {
            $appFeeMoney = new Money();
            $appFeeMoney->setAmount($options['app_fee_amount']);
            $appFeeMoney->setCurrency($options['currency']);
        }

        if (isset($options['approved_money'])) {
            $approvedMoney = new Money(); 
            $approvedMoney->setAmount($options['approved_money']);
            $approvedMoney->setCurrency($options['currency']);
        }

        if (isset($options['buyer_supplied_money'])) {
            $buyerSuppliedMoney = new Money();
            $buyerSuppliedMoney->setAmount($options['buyer_supplied_money']);
            $buyerSuppliedMoney->setCurrency($options['currency']);
        }

        if(isset($buyerSuppliedMoney)) {
            $cashDetails = new CashPaymentDetails($buyerSuppliedMoney);
        }

        $payment = new Payment();
        if (isset($amountMoney)) {
            $payment->setAmountMoney($amountMoney);
        }
        if (isset($tipMoney)) {
            $payment->setTipMoney($tipMoney);
        }
        if (isset($appFeeMoney)) {
            $payment->setAppFeeMoney($appFeeMoney);
        }
        if (isset($approvedMoney)) {
            $payment->setApprovedMoney($approvedMoney);
        }
        if (isset($options['delay_action'])) {
            $payment->setDelayAction('delay_action');
        }
        if (isset($cashDetails)) {
            $payment->setCashDetails($cashDetails);
        }
        if (isset($options['version_token'])) {
            $payment->setVersionToken($options['version_token']);
        }

        $idempotencyKey = (string) Str::uuid();
        $body = new UpdatePaymentRequest($idempotencyKey);
        $body->setPayment($payment);
        return [
            'idempotency_key' => $idempotencyKey,
            'body' => $body 
        ];
    }

    public function formatPaymentResponse($response)
    {
        if (!$response->isSuccess()) {
            return ['error' => $response->getErrors()];
        }

        $responseFromStr = json_decode($response->getBody(), true);
        $response = $responseFromStr['payment'];

        return [
            'provider' => CmnEnum::PROVIDER_SQUAREUP,
            'id' => $response['id'], 
            'amount' => $response['total_money']['amount'] ?? 0,
            'currency' => $response['total_money']['currency'],
            'captured' => $response['card_details']['status'] == strtoupper(CmnEnum::STATUS_CAPTURED) ?CmnEnum::ONE : CmnEnum::ZERO,
            'payment_status' => $response['paid'] ?? CmnEnum::EMPTY_NULL,
            'status' => $response['status'],
            'generic_payment_status' => $response['status'] == strtoupper(CmnEnum::STATUS_COMPLETE) ?CmnEnum::PS_PAID : CmnEnum::PS_UNPAID,
            'generic_status' => $response['status'] == strtoupper(CmnEnum::STATUS_COMPLETE) ?CmnEnum::STATUS_COMPLETE : CmnEnum::STATUS_OPEN,
            'description' => $response['description'] ?? CmnEnum::EMPTY_NULL,
            'payment_type' => $response['card_details']['card']['card_type'] ?? CmnEnum::EMPTY_NULL,
            'card_brand' => $response['card_details']['card']['card_brand'] ?? CmnEnum::EMPTY_NULL,
            'last_4_digit' => $response['card_details']['card']['last_4'] ?? CmnEnum::EMPTY_NULL,
            'customer_name' => $response['customer_details']['name'] ?? CmnEnum::EMPTY_NULL,
            'customer_email' => $response['customer_details']['email'] ?? CmnEnum::EMPTY_NULL,
            'customer_phone' => $response['customer_details']['phone'] ?? CmnEnum::EMPTY_NULL,
            'created_at' => $response['created_at'],
            'provider_response' => $response
        ];
    }

    private function getPaymentAmount($paymentId)
    {
        $payment = $this->retrievePayment($paymentId);
        return $payment['amount'];
    }

    public function createPayment($options)
    {
        $responseFromInput = $this->formatPaymentInput($options);
        $response = $this->squareup->getPaymentsApi()->createPayment($responseFromInput['body']);

        if ($response->isSuccess()) {
            $responseFromStr = json_decode($response->getBody(), true);
            $responseFromStr = $responseFromStr['payment'];
            $options['idempotency_key'] = $responseFromInput['idempotency_key'];
            $this->storePaymentInDatabase($responseFromStr, $options, CmnEnum::PT_DIRECT_PAYMENT);
        }
        return $this->formatPaymentResponse($response);
    }

    public function retrievePayment($paymentId, $options = [])
    {
        return $this->formatPaymentResponse($this->squareup->getPaymentsApi()->getPayment($paymentId));
    }

    public function cancelPayment($paymentId, $options = [])
    {
        $response = $this->squareup->getPaymentsApi()->cancelPayment($paymentId);
        if ($response->isSuccess()) {
            $responseFromStr = json_decode($response->getBody(), true);
            $responseFromStr = $responseFromStr['payment']; 
            $this->updateCancelPaymentInDatabase($paymentId, $responseFromStr, $options);
        }
        return $this->formatPaymentResponse($response);
    }

    public function updatePayment($paymentId, $options = [])
    {
        $responseFromInput = $this->formatUpdatePaymentInput($options);
        $response = $this->squareup->getPaymentsApi()->updatePayment($paymentId, $responseFromInput['body']);
        if ($response->isSuccess()) {
            $responseFromStr = json_decode($response->getBody(), true);
            $responseFromStr = $responseFromStr['payment'];
            $options['idempotency_key'] = $responseFromInput['idempotency_key'];
            $this->updatePaymentInDatabase($paymentId, $responseFromStr, $options);
        }

        return $this->formatPaymentResponse($response);
    }

    public function capturePayment($paymentId, $options = [])
    {
        $body = new CompletePaymentRequest();
        if(isset($options['version_token'])) {
            $body->setVersionToken($options['version_token']);
        }
        $response = $this->squareup->getPaymentsApi()->completePayment($paymentId, $body);
         
        if ($response->isSuccess()) {
            $responseFromStr = json_decode($response->getBody(), true);
            $responseFromStr = $responseFromStr['payment'];
            $this->updateCapturePaymentInDatabase($paymentId, $responseFromStr, $options);
        }
        return $this->formatPaymentResponse($response);
    }

    public function allPayments($options = [])
    {
        return $this->squareup->getPaymentsApi()->listPayments(...$this->formatPaymentListInput($options));
    }

    private function storePaymentInDatabase($response, $options, $paymentType)
    {
        if (!(config('payments.store.in-database') === CmnEnum::STORE_IN_DB_AUTOMATIC)) {
            return true;
        }

        return DB::table(CmnEnum::TABLE_PAYMENT_NAME)->insert([
            'state' => $options['state'] ?? CmnEnum::EMPTY_NULL,
            'type' => $paymentType,
            'provider' => CmnEnum::PROVIDER_SQUAREUP,
            'provider_payment_id' => $response['id'],
            'provider_payment_idempotency_key' => $options['idempotency_key'] ?? CmnEnum::EMPTY_NULL,
            'user_id' => Auth::user()->id ?? CmnEnum::ONE,
            'amount' => $response['total_money']['amount'] ?? 0,
            'currency' => $response['total_money']['currency'],
            'captured' => $response['card_details']['status'] == strtoupper(CmnEnum::STATUS_CAPTURED) ?CmnEnum::ONE : CmnEnum::ZERO,
            'payment_status' => $response['paid'] ?? CmnEnum::EMPTY_NULL,
            'status' => $response['status'],
            'generic_payment_status' => $response['status'] == strtoupper(CmnEnum::STATUS_COMPLETE) ?CmnEnum::PS_PAID : CmnEnum::PS_UNPAID,
            'generic_status' => $response['status'] == strtoupper(CmnEnum::STATUS_COMPLETE) ?CmnEnum::STATUS_COMPLETE : CmnEnum::STATUS_OPEN,
            'description' => $response['description'] ?? CmnEnum::EMPTY_NULL,
            'payment_type' => $response['card_details']['card']['card_type'] ?? CmnEnum::EMPTY_NULL,
            'card_brand' => $response['card_details']['card']['card_brand'] ?? CmnEnum::EMPTY_NULL,
            'last_4_digit' => $response['card_details']['card']['last_4'] ?? CmnEnum::EMPTY_NULL,
            'customer_name' => $response['customer_details']['name'] ?? CmnEnum::EMPTY_NULL,
            'customer_email' => $response['customer_details']['email'] ?? CmnEnum::EMPTY_NULL,
            'customer_phone' => $response['customer_details']['phone'] ?? CmnEnum::EMPTY_NULL,
            'success_json' => json_encode($response),
            'created_at' => now(),
            'updated_at' => now()
        ]);
    }

    private function updatePaymentTableInDatabase($paymentId, $options) 
    {  
        if (! (config('payments.store.in-database') === CmnEnum::STORE_IN_DB_AUTOMATIC)) {
            return true;
        }

        return DB::table(CmnEnum::TABLE_PAYMENT_NAME)
            ->where('provider', CmnEnum::PROVIDER_SQUAREUP)
            ->where('provider_payment_id', $paymentId)
            ->update($options);
    }
    private function rearrangeDataForUpdate($response, $options = [])
    {
        return [
            'provider_payment_idempotency_key' => $options['idempotency_key'] ?? CmnEnum::EMPTY_NULL,
            'user_id' => Auth::user()->id ?? CmnEnum::ONE,
            'amount' => $response['total_money']['amount'] ?? 0,
            'currency' => $response['total_money']['currency'],
            'captured' => $response['card_details']['status'] == strtoupper(CmnEnum::STATUS_CAPTURED) ?CmnEnum::ONE : CmnEnum::ZERO,
            'payment_status' => $response['paid'] ?? CmnEnum::EMPTY_NULL,
            'status' => $response['status'],
            'generic_payment_status' => $response['status'] == strtoupper(CmnEnum::STATUS_COMPLETE) ?CmnEnum::PS_PAID : CmnEnum::PS_UNPAID,
            'generic_status' => $response['status'] == strtoupper(CmnEnum::STATUS_COMPLETE) ?CmnEnum::STATUS_COMPLETE : CmnEnum::STATUS_OPEN,
            'description' => $response['description'] ?? CmnEnum::EMPTY_NULL,
            'payment_type' => $response['card_details']['card']['card_type'] ?? CmnEnum::EMPTY_NULL,
            'card_brand' => $response['card_details']['card']['card_brand'] ?? CmnEnum::EMPTY_NULL,
            'last_4_digit' => $response['card_details']['card']['last_4'] ?? CmnEnum::EMPTY_NULL,
            'customer_name' => $response['customer_details']['name'] ?? CmnEnum::EMPTY_NULL,
            'customer_email' => $response['customer_details']['email'] ?? CmnEnum::EMPTY_NULL,
            'customer_phone' => $response['customer_details']['phone'] ?? CmnEnum::EMPTY_NULL,
            'success_json' => json_encode($response),
            'updated_at' => now()
        ];
    }

    private function updatePaymentInDatabase($paymentId, $response, $options) 
    {  
        if (! (config('payments.store.in-database') === CmnEnum::STORE_IN_DB_AUTOMATIC)) {
            return true;
        }

        return $this->updatePaymentTableInDatabase($paymentId, $this->rearrangeDataForUpdate($response, $options));
    } 
    private function updateCapturePaymentInDatabase($paymentId, $response, $options) 
    {  
        if (! (config('payments.store.in-database') === CmnEnum::STORE_IN_DB_AUTOMATIC)) {
            return true;
        }

        return $this->updatePaymentTableInDatabase($paymentId, 
            array_merge(
                $this->rearrangeDataForUpdate($response, $options),
                [ 
                    'captured_at' => now()
                ]
            )
        );
    } 
    private function updateCancelPaymentInDatabase($paymentId, $response, $options) 
    {  
        if (! (config('payments.store.in-database') === CmnEnum::STORE_IN_DB_AUTOMATIC)) {
            return true;
        }

        return $this->updatePaymentTableInDatabase($paymentId, 
            array_merge(
                $this->rearrangeDataForUpdate($response, $options),
                [ 
                    'canceled_at' => now()
                ]
            )
        );
    } 
}