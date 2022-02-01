<?php 

namespace Bjit\Payment\Traits\Paypay;

use Bjit\Payment\Enums\CmnEnum;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use PayPay\OpenPaymentAPI\Models\RefundPaymentPayload;
use RuntimeException;

trait Refundable
{
    public function formatRefundInput($options, $paymentId)
    {
        $formatInput = [];

        if(str_contains($paymentId, CmnEnum::STRIPE_PAYMENT_INTENT_PREFIX)) {
            $formatInput = [
                'payment_intent' => $paymentId
            ];
        } else {
            $formatInput = [
                'charge' => $paymentId
           ];
        }

        if(isset($options['amount']) && $options['amount'] >= CmnEnum::ONE) {
            $formatInput['amount'] = $options['amount'];
        }

        if(isset($options['refund_reason']) && in_array($options['refund_reason'], CmnEnum::REFUND_STRIPE_REASONS)) {
            $formatInput['reason'] = $options['refund_reason'];
        } 

        return $formatInput;
    } 

    public function formatRefundReponse($response)
    {
        return [
            'provider' => CmnEnum::PROVIDER_STRIPE,
            'id' => $response['id'],
            'amount' => $this->getPaymentAmount($response['charge']),
            'amount_refunded' => $response['amount'],
            'currency' => $response['currency'],
            'status' => $response['status'],
            'generic_refund_status' => $response['status'] == CmnEnum::STATUS_SUCCEEDED ? CmnEnum::RS_REFUNDED : CmnEnum::RS_NOT_REFUNDED, 
            'generic_status' => $response['status'] == CmnEnum::STATUS_SUCCEEDED ? CmnEnum::STATUS_COMPLETE : CmnEnum::STATUS_OPEN,
            'provider_response' => $response
        ];
    }

    public function refundPayment($paymentId, $options = [])
    {
        $amount = [
            "amount" => 1,
            "currency" => "JPY"
        ];

        $rPPayload = new RefundPaymentPayload();
        $rPPayload
          ->setMerchantRefundId(uniqid('rf' . date('YmdHis')))
          ->setPaymentId($paymentId)
          ->setAmount($amount)
          ->setRequestedAt(); 

        return $this->paypay->refund->refundPayment($rPPayload);
    }

    public function retrieveRefund($refundId, $options = [])
    {
        return $this->paypay->refunds->retrieve( $refundId, $options );
    }

    public function updateRefund($refundId, $options = [])
    {
        return $this->paypay->refunds->update( $refundId, $options );
    } 

    private function storeRefundInDatabase($response, $options, $providerPaymentId)
    { 
        if (! (config('payments.store.in-database') === CmnEnum::STORE_IN_DB_AUTOMATIC)) {
            return true;
        }

        DB::beginTransaction();

        try {
            $paymentId = $this->getPaymentIdByProviderPaymentId($providerPaymentId);
            $now = now();

            DB::table(CmnEnum::TABLE_REFUND_NAME)->insert([
                //'state' => $options['state'], 
                'payment_id' => $paymentId,
                'provider' => CmnEnum::PROVIDER_PAYPAY,
                'provider_refund_id' => $response['id'],
                'user_id' => Auth::user()->id ?? CmnEnum::ONE,
                'amount' => $this->getPaymentAmount($response['charge']),
                'amount_refunded' => $response['amount'],
                'currency' => $response['currency'],
                'status' => $response['status'],
                'generic_refund_status' => $response['status'] == CmnEnum::STATUS_SUCCEEDED ? CmnEnum::RS_REFUNDED : CmnEnum::RS_NOT_REFUNDED, 
                'generic_status' => $response['status'] == CmnEnum::STATUS_SUCCEEDED ? CmnEnum::STATUS_COMPLETE : CmnEnum::STATUS_OPEN,
                'refund_reason' => $response['reason'] ?? '',
                'success_json' => json_encode($response),
                'created_at' => $now,
                'updated_at' => $now
            ]);

            DB::table(CmnEnum::TABLE_PAYMENT_NAME)->where('id', $paymentId)->update([
                'refunded_at' => $now
            ]);

            DB::commit();
            return true;
        } catch (Exception $e) {
            DB::rollBack();
            throw new RuntimeException($e->getMessage()); 
        }
        
    }

    private function updateRefundInDatabase($refundId, $response, $options, $providerPaymentId)
    { 
        if (! (config('payments.store.in-database') === CmnEnum::STORE_IN_DB_AUTOMATIC)) {
            return true;
        }

        DB::beginTransaction();

        try {
            $paymentId = $this->getPaymentIdByProviderPaymentId($providerPaymentId);
            $now = now();

            DB::table(CmnEnum::TABLE_REFUND_NAME)
                ->where('provider', CmnEnum::PROVIDER_PAYPAY)
                ->where('provider_refund_id', $paymentId)
                ->update([ 
                    'user_id' => Auth::user()->id ?? CmnEnum::ONE,
                    'amount' => $this->getPaymentAmount($response['charge']),
                    'amount_refunded' => $response['amount'],
                    'currency' => $response['currency'],
                    'status' => $response['status'],
                    'generic_refund_status' => $response['status'] == CmnEnum::STATUS_SUCCEEDED ? CmnEnum::RS_REFUNDED : CmnEnum::RS_NOT_REFUNDED, 
                    'generic_status' => $response['status'] == CmnEnum::STATUS_SUCCEEDED ? CmnEnum::STATUS_COMPLETE : CmnEnum::STATUS_OPEN,
                    'refund_reason' => $response['reason'] ?? '',
                    'success_json' => json_encode($response), 
                    'updated_at' => $now
                ]);

            DB::table(CmnEnum::TABLE_PAYMENT_NAME)->where('id', $paymentId)->update([
                'refunded_at' => $now
            ]);

            DB::commit();
            return true;
        } catch (Exception $e) {
            DB::rollBack();
            throw new RuntimeException($e->getMessage()); 
        }
        
    }

    private function getPaymentIdByProviderPaymentId($id)
    {
        return optional(
            DB::table(CmnEnum::TABLE_PAYMENT_NAME)->where('provider_payment_id', $id)->orWhere('provider_payment_intent_id', $id)->first(['id'])
        )->id;
    }
}