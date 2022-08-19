<?php 

namespace Bjit\Payment\Traits\Squareup;

use Bjit\Payment\Enums\CmnEnum;
use Exception;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Square\Models\Money;
use Square\Models\RefundPaymentRequest;

trait Refundable
{
    private function formatRefundListInput($options)
    {
        $input = [];

        $input[] = $options['begin_time'] ?? CmnEnum::EMPTY_NULL;
        $input[] = $options['end_time'] ?? CmnEnum::EMPTY_NULL;
        $input[] = $options['sort_order'] ?? CmnEnum::EMPTY_NULL;
        $input[] = $options['cursor'] ?? CmnEnum::EMPTY_NULL;
        $input[] = $options['location_id'] ?? CmnEnum::EMPTY_NULL;
        $input[] = $options['status'] ?? CmnEnum::EMPTY_NULL;
        $input[] = $options['source_type'] ?? CmnEnum::EMPTY_NULL; 
        $input[] = $options['limit'] ?? CmnEnum::EMPTY_NULL; 
        
        return $input;
    }

    private function formatRefundInput($options, $paymentId)
    {    
        $amountMoney = new Money();
        $amountMoney->setAmount($options['amount']);
        $amountMoney->setCurrency($options['currency']);

        $appFeeMoney = CmnEnum::EMPTY_NULL;
        if(isset($options['app_fee_amount'])) {
            $appFeeMoney = new Money();
            $appFeeMoney->setAmount($options['app_fee_amount']);

            if(isset($options['app_fee_currency'])) {
                $appFeeMoney->setCurrency($options['app_fee_currency']);
            }
        }
        

        $idempotencyKey = (string) Str::uuid();
        
        $body = new RefundPaymentRequest($idempotencyKey, $amountMoney);
        if(!is_null($appFeeMoney)) {
            $body->setAppFeeMoney($appFeeMoney);
        }
        $body->setPaymentId($paymentId);
        $body->setReason($options['refund_reason']);

        if(isset($options['payment_version_token'])) {
            $body->setPaymentVersionToken($options['payment_version_token']);
        }
        if(isset($options['team_member_id'])) {
            $body->setTeamMemberId($options['team_member_id']);
        }

        return [
            'idempotency_key' => $idempotencyKey,
            'body' => $body
        ];
    } 

    private function formatRefundReponse($response)
    {
        if (!$response->isSuccess()) {
            return ['error' => $response->getErrors()];
        }
 
        $responseFromStr = json_decode($response->getBody(), true);
        $response = $responseFromStr['refund'];
        
        return [
            'provider' => CmnEnum::PROVIDER_SQUAREUP,
            'id' => $response['id'],
            'amount' => $this->getPaymentAmount($response['payment_id']),
            'amount_refunded' => $response['amount_money']['amount'],
            'currency' => $response['amount_money']['currency'],
            'status' => $response['status'],
            'generic_refund_status' => $response['status'] == CmnEnum::STATUS_SUCCEEDED ? CmnEnum::RS_REFUNDED : CmnEnum::RS_NOT_REFUNDED, 
            'generic_status' => $response['status'] == CmnEnum::STATUS_SUCCEEDED ? CmnEnum::STATUS_COMPLETE : CmnEnum::STATUS_OPEN,
            'provider_response' => $response
        ];
    }

    public function refundPayment($paymentId, $options = [])
    {
        $responseFromInput = $this->formatRefundInput($options, $paymentId);
        $response = $this->squareup->getRefundsApi()->refundPayment($responseFromInput['body']);
    
        if ($response->isSuccess()) {
            $responseFromStr = json_decode($response->getBody(), true);
            $responseFromStr = $responseFromStr['refund'];
            $options['idempotency_key'] = $responseFromInput['idempotency_key'];
            $this->storeRefundInDatabase($responseFromStr, $options, $paymentId);
        }
        return $this->formatRefundReponse($response);
    }

    public function retrieveRefund($refundId, $options = [])
    {
        return $this->formatRefundReponse($this->squareup->getRefundsApi()->getPaymentRefund( $refundId ));
    } 

    public function allRefunds($options = [])
    { 
        $response = $this->squareup->getRefundsApi()->listPaymentRefunds(...$this->formatRefundListInput($options));
        $responseFromStr = json_decode($response->getBody(), true);
        //$responseFromStr = $responseFromStr['refund'];
        return $responseFromStr['refunds'];
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
                'provider' => CmnEnum::PROVIDER_SQUAREUP,
                'provider_refund_id' => $response['id'],
                'user_id' => Auth::user()->id ?? CmnEnum::ONE,
                'amount' => $this->getPaymentAmount($response['payment_id']),
                'amount_refunded' => $response['amount_money']['amount'],
                'currency' => $response['amount_money']['currency'],
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

    private function getPaymentIdByProviderPaymentId($id)
    {
        return optional(
            DB::table(CmnEnum::TABLE_PAYMENT_NAME)->where('provider_payment_id', $id)->first(['id'])
        )->id;
    } 

}