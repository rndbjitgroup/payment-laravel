<?php 

namespace Bjit\Payment\Traits\TwoCheckout;

use Bjit\Payment\Enums\CmnEnum;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;

trait Refundable
{
    public function formatRefundInput($options, $capturedId)
    {
        $formatInput = [
            'captured_id' => $capturedId
        ];

        if (isset($options['invoice_id']) && !empty($options['invoice_id'])) {
            $formatInput['invoice_id'] = $options['invoice_id'];
        }

        if (isset($options['amount']) && $options['amount'] >= CmnEnum::ONE) {
            $formatInput['amount'] = $options['amount'];
        }

        if (isset($options['refund_reason'])) {
            $formatInput['reason'] = $options['refund_reason'];
        } 

        return $formatInput;
    } 

    public function formatRefundReponse($response)
    {
        if(isset($response['error'])) {
            return ['error' => $response['error']];
        }

        return [
            'provider' => CmnEnum::PROVIDER_PAYPAL,
            'id' => $response['id'],
            'amount' => $this->getPaymentAmountByProviderRefundId($response['id']),
            'amount_refunded' => $response['amount']['value'] ?? '',
            'currency' => $response['amount']['currency_code'] ?? '',
            'status' => $response['status'],
            'generic_refund_status' => $response['status'] == strtoupper(CmnEnum::STATUS_COMPLETE) ? CmnEnum::RS_REFUNDED : CmnEnum::RS_NOT_REFUNDED, 
            'generic_status' => $response['status'] == strtoupper(CmnEnum::STATUS_COMPLETE) ? CmnEnum::STATUS_COMPLETE : CmnEnum::STATUS_OPEN,
            'provider_response' => $response
        ];
    }

    public function refundPayment($capturedId, $options = [])
    { 
        $formatedInput = $this->formatRefundInput($options, $capturedId);
        $response = $this->paypal->refundCapturedPayment(
            $formatedInput['captured_id'],
            $formatedInput['invoice_id'] ?? '',
            $formatedInput['amount'] ?? null,
            $formatedInput['reason'] ?? null,
        );  
     
        if(isset($response['status']) && $response['status'] == strtoupper(CmnEnum::STATUS_COMPLETE)) {
            $response = $this->paypal->showRefundDetails( $response['id'] );
            $this->storeRefundInDatabase($response, $options, $capturedId);
        }

        return $this->formatRefundReponse($response);
    }

    public function retrieveRefund($refundId, $options = [])
    {
        return $this->formatRefundReponse( $this->paypal->showRefundDetails( $refundId ) );
    } 

    private function storeRefundInDatabase($response, $options, $providerPaymentId)
    {  
        if (! (config('payments.store.in-database') === CmnEnum::STORE_IN_DB_AUTOMATIC)) {
            return true;
        } 

        DB::beginTransaction();

        try {
            $paymentId = $this->getPaymentIdByProviderPaymentId($providerPaymentId);
            $paymentAmount = $this->getPaymentAmountByProviderPaymentId($providerPaymentId);
            $now = now();

            DB::table(CmnEnum::TABLE_REFUND_NAME)->insert([
                //'state' => $options['state'], 
                'payment_id' => $paymentId,
                'provider' => CmnEnum::PROVIDER_PAYPAL,
                'provider_refund_id' => $response['id'],
                'user_id' => Auth::user()->id ?? CmnEnum::ONE,
                'amount' => $paymentAmount,
                'amount_refunded' => $response['amount']['value'] ?? '',
                'currency' => $response['amount']['currency_code'],
                'status' => $response['status'],
                'generic_refund_status' => $response['status'] == strtoupper(CmnEnum::STATUS_COMPLETE) ? CmnEnum::RS_REFUNDED : CmnEnum::RS_NOT_REFUNDED, 
                'generic_status' => $response['status'] == strtoupper(CmnEnum::STATUS_COMPLETE) ? CmnEnum::STATUS_COMPLETE : CmnEnum::STATUS_OPEN,
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

    // private function updateRefundInDatabase($refundId, $response, $options, $providerPaymentId)
    // { 
    //     if (! (config('payments.store.in-database') === CmnEnum::STORE_IN_DB_AUTOMATIC)) {
    //         return true;
    //     }

    //     DB::beginTransaction();

    //     try {
    //         $paymentId = $this->getPaymentIdByProviderPaymentId($providerPaymentId);
    //         $paymentAmount = $this->getPaymentAmountByProviderPaymentId($providerPaymentId);
    //         $now = now();

    //         DB::table(CmnEnum::TABLE_REFUND_NAME)
    //             ->where('provider', CmnEnum::PROVIDER_PAYPAL)
    //             ->where('provider_refund_id', $refundId)
    //             ->update([ 
    //                 'user_id' => Auth::user()->id ?? CmnEnum::ONE,
    //                 'amount' => $paymentAmount,
    //                 'amount_refunded' => $response['amount']['value'] ?? '',
    //                 'currency' => $response['amount']['currency_code'],
    //                 'status' => $response['status'],
    //                 'generic_refund_status' => $response['status'] == strtoupper(CmnEnum::STATUS_COMPLETE) ? CmnEnum::RS_REFUNDED : CmnEnum::RS_NOT_REFUNDED, 
    //                 'generic_status' => $response['status'] == strtoupper(CmnEnum::STATUS_COMPLETE) ? CmnEnum::STATUS_COMPLETE : CmnEnum::STATUS_OPEN,
    //                 'refund_reason' => $response['reason'] ?? '',
    //                 'success_json' => json_encode($response), 
    //                 'updated_at' => $now
    //             ]);

    //         DB::table(CmnEnum::TABLE_PAYMENT_NAME)->where('id', $paymentId)->update([
    //             'refunded_at' => $now
    //         ]);

    //         DB::commit();
    //         return true;
    //     } catch (Exception $e) {
    //         DB::rollBack();
    //         throw new RuntimeException($e->getMessage()); 
    //     }
        
    // }

    private function getPaymentIdByProviderPaymentId($id)
    {
        return optional(
            DB::table(CmnEnum::TABLE_PAYMENT_NAME)
            ->where(function($q) use ($id) {
                $q->where('provider_payment_id', $id)
                ->orWhere('provider_payment_intent_id', $id)
                ->orWhere('provider_captured_id', $id)
                ->orWhere('provider_authorized_captured_id', $id);
            })
            ->first(['id'])
        )->id;
    }

    private function getPaymentAmountByProviderPaymentId($id)
    {
        return optional(
            DB::table(CmnEnum::TABLE_PAYMENT_NAME)
            ->where(function($q) use ($id) {
                $q->where('provider_payment_id', $id)
                ->orWhere('provider_payment_intent_id', $id)
                ->orWhere('provider_captured_id', $id)
                ->orWhere('provider_authorized_captured_id', $id);
            })
            ->first(['amount'])
        )->amount;
    }

    private function getPaymentAmountByProviderRefundId($id)
    {
        return optional(
            DB::table(CmnEnum::TABLE_REFUND_NAME) 
            ->where('provider_refund_id', $id)
            ->first(['amount'])
        )->amount;
    }

}