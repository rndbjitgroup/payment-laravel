<?php 

namespace Bjit\Payment\Traits\Payjp;

use Bjit\Payment\Enums\CmnEnum;
use Bjit\Payment\Helpers\CmnHelper;
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
            'refund_reason' => $options['refund_reason'] ?? CmnEnum::EMPTY_NULL
        ];
    } 

    public function formatRefundReponse($response)
    {
        if(isset($response['error'])) {
            return $this->formatErrorResponse($response);
        } 

        return [
            'provider' => CmnEnum::PROVIDER_PAYJP,
            'id' => $response['id'],
            'amount' => $response['amount'],
            'amount_refunded' => $response['amount_refunded'],
            'currency' => $response['currency'],
            'status' => $response['refunded'] ?? CmnEnum::EMPTY_NULL,
            'generic_refund_status' => $response['refunded'] ? CmnEnum::RS_REFUNDED : CmnEnum::RS_NOT_REFUNDED,
            'generic_status' => $response['refunded'] ? CmnEnum::STATUS_COMPLETE : CmnEnum::STATUS_OPEN,
            'provider_response' => $response
        ];
    } 
    
    public function refundPayment($paymentId, $options = [])
    {
        if (! (config('payments.store.in-database') === CmnEnum::STORE_IN_DB_AUTOMATIC)) {
            return CmnEnum::TRUE;
        }

        $ch = Charge::retrieve($paymentId);
         
        if(is_null($options)) {
            return $ch->refund();
        }

        $response = $ch->refund($this->formatRefundInput($options));

        $this->storeRefundInDatabase($response, $options, $paymentId);
        return $this->formatRefundReponse($response);
    } 
    
    

    private function storeRefundInDatabase($response, $options, $providerPaymentId)
    {
        if (! (config('payments.store.in-database') === CmnEnum::STORE_IN_DB_AUTOMATIC)) {
            return CmnEnum::TRUE;
        }

        DB::beginTransaction();

        try {
            $paymentId = $this->getPaymentIdByProviderPaymentId($providerPaymentId);
            $now = now();

            DB::table(CmnEnum::TABLE_REFUND_NAME)->insert([ 
                'payment_id' => $paymentId,
                'provider' => CmnEnum::PROVIDER_PAYJP,
                'provider_refund_id' => $response['id'],
                'user_id' => Auth::user()->id ?? CmnEnum::ONE,
                'amount' => $response['amount'],
                'amount_refunded' => $response['amount_refunded'],
                'currency' => $response['currency'],
                'status' => $response['refunded'] ?? CmnEnum::EMPTY_NULL,
                'generic_refund_status' => $response['refunded'] ? CmnEnum::RS_REFUNDED : CmnEnum::RS_NOT_REFUNDED,
                'generic_status' => $response['refunded'] ? CmnEnum::STATUS_COMPLETE : CmnEnum::STATUS_OPEN,
                'refund_reason' => $response['refund_reason'] ?? CmnEnum::EMPTY_NULL,
                'success_json' => CmnHelper::jsonEncodePrivate($response),
                'created_at' => $now,
                'updated_at' => $now
            ]);

            if(isset($paymentId) && $paymentId > CmnEnum::ZERO) { 
                DB::table(CmnEnum::TABLE_PAYMENT_NAME)->where('id', $paymentId)->update([
                    'refunded_at' => $now
                ]);
            }

            DB::commit();
            return CmnEnum::TRUE;
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