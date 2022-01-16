<?php 

namespace Bjit\Payment\Traits\Payjp;

use Bjit\Payment\Enums\CmnEnum;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Payjp\Charge;
use RuntimeException;

trait Refundable 
{
    public function formatRefundInput($options)
    {
        return [
            'amount' => $options['amount'],
            'refund_reason' => $options['refund_reason'] ?? ''
        ];
    } 

    public function formatRefundReponse($response)
    {
        return [
            'provider' => CmnEnum::PROVIDER_PAYJP,
            'id' => $response['id'],
            'amount' => $response['amount'],
            'amount_refunded' => $response['amount_refunded'],
            'currency' => $response['currency'],
            'status' => $response['refunded'] ?? null,
            'generic_refund_status' => $response['refunded'] ? CmnEnum::RS_REFUNDED : CmnEnum::RS_NOT_REFUNDED,
            'generic_status' => $response['refunded'] ? CmnEnum::STATUS_COMPLETE : CmnEnum::STATUS_OPEN,
            'provider_response' => $response
        ];
    } 
    
    public function refundPayment($paymentId, $options = [])
    {
        if (! (config('payments.store.in-database') === CmnEnum::STORE_IN_DB_AUTOMATIC)) {
            return true;
        }

        $ch = Charge::retrieve($paymentId);
         
        if(is_null($options)) {
            return $ch->refund();
        }

        $response = $ch->refund($this->formatRefundInput($options));

        $this->storeRefund($response, $options, $paymentId);
        return $this->formatRefundReponse($response);
    } 
    
    

    private function storeRefund($response, $options, $providerPaymentId)
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
                'provider' => CmnEnum::PROVIDER_PAYJP,
                'provider_refund_id' => $response['id'],
                'user_id' => Auth::user()->id ?? CmnEnum::ONE,
                'amount' => $response['amount'],
                'amount_refunded' => $response['amount_refunded'],
                'currency' => $response['currency'],
                'status' => $response['refunded'] ?? null,
                'generic_refund_status' => $response['refunded'] ? CmnEnum::RS_REFUNDED : CmnEnum::RS_NOT_REFUNDED,
                'generic_status' => $response['refunded'] ? CmnEnum::STATUS_COMPLETE : CmnEnum::STATUS_OPEN,
                'refund_reason' => $response['refund_reason'] ?? '',
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
            //return false;
        }
        
    }

    private function getPaymentIdByProviderPaymentId($id)
    {
        return optional(
            DB::table(CmnEnum::TABLE_PAYMENT_NAME)->where('provider_payment_id', $id)->first(['id'])
        )->id;
    }

}