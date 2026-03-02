<?php

namespace App\Services;

use Illuminate\Http\Request;

interface PaywayService
{
    //
    public function bankABAKHQRGeneratePayload(object $authUser,Request $req):object;
    public function receiverPayCallbackUrl(array $data);
    public function DriverPayCallbackUrl(Request $req);
    public function deeplinkAfterKHQRScan(int $userId,$redirectUrls);
    public function streamRedirect(Request $req);
    // public function generateCheckTransactionPayload(object $authUser, Request $req): object;
    public function bankABAKHQRSettleGeneratePayload(Request $req,object $authUser): object;

    public function getPaywayLogs(Request $req,object $authUser):object;
    public function getTransDetails(string $tranId):object;
    public function saveTransactionDetails(string $tranId,array $data): object;

    public function payout(string $currency,float $totalAmount,array $payoutAccounts,int $count): object;

    public function paywaylog(
        string $tranId,
        object $user,
        string $type,
        string $provider,
        string $details,
        $apv = null,
        string $payload,
        ?string $status = null,
        string $notes = ''
    ): int;

    // public function payout(string $currency,float $totalAmount,array $accountsAndAmounts,): object;
}
