<?php

namespace App\Services;

use Illuminate\Http\Request;

interface MerchantTransactionService
{
    //
    public function getRequestedSettlement(Request $req,object $authUser):object;
    public function approveAndSettleRequestedSettlement(Request $req,object $authUser):object;
    public function declineRequetedSettlement(Request $req,object $authUser):object;
    public function approveAndSettleBulkRequestedSettlement(Request $req,object $authUser):object;
}
