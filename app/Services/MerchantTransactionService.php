<?php

namespace App\Services;

use Illuminate\Http\Request;

interface MerchantTransactionService
{
    //
    public function getRequestedSettlement(array $filters,object $authUser):object;
    public function approveAndSettleRequestedSettlement(int $paymentId,string $tranType,object $authUser):object;
    public function declineRequetedSettlement(int $paymentId,array $data,object $authUser):object;
    public function approveAndSettleBulkRequestedSettlement(array $data,object $authUser):object;
    public function getSettledPaymentTransactions(array $filters,object $authUser):object;
    public function getSettledPaymentTransactionById(int $tranId,object $authUser):object;
}
