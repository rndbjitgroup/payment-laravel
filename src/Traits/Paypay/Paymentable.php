<?php 

namespace Bjit\Payment\Traits\Paypay;

use Bjit\Payment\Enums\CmnEnum;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use PayPay\OpenPaymentAPI\Models\AccountLinkPayload;
use PayPay\OpenPaymentAPI\Models\CapturePaymentAuthPayload;
use PayPay\OpenPaymentAPI\Models\CreateQrCodePayload;

trait Paymentable 
{
    public function formatPaymentInput($options)
    {
        return [
            'amount' => $options['amount'],
            'currency' => strtoupper($options['currency']) ?? 'JPY',
            //'card' => $options['nonce'] ?? '',
            'description' => $options['description'] ?? null,
            //'success_url' => $options['success_url']
        ];
    }

    public function formatPaymentResponse($response)
    { 
        return [
            'provider' => CmnEnum::PROVIDER_PAYPAY,
            'id' => $response['id'],
            'amount' => $response['amount'], 
            'currency' => $response['currency'],
            'payment_status' => $response['paid'] ?? null,
            'status' => $response['status'] ?? null,
            'generic_payment_status' => $response['paid'] ? CmnEnum::PS_PAID : CmnEnum::PS_UNPAID,
            'generic_status' => $response['status'] == CmnEnum::STATUS_SUCCEEDED ? CmnEnum::STATUS_COMPLETE : CmnEnum::STATUS_OPEN,
            'description' => $response['description'],
            'payment_type' => $response['payment_method_details']['type'] ?? $response['payment_method_types'][CmnEnum::ZERO] ?? null,
            'card_brand' => $response['payment_method_details']['card']['brand'] ?? null,
            'last_4_digit' => $response['payment_method_details']['card']['last4'] ?? null,
            'created_at' => $response['created'],
            'customer_name' => $response['customer']['name'] ?? null,
            'customer_email' => $response['customer']['email'] ?? null,
            'customer_phone' => $response['customer']['phone'] ?? null,
            'provider_response' => $response
        ];
    }

    public function accountLinkPayload()
    {
        $payload = new AccountLinkPayload();
        $payload
            ->setScopes($this->additionalConfig['scopes'])
            ->setRedirectUrl($this->additionalConfig['redirect'])
            ->setReferenceId(uniqid("ppap" . date('YmdHis')));
        //dd($this->additionalConfig, $this->paypay, $payload);

        return $this->paypay->user->createAccountLinkQrCode($payload);
    } 

    private function getPaymentAmount($paymentId)
    {
        $payment = $this->retrievePayment($paymentId);
        return $payment['amount'];
    }

    public function createPayment($options)
    { 
         
        $cQCPayload = new CreateQrCodePayload();
        $cQCPayload->setMerchantPaymentId(uniqid('ppp') . date('YmdHis'));
        $cQCPayload->setRequestedAt();
        $cQCPayload->setCodeType(CmnEnum::PAYPAY_ORDER_TYPE); 
        
        $cQCPayload->setAmount($this->formatPaymentInput($options));
 
        $cQCPayload->setRedirectType(CmnEnum::PAYPAY_REDIRECT_TYPE_WEB_LINK);
        $cQCPayload->setRedirectUrl($this->additionalConfig['redirect']);
        //$cQCPayload->setIsAuthorization(true);
 
        $response = $this->paypay->code->createQRCode($cQCPayload);
        $this->storePaymentInDatabase($response, $options, CmnEnum::PT_DIRECT_PAYMENT);
        //dump($response['data']['merchantPaymentId'], $options); //die;
        //$this->capturePayment($response['data']['merchantPaymentId'], $options);
        return $response; 
    }

    public function retrievePayment($paymentId, $options = [])
    {
        return $this->paypay->code->getPaymentDetails($paymentId);
    }

    public function updatePayment($paymentId, $options = [])
    {
        
    }

    public function capturePayment($paymentId, $options = [])
    {
        $cAPayload = new CapturePaymentAuthPayload();
        $cAPayload->setMerchantPaymentId($paymentId);

        $cAPayload->setAmount($this->formatPaymentInput($options));

        $cAPayload->setMerchantCaptureId(uniqid('ppmc') . date('YmdHis'));
        $cAPayload->setRequestedAt();
        $cAPayload->setOrderDescription("ORDER DESCRIPTION TEST 101");
        //$cAPayload->setCodeType("ORDER_QR");

        return $this->paypay->payment->capturePaymentAuth($cAPayload);
    }

    public function cancelPayment($paymentId, $options = [])
    {
        return $this->paypay->payment->cancelPayment($paymentId);
    }

    public function deletePayment($codeId, $options = [])
    {
        return $this->paypay->code->deleteQRCode($codeId);
    }

    private function storePaymentInDatabase($response, $options, $paymentType)
    {  
        if (! (config('payments.store.in-database') === CmnEnum::STORE_IN_DB_AUTOMATIC)) {
            return true;
        }

        return DB::table(CmnEnum::TABLE_PAYMENT_NAME)->insert([
            'state' => $options['state'] ?? null,
            'type' => $paymentType,
            'provider' => CmnEnum::PROVIDER_PAYPAY,
            'provider_payment_id' => $response['data']['merchantPaymentId'], 
            'user_id' => Auth::user()->id ?? CmnEnum::ONE,
            'amount' => $response['amount_total'] ?? $response['data']['amount']['amount'] ?? 0,
            'currency' => $response['data']['amount']['currency'],
            'payment_status' => $response['resultInfo']['code'] == CmnEnum::STATUS_PAYPAY_CODE ? CmnEnum::STATUS_PAYPAY_CREATED : null,
            'status' => $response['resultInfo']['code'] == CmnEnum::STATUS_PAYPAY_CODE ? CmnEnum::STATUS_PAYPAY_CREATED : null,
            'generic_payment_status' => $response['resultInfo']['code'] == CmnEnum::STATUS_PAYPAY_CODE ? CmnEnum::PS_UNPAID : null,
            'generic_status' => $response['resultInfo']['code'] == CmnEnum::STATUS_PAYPAY_CODE ? CmnEnum::STATUS_OPEN : null,
            'description' => $response['data']['description'] ?? null,
            'success_json' => json_encode($response),
            'created_at' => now(),
            'updated_at' => now()
        ]);
    } 

    private function updatePaymentInDatabase($paymentId, $response, $options) 
    {  
        if (! (config('payments.store.in-database') === CmnEnum::STORE_IN_DB_AUTOMATIC)) {
            return true;
        }

        return DB::table(CmnEnum::TABLE_PAYMENT_NAME)
            ->where('provider', CmnEnum::PROVIDER_PAYPAY)
            ->where('provider_payment_id', $paymentId)
            ->update([  
                'user_id' => Auth::user()->id ?? CmnEnum::ONE,
                'amount' => $response['amount_total'] ?? $response['data']['amount']['amount'] ?? 0,
                'currency' => $response['data']['amount']['currency'],
                'payment_status' => $response['resultInfo']['code'] == CmnEnum::STATUS_PAYPAY_CODE ? CmnEnum::STATUS_PAYPAY_CREATED : null,
                'status' => $response['resultInfo']['code'] == CmnEnum::STATUS_PAYPAY_CODE ? CmnEnum::STATUS_PAYPAY_CREATED : null,
                'generic_payment_status' => $response['resultInfo']['code'] == CmnEnum::STATUS_PAYPAY_CODE ? CmnEnum::PS_UNPAID : null,
                'generic_status' => $response['resultInfo']['code'] == CmnEnum::STATUS_PAYPAY_CODE ? CmnEnum::STATUS_OPEN : null,
                'description' => $response['data']['description'] ?? null,
                'success_json' => json_encode($response), 
                'updated_at' => now()
            ]);
    }

}