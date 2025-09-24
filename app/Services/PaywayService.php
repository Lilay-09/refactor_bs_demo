<?php

namespace App\Services;

use Illuminate\Http\Request;

interface PaywayService
{
    //
    public function bankABAKHQRGeneratePayload(object $authUser,Request $req):object;
    public function receiverPayCallbackUrl(Request $req);
    public function DriverPayCallbackUrl(Request $req);
    public function deeplinkAfterKHQRScan(int $userId,$redirectUrls);
    public function streamRedirect(Request $req);
    public function generateCheckTransactionPayload(object $authUser, Request $req): object;
    public function bankABAKHQRSettleGeneratePayload(Request $req,object $authUser): object;

    public function getPaywayLogs(Request $req,object $authUser):object;

    // public function payout(string $currency,float $totalAmount,array $accountsAndAmounts,): object;
}
