<?php

namespace App\Jobs;

use App\Enums\PaywayStatus;
use App\Models\PaywayLog;
use App\Services\PaywayServiceImpl;
use Illuminate\Support\Facades\DB;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class VerifyPaymentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $transactionId;
    public $paymentRef;
    public $maxRetries;
    public $topic;


    /**
     * Create a new job instance.
     */
    public function __construct($transactionId,$paymentRef,$topic,$maxRetries=5)
    {
        $this->transactionId = $transactionId;
        $this->paymentRef = $paymentRef;
        $this->topic = $topic;
        $this->maxRetries = $maxRetries;
    }

    /**
     * Execute the job.
     */

    public int $tries = 5; // Maximum 5 attempts
    public int $backoff = 1; // optional delay between retries

    public function handle()
    {
        $transaction = PaywayLog::find($this->transactionId);

        if (!$transaction) {
            // Throwing exception counts as a retry
            throw new \Exception('Transaction not found');
        }

        if ($transaction->status === PaywayStatus::DONE->value) {
            // Log::info('Transaction already paid', ['transaction_id' => $this->transactionId]);
            return;
        }
        $response = PaywayServiceImpl::verifyTransaction($transaction->tran_id);

        if ($response->error) {
            Log::error(json_encode($response));
        }else{
            $newData = [
                'tran_id' => $transaction->tran_id,
                // 'package_ref'      => $this->paymentRef ?? '',
                'amount'           => $response->data['amount'],
                'transaction_date' => $response->data['transaction_date'],
                'payment_method'   => $response->data['payment_method'],
                'payer'            => $response->data['payer'],
                'payee'            => $response->data['payee'],
            ];

            // $transaction->update([
            // 'status'  => PaywayStatus::DONE->value,
            //     'details' => DB::raw("COALESCE(details, '{}'::jsonb) || ?::jsonb"),
            // ], [json_encode($newData, JSON_UNESCAPED_UNICODE)]);
            DB::table('payway_logs')
            ->where('id', $transaction->id)
            ->update([
                'status'  => PaywayStatus::DONE->value,
                'details' => DB::raw("COALESCE(details, '{}'::jsonb) || '" . json_encode($newData, JSON_UNESCAPED_UNICODE) . "'::jsonb"),
            ]);



            $newData['error'] = false;
            PaywayServiceImpl::sendDriverPaymentTransaction($newData, $this->topic);
        }


    }

    public function failed(\Exception $exception)
    {
        // Called automatically after 5 failed attempts
        $transaction = PaywayLog::find($this->transactionId);
        if ($transaction) {
            Log::info('Failed');
            // $transaction->update([
            //     'status'  => 'failed',
            //     'message' => 'Payment verification failed after 5 attempts: ' . $exception->getMessage(),
            // ]);
        }

        Log::error('Payment job permanently failed', [
            'transaction_id' => $this->transactionId,
            'error' => $exception->getMessage(),
        ]);
    }

    // public function handle()
    // {
    //     $transaction = PaywayLog::find($this->transactionId);

    //     if (!$transaction || $transaction->status === PaywayStatus::DONE->value) {
    //         Log::info('Paid');
    //         return; // already paid or not found
    //     }
    //     Log::info('Reach => '.$this->transactionId);
    //     try {
    //         $transaction = PaywayLog::find($this->transactionId);

    //         // Retry if transaction not found
    //         if (!$transaction) {
    //             throw new \Exception('Transaction not found');
    //         }

    //         // Skip if already done
    //         if ($transaction->status === PaywayStatus::DONE->value) {
    //             Log::info('Transaction already paid', ['transaction_id' => $this->transactionId]);
    //             return;
    //         }

    //         // Call bank verification API
    //         $response = PaywayServiceImpl::verifyTransaction($this->transactionId);

    //         if ($response->error) {
    //             throw new \Exception('Payment not verified yet');
    //         }

    //         // Prepare new data
    //         $newData = [
    //             'package_ref'      => $this->paymentRef ?? '',
    //             'amount'           => $response->data['amount'],
    //             'transaction_date' => $response->data['transaction_date'],
    //             'payment_method'   => $response->data['payment_method'],
    //             'payer'            => $response->data['payer'],
    //             'payee'            => $response->data['payee'],
    //         ];

    //         // Update transaction
    //         $transaction->update([
    //             'status'  => PaywayStatus::DONE->value,
    //             'details' => DB::raw("details || '" . json_encode($newData) . "'::jsonb"),
    //         ]);

    //         // Send to driver
    //         PaywayServiceImpl::sendDriverPaymentTransaction($newData, $this->topic);

    //     } catch (\Exception $e) {
    //         // Retry only if attempts < maxRetries
    //         if ($this->attempts() < $this->maxRetries) {
    //             Log::warning('Payment job retrying...', [
    //                 'transaction_id' => $this->transactionId,
    //                 'attempt' => $this->attempts() + 1,
    //                 'error' => $e->getMessage(),
    //             ]);

    //             $queueFCMName = config('queue_job_names.' . config('app.env') . '.payment');
    //             self::dispatch($this->transactionId,$this->paymentRef,$this->topic,$this->maxRetries)
    //                 ->onQueue($queueFCMName)
    //                 ->delay(now()->addSeconds(1)); // optional delay before retry
    //         } else {
    //             // Mark failed after max retries
    //             if ($transaction) {
    //                 Log::info('Attemp 5');
    //                 // $transaction->update([
    //                 //     'status'  => 'failed',
    //                 //     'message' => 'Payment verification failed after 5 attempts: ' . $e->getMessage(),
    //                 // ]);
    //             }
    //             Log::error('Payment job permanently failed', [
    //                 'transaction_id' => $this->transactionId,
    //                 'error' => $e->getMessage(),
    //         ]);
    //     }
    //     // try {
    //     //     // Call bank verification API
    //     //     $response = PaywayServiceImpl::verifyTransaction($this->transactionId);
    //     //     if (!$response->error) {
    //     //         $newData = [
    //     //             'package_ref' => $this->paymentRef ?? '',
    //     //             'amount' => $response->data['amount'],
    //     //             'transaction_date' => $response->data['transaction_date'],
    //     //             'payment_method' => $response->data['payment_method'],
    //     //             'payer' => $response->data['payer'],
    //     //             'payee' => $response->data['payee'],
    //     //         ];
    //     //         $transaction->update([
    //     //             'status' => PaywayStatus::DONE->value,
    //     //             'details' => DB::raw("details || '" . json_encode($newData) . "'::jsonb")
    //     //         ]);
    //     //         Log::info('attemp');
    //     //         PaywayServiceImpl::sendDriverPaymentTransaction($newData,$this->topic);
    //     //     } else {
    //     //         // If not paid yet, retry after delay
    //     //         if ($this->attempts() < $this->maxRetries) {
    //     //             Log::info('Retry');
    //     //             $queueFCMName = config('queue_job_names.'.config('app.env').'.payment');
    //     //             self::dispatch($this->transactionId, $this->paymentRef,$this->topic,$this->maxRetries)
    //     //                 ->onQueue($queueFCMName)
    //     //                 ->delay(now()->addSeconds(1));
    //     //         } else {
    //     //             // max retries reached
    //     //             Log::info('Max 1');
    //     //             $transaction->update([
    //     //                 'status' => 'failed',
    //     //                 'message' => 'Payment verification failed after multiple attempts',
    //     //             ]);
    //     //         }
    //     //     }
    //     // } catch (\Exception $e) {
    //     //     Log::error($e->getMessage());
    //     //     // retry on exception
    //     //     Log::info('Max 2');
    //     //     if ($this->attempts() < $this->maxRetries) {

    //     //         $queueFCMName = config('queue_job_names.'.config('app.env').'.payment');
    //     //         self::dispatch($this->transactionId, $this->paymentRef,$this->topic,$this->maxRetries)
    //     //                 ->onQueue($queueFCMName)
    //     //                 ->delay(now()->addSeconds(1));
    //     //     }
    //     //     // else {
    //     //     //     $transaction->update([
    //     //     //         'status' => 'failed',
    //     //     //         'message' => 'Payment verification exception: ' . $e->getMessage(),
    //     //     //     ]);
    //     //     // }
    //     }
    // }

}
