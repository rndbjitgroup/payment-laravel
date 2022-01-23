<?php 

namespace Bjit\Payment\Traits\Payjp;

use Bjit\Payment\Enums\CmnEnum;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Payjp\Charge;

trait Paymentable 
{
    public function formatPaymentInput($options)
    {
        return [
            'amount' => $options['amount'],
            'currency' => $options['currency'] ?? 'jpy',
            'card' => $options['nonce'] ?? '',
            'description' => $options['description'] ?? ''
            //'tenant' => $options['description'] ?? ''
        ];
    }

    public function formatPaymentResponse($response)
    {
        //dd($response);
        return [
            'provider' => CmnEnum::PROVIDER_PAYJP,
            'id' => $response['id'],
            'amount' => $response['amount'], 
            'currency' => $response['currency'],
            'payment_status' => $response['paid'] ?? null,
            'status' => $response['status'] ?? null,
            'generic_payment_status' => $response['paid'] ? CmnEnum::PS_PAID : CmnEnum::PS_UNPAID,
            'generic_status' => $response['paid'] ? CmnEnum::STATUS_COMPLETE : CmnEnum::STATUS_OPEN,
            'description' => $response['description'],
            'payment_type' => CmnEnum::PT_CARD,
            'card_brand' => $response['card']['brand'] ?? null,
            'last_4_digit' => $response['card']['last4'] ?? null,
            'customer_name' =>$response['card']['name'] ?? null,
            'customer_email' => $response['customer_details']['email'] ?? null,
            'customer_phone' => $response['customer_details']['phone'] ?? null,
            'provider_response' => $response
        ];
    }

    public function allPayments($options)
    {
        return Charge::all([
            'limit' => $options['limit'] ?? CmnEnum::LIMIT, 
            'offset' => $options['offset'] ?? CmnEnum::OFFSET
        ]);
    }

    public function createPayment($options)
    { 
        //dd($options, $this->formatPaymentInput($options));
        $response = Charge::create($this->formatPaymentInput($options)); 
        $this->storePaymentInDatabase($response, $options, CmnEnum::PT_DIRECT_PAYMENT);
        return $this->formatPaymentResponse($response);
    }

    public function retrievePayment($paymentId, $options = [])
    {
        return $this->formatPaymentResponse(Charge::retrieve($paymentId));
    }

    public function updatePayment($paymentId, $options = [])
    {
        $obj = Charge::retrieve($paymentId);
        if($options['amount']) {
            $obj->amount = $options['amount'];
        }
        if($options['description']) {
            $obj->description = $options['description'];
        }
        if($options['currency']) {
            $obj->currency = $options['currency'];
        }
        return $obj->save();
    }

    public function capturePayment($paymentId, $options = [])
    {
        $ch = Charge::retrieve($paymentId);
        return $ch->capture(); 
    }

    private function storePaymentInDatabase($response, $options, $paymentType)
    { 
        if (! (config('payments.store.in-database') === CmnEnum::STORE_IN_DB_AUTOMATIC)) {
            return true;
        }

        return DB::table(CmnEnum::TABLE_PAYMENT_NAME)->insert([
            //'state' => $options['state'], 
            'type' => $paymentType,
            'provider' => CmnEnum::PROVIDER_PAYJP,
            'provider_payment_id' => $response['id'],
            'user_id' => Auth::user()->id ?? CmnEnum::ONE,
            'amount' => $response['amount'],
            'currency' => $response['currency'],
            'payment_status' => $response['paid'] ?? null,
            'status' => $response['status'] ?? null,
            'generic_payment_status' => $response['paid'] ? CmnEnum::PS_PAID : CmnEnum::PS_UNPAID,
            'generic_status' => $response['paid'] ? CmnEnum::STATUS_COMPLETE : CmnEnum::STATUS_OPEN,
            'description' => $response['description'],
            'payment_type' => CmnEnum::PT_CARD,
            'card_brand' => $response['card']['brand'] ?? null,
            'last_4_digit' => $response['card']['last4'] ?? null,
            'customer_name' =>$response['card']['name'] ?? null,
            'customer_email' => $response['customer_details']['email'] ?? null,
            'customer_phone' => $response['customer_details']['phone'] ?? null,
            'success_json' => json_encode($response),
            'created_at' => now(),
            'updated_at' => now()
        ]);
    }

}