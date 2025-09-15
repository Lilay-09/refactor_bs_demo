<?php

namespace App\Services;

use Illuminate\Http\Request;

interface MerchantTransactionService
{
    //
    public function getRequestedSettlement(Request $req,object $authUser):object;
    public function approveAndSettleRequestedSettlement(int $paymentId,string $tranType,object $authUser):object;
    public function declineRequetedSettlement(int $paymentId,Request $req,object $authUser):object;
    public function approveAndSettleBulkRequestedSettlement(Request $req,object $authUser):object;
    public function getSettledPaymentTransactions(Request $req,$authUser):object;
    public function getSettledPaymentTransactionById(int $tranId,object $authUser):object;
}
