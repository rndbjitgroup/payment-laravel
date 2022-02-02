<?php 

namespace Bjit\Payment\Traits\Paypal;

use Bjit\Payment\Enums\CmnEnum;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

trait Invoiceable 
{
    public function formatInvoiceInput($options)
    {
        return $options;
    }

    public function formatInvoiceResponse($response)
    { 
        return $response;
    } 

    public function createInvoice($options)
    {  
        $response = $this->paypal->createInvoice($this->formatInvoiceInput($options)); 
        return $this->formatInvoiceResponse($response);
    }

    public function retrieveInvoice($invoiceId, $options = [])
    {
        return $this->formatInvoiceResponse($this->paypal->showInvoiceDetails( $invoiceId ));
    }

    public function updateInvoice($invoiceId, $options = [])
    {
        $response = $this->paypal->updateInvoice( $invoiceId, $options ); 
        return $this->formatPaymentResponse($response);
    }

    public function deleteInvoice($invoiceId, $options = [])
    {
        $response = $this->paypal->deleteInvoice( $invoiceId ); 
        return $this->formatInvoiceResponse($response);
    } 

     
}