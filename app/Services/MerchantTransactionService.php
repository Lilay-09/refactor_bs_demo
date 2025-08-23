<?php

namespace App\Services;

use Illuminate\Http\Request;

interface MerchantTransactionService
{
    //
    public function getRequestedSettlement(Request $req,object $authUser):object;
}
