<?php

namespace App\Services;

use App\Enums\ImageDirectory;
use App\Enums\PaymentMethod;
use App\Enums\PaywayProvider;
use App\Enums\PaywayStatus;
use App\Enums\PaywayType;
use App\Exceptions\ForbiddenExcept;
use App\Jobs\VerifyBatchPaymentJob;
use App\Jobs\VerifyPaymentJob;
use App\Models\DeliveryPackage;
use App\Models\Package;
use App\Models\PackageAttachment;
use App\Models\PaymentTransaction;
use App\Models\PaywayLog;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use DataResponse;
use Illuminate\Support\Facades\DB;
use Exception;
use GuzzleHttp\Client;
use Helper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PaywayServiceImpl implements PaywayService
{
    // ==========================================
    // CONSTANTS
    // ==========================================
    private const MAX_DELIVERY_REMARKS_LENGTH = 50;
    private const KHQR_LIFETIME_HOURS = 6;
    private const TRANSACTION_VERIFICATION_ATTEMPTS = 80;
    private const VERIFICATION_INTERVAL_SECONDS = 3;
    private const RSA_MAX_CHUNK_SIZE = 117;
    private const CACHE_REDIRECT_TTL_MINUTES = 10;

    // ==========================================
    // VALIDATION
    // ==========================================
    
    /**
     * Validate KHQR payload request
     */
    private function payloadValidation(Request $req): \Illuminate\Contracts\Validation\Validator
    {
        return validator($req->all(), [
            'package_id' => 'required|integer',
            'method' => 'required|string',
            'delivery_remarks' => 'nullable|string|max:' . self::MAX_DELIVERY_REMARKS_LENGTH,
            'status_id' => 'required|in:9,10,19',
            'images' => 'nullable|array',
            'amount' => 'required|numeric|min:0.01',
            'payer' => 'nullable|string|in:sender,receiver',
            'currency' => 'required|in:USD,KHR'
        ]);
    }

    /**
     * Validate batch settle request
     */
    private function batchSettleValidation(Request $req): \Illuminate\Contracts\Validation\Validator
    {
        return validator($req->all(), [
            'amount' => 'required|numeric|min:0.01',
            'method' => 'required|string',
            'currency' => 'required|in:KHR,USD',
            'packages' => 'required|array|min:1',
            'packages.*' => 'integer',
            'remarks' => 'nullable|string|max:' . self::MAX_DELIVERY_REMARKS_LENGTH
        ]);
    }

    /**
     * Validate callback data
     */
    private function callbackValidation(array $data): \Illuminate\Contracts\Validation\Validator
    {
        return validator($data, [
            'key' => 'required|string',
            'sub' => 'required|integer',
            'sub_type' => 'required|string',
            'tran_id' => 'required|string',
            'status' => 'nullable|string',
            'apv' => 'nullable|string',
            'pl_id' => 'required|integer',
            'return_params' => 'nullable|string',
            'response' => 'nullable|string',
        ]);
    }

    // ==========================================
    // MAIN PUBLIC METHODS
    // ==========================================

    /**
     * Generate KHQR payload for single package payment
     */
    public function bankABAKHQRGeneratePayload(object $authUser, Request $req): object
    {
        $validator = $this->payloadValidation($req);
        
        if ($validator->fails()) {
            return DataResponse::ValidateFail($validator->errors()->first());
        }

        $inputs = $validator->validated();

        // Business logic validation
        if ($inputs['payer'] === 'sender' && $inputs['status_id'] == 19) {
            return DataResponse::ValidateFail('Could not generate KHQR when payer is sender');
        }

        if (!$this->validatePackageOwnership($authUser, $inputs['package_id'])) {
            return DataResponse::ValidateFail('Wrong data');
        }

        try {
            DB::beginTransaction();

            // Prepare transaction data
            $tranId = $this->generateTransactionId();
            $amount = number_format($inputs['amount'], 2, '.', '');
            $deliveryRemarks = $this->sanitizeDeliveryRemarks($inputs['delivery_remarks'] ?? '');

            // Prepare pushback parameters
            $pushbackParams = $this->buildPushbackParams([
                'status_id' => $inputs['status_id'],
                'package_id' => $inputs['package_id'],
                'amount' => $amount,
                'currency' => $inputs['currency'],
                'method' => $inputs['method'],
                'payer' => $inputs['payer'] ?? null
            ]);

            // Save temporary data
            $this->prepareTempData(
                $inputs['package_id'],
                $deliveryRemarks,
                $tranId,
                $inputs['images'] ?? [],
                $authUser
            );

            // Create payway log
            $pwlId = $this->createPaywayLog($tranId, $authUser, [
                'type' => PaywayType::ABA_KHQR->value,
                'provider' => $inputs['method'],
                'items' => [$inputs['package_id']],
                'currency' => $inputs['currency'],
                'amount' => $amount,
                'payload' => $inputs
            ]);

            // Generate callback URL
            $callbackUrl = $this->generateCallbackUrl($authUser, $tranId, $pwlId, 'receiver');

            // Generate KHQR payload
            $payload = $this->generateKHQRPayload($tranId, $amount, $inputs['currency'], $callbackUrl, $pushbackParams);

            DB::commit();

            // Generate check transaction hash
            $checkTransData = $this->generateCheckTransactionHash($tranId);

            return DataResponse::JsonResult($payload, false, null, [], 200, 'OK', [
                'check_trans' => $checkTransData,
                'push_back' => [
                    'key' => config('services.payway.key'),
                    'sub' => $authUser->id,
                    'sub_type' => $authUser->account_type,
                    'tran_id' => $tranId,
                    'pl_id' => $pwlId,
                    'status' => PaywayStatus::PENDING->value,
                    'return_params' => $pushbackParams['encoded']
                ],
                'payment' => [
                    'method' => PaymentMethod::ABA_KHQR->label(),
                    'payee' => config('app.company_name') . ' Account'
                ]
            ]);

        } catch (Exception $e) {
            DB::rollBack();
            Log::error('KHQR Generation Failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user' => $authUser->id,
                'package' => $inputs['package_id'] ?? null
            ]);
            return DataResponse::Error('Failed to generate KHQR');
        }
    }

    /**
     * Generate KHQR payload for batch settle
     */
    public function bankABAKHQRSettleGeneratePayload(Request $req, object $authUser): object
    {
        $validator = $this->batchSettleValidation($req);

        if ($validator->fails()) {
            return DataResponse::ValidateFail($validator->errors()->first());
        }

        $inputs = $validator->validated();

        // Check for already paid packages
        if ($this->hasAlreadyPaidPackages($inputs['packages'])) {
            return DataResponse::Duplicated(__('messages.info', [
                'info' => 'Found some packages have paid. Please try again.',
                'khInfo' => 'រកឃើញកញ្ចប់ខ្លះត្រូវបានបង់ប្រាក់រួចហើយ។ សូមព្យាយាមម្តងទៀត។'
            ]));
        }

        try {
            DB::beginTransaction();

            $amount = number_format($inputs['amount'], 2, '.', '');
            
            // Create batch settle record
            $batchId = $this->createBatchSettleRecord([
                'actor_id' => $authUser->id,
                'currency' => $inputs['currency'],
                'amount' => $amount
            ], $inputs['packages']);

            // Generate transaction ID
            $tranId = $this->generateTransactionId(count($inputs['packages']));

            // Prepare pushback parameters
            $pushbackParams = $this->buildPushbackParams([
                'packages' => json_encode($batchId),
                'amount' => $amount,
                'currency' => $inputs['currency'],
                'method' => $inputs['method'],
                'remarks' => $inputs['remarks'] ?? '',
                'method_type' => PaymentMethod::ABA_KHQR->value
            ]);

            // Create payway log
            $pwlId = $this->createPaywayLog($tranId, $authUser, [
                'type' => PaywayType::ABA_KHQR->value,
                'provider' => $inputs['method'],
                'items' => $inputs['packages'],
                'currency' => $inputs['currency'],
                'amount' => $amount,
                'payload' => $inputs
            ]);

            // Generate callback URL
            $callbackUrl = $this->generateCallbackUrl($authUser, $tranId, $pwlId, 'driver');

            // Generate KHQR payload
            $payload = $this->generateKHQRPayload($tranId, $amount, $inputs['currency'], $callbackUrl, $pushbackParams);

            DB::commit();

            // Generate check transaction hash
            $checkTransData = $this->generateCheckTransactionHash($tranId);

            return DataResponse::JsonResult($payload, false, null, [], 200, 'OK', [
                'check_trans' => $checkTransData,
                'payment' => [
                    'method' => PaymentMethod::ABA_KHQR->label(),
                    'payee' => config('app.company_name') . ' Account'
                ]
            ]);

        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Batch KHQR Generation Failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user' => $authUser->id
            ]);
            return DataResponse::Error('Failed to generate batch KHQR');
        }
    }

    /**
     * Handle receiver payment callback
     */
    public function receiverPayCallbackUrl(array $data): object
    {
        $callback = $this->processCallbackData($data);
        
        if ($callback instanceof \stdClass && isset($callback->error) && $callback->error) {
            return $callback;
        }

        $validInputs = $callback['inputs'];
        $returnParams = $callback['return_params'];

        try {
            DB::beginTransaction();

            // Update package
            $updateResult = $this->updatePackage(
                $returnParams['package_id'],
                $validInputs['sub'],
                $validInputs['sub_type'],
                $returnParams
            );

            if ($updateResult->error) {
                Log::error('Package update failed in callback', ['result' => $updateResult]);
                return $updateResult;
            }

            // Update payway log
            $this->updatePaywayLog($validInputs['pl_id'], $validInputs['apv'] ?? null);

            // Dispatch verification job
            $this->dispatchPaymentVerificationJob(
                $validInputs['pl_id'],
                $returnParams['package_id'],
                $validInputs['sub'],
                $updateResult->data['pmt_trx']
            );

            DB::commit();

            return DataResponse::JsonResult(null, false, 'Success');

        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Receiver callback failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return DataResponse::Error('Callback processing failed');
        }
    }

    /**
     * Handle driver payment callback
     */
    public function DriverPayCallbackUrl(Request $req): object
    {
        $callback = $this->processCallbackData($req->all());
        
        if ($callback instanceof \stdClass && isset($callback->error) && $callback->error) {
            return $callback;
        }

        $validInputs = $callback['inputs'];
        $returnParams = $callback['return_params'];

        try {
            $batchId = json_decode($returnParams['packages']);
            
            // Get packages from batch
            $packages = DB::table('batch_settle_package_details')
                ->where('batch_id', $batchId)
                ->pluck('package_id')
                ->toArray();

            DB::beginTransaction();

            // Batch update packages
            $updateResult = $this->batchUpdatePackages(
                $packages,
                $validInputs['sub'],
                $returnParams['method'],
                $returnParams['method_type'],
                $validInputs['tran_id'],
                $returnParams['currency'],
                $returnParams['amount'],
                $returnParams['remarks'] ?? ''
            );

            if ($updateResult->error) {
                Log::error('Batch update failed in callback', ['result' => $updateResult]);
                return $updateResult;
            }

            // Update payway log
            $this->updatePaywayLog($validInputs['pl_id'], $validInputs['apv'] ?? null);

            // Dispatch batch verification job
            $this->dispatchBatchVerificationJob(
                $validInputs['pl_id'],
                $validInputs['sub'],
                $updateResult->data['pmt_trx']
            );

            DB::commit();

            return DataResponse::JsonResult(null, false, 'Success');

        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Driver callback failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return DataResponse::Error('Callback processing failed');
        }
    }

    /**
     * Save transaction details after verification
     */
    public function saveTransactionDetails(string $tranId, array $data): object
    {
        $validInputs = [
            'pl_id' => $data['push_back']['pl_id'],
            'sub' => $data['push_back']['sub'],
            'sub_type' => $data['push_back']['sub_type'],
            'apv' => $data['push_back']['apv'] ?? null,
        ];

        $returnParams = $this->decodeReturnParams($data['push_back']['return_params'] ?? '');
        $returnParams['tran_id'] = $tranId;

        try {
            DB::beginTransaction();

            // Update package
            $updateResult = $this->updatePackage(
                $returnParams['package_id'],
                $validInputs['sub'],
                $validInputs['sub_type'],
                $returnParams
            );

            if ($updateResult->error) {
                Log::error('Package update failed in save transaction', ['result' => $updateResult]);
                return $updateResult;
            }

            // Update payway log with transaction details
            DB::table('payway_logs')
                ->where('tran_id', $tranId)
                ->update([
                    'status' => PaywayStatus::DONE->value,
                    'apv' => $validInputs['apv'],
                    'details' => DB::raw("COALESCE(details, '{}'::jsonb) || '" . json_encode($data['meta'], JSON_UNESCAPED_UNICODE) . "'::jsonb"),
                ]);

            // Update payment transaction
            PaymentTransaction::where('id', $updateResult->data['pmt_trx']->id)
                ->update([
                    'payment_ref' => $tranId,
                    'details' => DB::raw("COALESCE(details, '{}'::jsonb) || '" . json_encode($data['meta'], JSON_UNESCAPED_UNICODE) . "'::jsonb"),
                ]);

            DB::commit();

            return DataResponse::JsonResult(null);

        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Save transaction details failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'tran_id' => $tranId
            ]);
            return DataResponse::Error('Failed to save transaction details');
        }
    }

    // ==========================================
    // TRANSACTION VERIFICATION
    // ==========================================

    /**
     * Try to verify transaction with retry logic
     */
    public static function tryVerifyTransaction(string $tranId, bool $rawContent = false): object
    {
        $config = [
            'timeout' => 30,
            'connect_timeout' => 15,
        ];

        $client = new Client($config);
        $url = rtrim(config('services.aba.baseURL'), '/') . config('services.aba.checkTransactionEndpoint');

        for ($attempt = 0; $attempt < self::TRANSACTION_VERIFICATION_ATTEMPTS; $attempt++) {
            try {
                $checkData = self::buildCheckTransactionRequest($tranId);
                
                $response = $client->post($url, [
                    'headers' => ['Content-Type' => 'application/json'],
                    'json' => $checkData
                ]);

                $result = json_decode($response->getBody()->getContents(), true);

                // Success - transaction found
                if (($result['status']['code'] ?? null) === '00') {
                    return DataResponse::JsonResult(null);
                }

            } catch (\GuzzleHttp\Exception\RequestException $e) {
                Log::error('Transaction verification attempt failed', [
                    'attempt' => $attempt + 1,
                    'tran_id' => $tranId,
                    'error' => $e->getMessage()
                ]);
            }

            sleep(self::VERIFICATION_INTERVAL_SECONDS);
        }

        return DataResponse::Error('Timeout waiting for transaction');
    }

    // ==========================================
    // HELPER METHODS
    // ==========================================

    /**
     * Generate unique transaction ID
     */
    private function generateTransactionId(int $count = 1): string
    {
        return config('app.name') . time() . 'c-' . $count;
    }

    /**
     * Sanitize delivery remarks
     */
    private function sanitizeDeliveryRemarks(string $remarks): string
    {
        $remarks = trim($remarks);

        if (mb_strlen($remarks) > self::MAX_DELIVERY_REMARKS_LENGTH) {
            $remarks = mb_substr($remarks, 0, self::MAX_DELIVERY_REMARKS_LENGTH) . '...';
        }

        return $remarks;
    }

    /**
     * Build pushback parameters
     */
    private function buildPushbackParams(array $params): array
    {
        $encoded = base64_encode(json_encode($params));
        
        return [
            'raw' => $params,
            'encoded' => $encoded
        ];
    }

    /**
     * Generate encoded deeplink scheme
     */
    private function encodeDeeplinkScheme(): string
    {
        $links = [
            'android_scheme' => config('app.mobile_deep_link.payway.android'),
            'ios_scheme' => config('app.mobile_deep_link.payway.ios'),
        ];

        return base64_encode(json_encode($links));
    }

    /**
     * Validate package ownership
     */
    private function validatePackageOwnership(object $user, int $packageId): bool
    {
        $foreignKey = $user->account_type . '_id';

        return Package::where('is_deleted', false)
            ->where($foreignKey, $user->id)
            ->where('status_id', 6)
            ->where('id', $packageId)
            ->exists();
    }

    /**
     * Check if packages are already paid
     */
    private function hasAlreadyPaidPackages(array $packageIds): bool
    {
        return Package::whereIn('id', $packageIds)
            ->where(function ($query) {
                $query->whereExists(function ($q) {
                    $q->select(DB::raw(1))
                        ->from('payment_packages')
                        ->whereColumn('payment_packages.package_id', 'packages.id')
                        ->where('payer_type', 'driver')
                        ->where('is_deleted', false);
                })
                ->orWhereExists(function ($q) {
                    $q->select(DB::raw(1))
                        ->from('disbursement_packages')
                        ->whereColumn('disbursement_packages.package_id', 'packages.id')
                        ->where('payee_type', 'driver')
                        ->where('is_deleted', false);
                });
            })
            ->exists();
    }

    /**
     * Prepare temporary package data
     */
    private function prepareTempData(int $packageId, string $deliveryRemarks, string $tranId, array $photos, object $user): void
    {
        // Hide existing attachments
        PackageAttachment::where('package_id', $packageId)
            ->update(['hidden' => 1]);

        // Update package
        Package::where('id', $packageId)
            ->update([
                'tran_id' => $tranId,
                'delivery_remarks' => $deliveryRemarks
            ]);

        // Save new photos
        if (!empty($photos)) {
            $today = date('Y-m-d');
            foreach ($photos as $photo) {
                if(!$photo) continue;
                $fileName = Helper::saveImageFileOrBase64(
                    $photo,
                    $user->company_id,
                    ImageDirectory::SUBMIT_PACKAGE->value,
                    $today
                )->filename;

                if ($fileName) {
                    PackageAttachment::create([
                        'package_id' => $packageId,
                        'submit_uid' => $user->id,
                        'file_name' => $fileName
                    ]);
                }
            }
        }
    }

    /**
     * Create payway log entry
     */
    private function createPaywayLog(string $tranId, object $user, array $data): int
    {
        return PaywayLog::insertGetId([
            'tran_id' => $tranId,
            'user_id' => $user->id,
            'user_type' => $user->account_type,
            'provider' => $data['provider'],
            'details' => json_encode([
                'tran_id' => $tranId,
                'currency' => $data['currency'],
                'amount' => $data['amount'],
                'items' => $data['items']
            ]),
            'generated_at' => now(),
            'apv' => null,
            'status' => PaywayStatus::PENDING->value,
            'type' => $data['type'],
            'payload' => json_encode($data['payload'])
        ]);
    }

    /**
     * Generate callback URL
     */
    private function generateCallbackUrl(object $user, string $tranId, int $plId, string $payerType): string
    {
        $query = http_build_query([
            'key' => config('services.payway.key'),
            'sub' => $user->id,
            'sub_type' => $user->account_type,
            'tran_id' => $tranId,
            'pl_id' => $plId,
            'status' => PaywayStatus::PENDING->value
        ]);

        $configKey = "services.payway.{$payerType}-callback";
        $fullUrl = config($configKey) . '?' . $query;

        return base64_encode($fullUrl);
    }

    /**
     * Generate KHQR payload
     */
    private function generateKHQRPayload(string $tranId, string $amount, string $currency, string $callbackUrl, array $pushbackParams): array
    {
        $apiKey = config('services.aba.apikey');
        $reqTime = date('Ymdhis');
        $merchantId = config('services.aba.merchantid');
        $deeplink = $this->encodeDeeplinkScheme();

        $data = [
            'req_time' => $reqTime,
            'merchant_id' => $merchantId,
            'tran_id' => $tranId,
            'first_name' => null,
            'last_name' => null,
            'email' => null,
            'phone' => null,
            'amount' => $amount,
            'purchase_type' => 'purchase',
            'payment_option' => 'abapay_khqr',
            'callback_url' => $callbackUrl,
            'return_deeplink' => $deeplink,
            'currency' => $currency,
            'custom_fields' => null,
            'return_params' => $pushbackParams['encoded'],
            'lifetime' => self::KHQR_LIFETIME_HOURS,
            'qr_image_template' => 'template3_color',
            'hash' => $this->generateABAHash($apiKey, $reqTime, $merchantId, $tranId, $amount, $currency, $callbackUrl, $deeplink, $pushbackParams['encoded'])
        ];

        return $data;
    }

    /**
     * Generate ABA HMAC hash
     */
    private function generateABAHash(
        string $apiKey,
        string $reqTime,
        string $merchantId,
        string $tranId,
        string $amount,
        string $currency,
        string $callbackUrl,
        string $returnDeeplink,
        string $returnParams
    ): string {
        $preHash = $reqTime .
            $merchantId .
            $tranId .
            $amount .
            null . // items
            null . // first_name
            null . // last_name
            null . // email
            null . // phone
            'purchase' . // purchase_type
            'abapay_khqr' . // payment_option
            $callbackUrl .
            $returnDeeplink .
            $currency .
            null . // custom_fields
            $returnParams .
            null . // payout
            self::KHQR_LIFETIME_HOURS .
            'template3_color'; // qr_image_template

        return base64_encode(hash_hmac('sha512', $preHash, $apiKey, true));
    }

    /**
     * Generate check transaction hash
     */
    private function generateCheckTransactionHash(string $tranId): array
    {
        $apiKey = config('services.aba.apikey');
        $reqTime = date('Ymdhis');
        $merchantId = config('services.aba.merchantid');

        $preHash = $reqTime . $merchantId . $tranId;
        $hash = base64_encode(hash_hmac('sha512', $preHash, $apiKey, true));

        return [
            'tran_id' => $tranId,
            'merchant_id' => $merchantId,
            'req_time' => $reqTime,
            'hash' => $hash
        ];
    }

    /**
     * Build check transaction request
     */
    private static function buildCheckTransactionRequest(string $tranId): array
    {
        $apiKey = config('services.aba.apikey');
        $reqTime = now()->format('YmdHis');
        $merchantId = config('services.aba.merchantid');

        $preHash = $reqTime . $merchantId . $tranId;
        $hash = base64_encode(hash_hmac('sha512', $preHash, $apiKey, true));

        return [
            'req_time' => $reqTime,
            'merchant_id' => $merchantId,
            'tran_id' => $tranId,
            'hash' => $hash,
        ];
    }

    /**
     * Process callback data
     */
    private function processCallbackData(array $data): array|object
    {
        $validator = $this->callbackValidation($data);

        if ($validator->fails()) {
            Log::error('Callback validation failed', ['errors' => $validator->errors()]);
            return DataResponse::Forbidden('Not Allowed');
        }

        $validInputs = $validator->validated();

        // Verify keypair
        if (!$this->verifyPaywayKeypair($validInputs['key'])) {
            Log::error('Invalid payway key');
            return DataResponse::Forbidden('Not Allowed');
        }

        // Parse response if present
        $response = !empty($validInputs['response']) 
            ? json_decode($validInputs['response'], true) 
            : null;

        // Decode return params
        $returnParams = $validInputs['return_params'] ?? $response['return_params'] ?? null;
        $decodedParams = $this->decodeReturnParams($returnParams);
        $decodedParams['tran_id'] = $validInputs['tran_id'];

        $validInputs['apv'] = $validInputs['apv'] ?? $response['apv'] ?? null;

        return [
            'inputs' => $validInputs,
            'return_params' => $decodedParams
        ];
    }

    /**
     * Decode return parameters
     */
    private function decodeReturnParams(?string $params): array
    {
        if (!$params) {
            return [];
        }

        $decoded = base64_decode($params);
        return json_decode($decoded, true) ?? [];
    }

    /**
     * Verify payway keypair
     */
    private function verifyPaywayKeypair(string $key): bool
    {
        return $key === config('services.payway.key');
    }

    /**
     * Update payway log
     */
    private function updatePaywayLog(int $logId, ?string $apv): void
    {
        PaywayLog::where('id', $logId)
            ->update(['apv' => $apv]);
    }

    /**
     * Dispatch payment verification job
     */
    private function dispatchPaymentVerificationJob(int $logId, int $packageId, int $userId, object $paymentTrx): void
    {
        $queueName = config('queue_job_names.' . config('app.env') . '.payment');
        $topic = "paymentUpdate{$userId}";

        VerifyPaymentJob::dispatch($logId, $packageId, $topic, $paymentTrx)
            ->onQueue($queueName);
    }

    /**
     * Dispatch batch verification job
     */
    private function dispatchBatchVerificationJob(int $logId, int $userId, object $paymentTrx): void
    {
        $queueName = config('queue_job_names.' . config('app.env') . '.payment');
        $topic = "paymentUpdate{$userId}";

        VerifyBatchPaymentJob::dispatch($logId, $topic, $paymentTrx)
            ->onQueue($queueName);
    }

    /**
     * Create batch settle record
     */
    private function createBatchSettleRecord(array $data, array $packageIds): int
    {
        // Create batch record
        $batchId = DB::table('batch_settle_packages')->insertGetId($data);

        // Prepare package details
        $details = array_map(function ($packageId) use ($batchId) {
            return [
                'batch_id' => $batchId,
                'package_id' => $packageId,
            ];
        }, $packageIds);

        // Insert package details
        DB::table('batch_settle_package_details')->insert($details);

        return $batchId;
    }

    /**
     * Update single package
     */
    private function updatePackage(int $packageId, int $userId, string $userType, array $data): object
    {
        $user = User::where('is_deleted', false)
            ->where('account_type', $userType)
            ->find($userId);

        if (!$user) {
            return DataResponse::NotFound('No user');
        }

        $foreignKey = $user->account_type . '_id';

        $package = Package::where('is_deleted', false)
            ->where($foreignKey, $user->id)
            ->where('status_id', 6)
            ->where('id', $packageId)
            ->first();

        if (!$package) {
            Log::error('Package not found or already updated', ['package_id' => $packageId]);
            return DataResponse::NotFound(__('messages.not_found'));
        }

        // Process package update logic
        $updateData = $this->buildPackageUpdateData($package, $user, $data);

        $package->update($updateData);

        // Update delivery package
        $this->updateDeliveryPackage($packageId, $user->id, $updateData);

        // Update trip status
        GeneralSettingService::updateTripStatus($package->delivery_id, $user);

        // Prepare settlement
        $paymentResult = $this->prepareSettlement($user, [(int)$packageId], $data);

        return $paymentResult;
    }

    /**
     * Build package update data
     */
    private function buildPackageUpdateData(object $package, object $user, array $data): array
    {
        $statusId = $data['status_id'];
        $currency = $data['currency'];
        $amount = $data['amount'];
        $deliveryRemarks = $data['delivery_remarks'] ?? $package->delivery_remarks;
        $payer = $data['payer'] ?? $package->payer;

        $updateData = [
            'payer' => $payer,
            'status_id' => $statusId,
            'tracking_notes' => $this->buildTrackingNotes($package, $user, $statusId, $deliveryRemarks),
        ];

        // Status-specific updates
        if ($statusId == 9) {
            $updateData['delivered_datetime'] = now();
            $updateData['delivery_remarks'] = $deliveryRemarks;
        } elseif ($statusId == 10 || $statusId == 19) {
            $updateData['failed_datetime'] = now();
            $updateData['failure_notes'] = $deliveryRemarks;
        }

        // Currency-specific COD
        if ($currency == 'USD') {
            $updateData['driver_cod_usd'] = $amount;
            $updateData['original_driver_cod_usd'] = $amount;
        } elseif ($currency == 'KHR') {
            $updateData['driver_cod_khr'] = $amount;
            $updateData['original_driver_cod_khr'] = $amount;
        }

        // Calculate fees
        if ($payer) {
            $feeCalculation = GeneralSettingService::calculatePackageFee(
                $package->zone_code,
                $package->price,
                $package->billed_kg,
                $package->actual_kg,
                $payer,
                $package->cod,
                $package->extra_charge,
                $user,
                $package->taxi_fee,
                $package->merchant_id,
                $statusId
            );

            if (!$feeCalculation->error) {
                $updateData['merchant_total'] = $feeCalculation->merchant_total;
                $updateData['driver_total'] = $feeCalculation->driver_total;
            }
        }

        return $updateData;
    }

    /**
     * Build tracking notes
     */
    private function buildTrackingNotes(object $package, object $user, int $statusId, string $remarks): string
    {
        $statusText = match($statusId) {
            9 => 'Delivered',
            10 => 'Failed',
            19 => 'Failed with fee',
            default => 'Updated'
        };

        $timestamp = Helper::getDateTime();
        $note = "[{$user->id}]Driver ({$user->user_name}) submit {$statusText} ({$timestamp})[Remark: {$remarks}]";

        return $package->tracking_notes . "|" . $note;
    }

    /**
     * Update delivery package
     */
    private function updateDeliveryPackage(int $packageId, int $driverId, array $updateData): void
    {
        DeliveryPackage::where('package_id', $packageId)
            ->where('driver_id', $driverId)
            ->where('is_deleted', 0)
            ->where('has_swap', 0)
            ->where('delay_count', 0)
            ->orderByDesc('id')
            ->first()
            ?->update([
                'notes' => $updateData['tracking_notes'],
                'status_id' => $updateData['status_id'] ?? null
            ]);
    }

    /**
     * Batch update packages
     */
    private function batchUpdatePackages(
        array $packageIds,
        int $driverId,
        string $method,
        string $methodType,
        string $tranId,
        string $currency,
        string $amount,
        string $remarks = ''
    ): object {
        $user = User::where('is_deleted', false)
            ->where('account_type', 'driver')
            ->find($driverId);

        if (!$user) {
            Log::error('Driver not found for batch update', ['driver_id' => $driverId]);
            return DataResponse::NotFound('No user');
        }

        return $this->prepareDriverSettlement($user, $packageIds, $currency, $amount, $method, $tranId, $remarks);
    }

    /**
     * Prepare settlement for receiver
     */
    private function prepareSettlement(object $user, array $packageIds, array $data): object
    {
        $transactionService = new TransactionService();

        $makeData = [
            'driver_id' => $user->id,
            'packages' => $packageIds,
            'mark_settle' => "1",
            'pay_via' => $data['method'],
            'method' => $data['method'],
            'currency' => $data['currency'],
            'payment_ref' => $data['tran_id'],
            'exchange_rate' => GeneralSettingService::getLatestXRate()->sell_rate,
            'method_type' => PaymentMethod::ABA_KHQR->value,
        ];

        if ($data['currency'] == 'KHR') {
            $makeData['bank_amount_kh'] = $data['amount'];
        } elseif ($data['currency'] == 'USD') {
            $makeData['bank_amount'] = $data['amount'];
        }

        $request = new Request($makeData);

        return $transactionService->receivePaymentService($request, $user, 'driver', true);
    }

    /**
     * Prepare settlement for driver
     */
    private function prepareDriverSettlement(
        object $user,
        array $packageIds,
        string $currency,
        string $amount,
        string $method,
        string $paymentRef,
        string $remarks = ''
    ): object {
        $transactionService = new TransactionService();

        $makeData = [
            'driver_id' => $user->id,
            'packages' => $packageIds,
            'mark_settle' => "1",
            'method' => $method,
            'currency' => $currency,
            'payment_ref' => $paymentRef,
            'method_type' => PaymentMethod::ABA_KHQR->value,
            'remarks' => $remarks
        ];

        if ($currency == 'KHR') {
            $makeData['bank_amount_kh'] = $amount;
        } elseif ($currency == 'USD') {
            $makeData['bank_amount'] = $amount;
        }

        $request = new Request($makeData);

        return $transactionService->receiveDriverSettleAmount($request, $user, 'driver');
    }

    /**
     * Get payway logs
     */
    public function getPaywayLogs(Request $req, object $authUser): object
    {
        if ($authUser->system_admin == false) {
            return DataResponse::JsonResult([]);
        }

        $query = PaywayLog::query()->orderByDesc('id');

        if ($req->type) {
            $query->where('type', $req->type);
        }

        $callback = function ($log) {
            $log->date = Helper::dateDMY($log->generated_at);
            return $log;
        };

        return DataResponse::PaginationV1($query, $req->all(), '', [], 100, $callback);
    }

    /**
     * Stream payment status redirect
     */
    public function streamRedirect(Request $req): StreamedResponse
    {
        $driverId = $req->query('user');
        $packageId = $req->query('package');

        return new StreamedResponse(function () use ($driverId, $packageId) {
            header('Content-Type: text/event-stream');
            header('Cache-Control: no-cache');
            header('Connection: keep-alive');
            header('Access-Control-Allow-Origin: *');

            $paymentStatus = cache()->get("payment:{$driverId}:{$packageId}");

            if ($paymentStatus) {
                $payload = [
                    'error' => $paymentStatus->error,
                    'driver_id' => $driverId,
                    'status_code' => $paymentStatus->status_code,
                    'payment_status' => ($paymentStatus->status_code == 200) ? 'paid' : 'pending',
                    'message' => $paymentStatus->message ?? null,
                    'data' => $paymentStatus->data ?? null,
                ];

                echo "event: paymentUpdate\n";
                echo "data: " . json_encode($payload) . "\n\n";
            } else {
                echo "event: keepAlive\n";
                echo "data: {}\n\n";
            }

            flush();
        });
    }

    /**
     * Send driver payment transaction via WebSocket
     */
    public static function sendDriverPaymentTransaction(array $payload, string $topic, string $action = 'receiverPayViaDriverApp'): void
    {
        $uri = config('services.socket.chat_service_socket') . '?key=' . config('services.socket.chat_service_key');

        try {
            $client = new \WebSocket\Client($uri);

            $client->send(json_encode([
                'action' => $action,
                'topic' => $topic
            ]));

            $client->send(json_encode([
                'action' => $action,
                'topic' => $topic,
                'payload' => $payload,
                'error' => false
            ]));

            $client->close();
        } catch (Exception $e) {
            Log::error('WebSocket send failed', [
                'error' => $e->getMessage(),
                'topic' => $topic
            ]);
        }
    }

    /**
     * Trigger deeplink after KHQR scan
     */
    public function deeplinkAfterKHQRScan(int $userId, $redirectUrls): \Illuminate\Http\JsonResponse
    {
        Cache::put("payment_redirect_for_user_{$userId}", $redirectUrls, self::CACHE_REDIRECT_TTL_MINUTES);
        
        return response()->json(['message' => 'Redirect triggered']);
    }

    /**
     * Get transaction details
     */
    public function getTransDetails(string $transId): object
    {
        return DataResponse::JsonResult(null);
    }

    /**
     * Generate check transaction payload (placeholder)
     */
    public function generateCheckTransactionPayload(object $authUser, Request $req): object
    {
        return DataResponse::Error('Error');
    }

    // ==========================================
    // PAYOUT METHODS
    // ==========================================

    /**
     * Process payout to multiple beneficiaries
     * 
     * @param string $currency Currency code (USD or KHR)
     * @param float $totalAmount Total payout amount
     * @param array $payoutAccounts Array of beneficiary accounts [['account' => '012538302', 'amount' => 200], ...]
     * @param int $count Transaction count
     * @return object Response object
     */
    public function payout(string $currency, float $totalAmount, array $payoutAccounts, int $count): object
    {
        $apiKey = config('services.aba.apikey');
        $merchantId = config('services.aba.merchantid');
        $endpoint = $this->getPayoutEndpoint();
        $tranId = $this->generateTransactionId($count);

        try {
            // Load and validate RSA public key
            $publicKey = $this->loadRSAPublicKey();

            // Encrypt beneficiary information
            $beneficiariesJson = json_encode($payoutAccounts);
            $encryptedBeneficiaries = $this->encryptWithRSA($beneficiariesJson, $publicKey);

            // Generate hash
            $preHash = $merchantId . $tranId . $encryptedBeneficiaries . $totalAmount . null . $currency;
            $hash = hash_hmac('sha512', $preHash, $apiKey);

            // Prepare request body
            $requestBody = [
                'merchant_id' => $merchantId,
                'tran_id' => $tranId,
                'beneficiaries' => $encryptedBeneficiaries,
                'amount' => $totalAmount,
                'currency' => $currency,
                'custom_fields' => null,
                'hash' => $hash
            ];

            // Make API request
            $client = new Client([
                'timeout' => 30,
                'connect_timeout' => 15,
            ]);

            $response = $client->post($endpoint, [
                'headers' => ['Content-Type' => 'application/json'],
                'json' => $requestBody
            ]);

            $result = json_decode($response->getBody()->getContents(), true);

            return DataResponse::JsonResult($result, false);

        } catch (\GuzzleHttp\Exception\RequestException $e) {
            $errorResponse = $e->hasResponse() 
                ? json_decode($e->getResponse()->getBody(), true) 
                : null;

            Log::error('Payout request failed', [
                'error' => $e->getMessage(),
                'tran_id' => $tranId,
                'response' => $errorResponse
            ]);

            $errorMessage = $errorResponse['status']['message'] ?? 'Payout request failed';
            return DataResponse::BadRequest($errorMessage);

        } catch (Exception $e) {
            Log::error('Payout general error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'tran_id' => $tranId
            ]);

            return DataResponse::Error('Unexpected error occurred during payout');
        }
    }

    /**
     * Get payout endpoint URL
     */
    private function getPayoutEndpoint(): string
    {
        $endpoint = config('services.aba.payoutURL');
        if (!$endpoint) {
            throw new ForbiddenExcept('Payway is not available. Please contact support.');
        }
        return $endpoint;
    }

    /**
     * Load RSA public key from file
     * 
     * @throws Exception if key file not found or invalid
     */
    private function loadRSAPublicKey()
    {
        $path = storage_path('key/aba_rsa_public.pem');

        if (!file_exists($path)) {
            throw new Exception("Public key file not found at: {$path}");
        }

        $publicKeyContent = file_get_contents($path);
        $publicKeyResource = openssl_pkey_get_public($publicKeyContent);

        if ($publicKeyResource === false) {
            throw new Exception('Invalid public key: ' . openssl_error_string());
        }

        return $publicKeyResource;
    }

    /**
     * Encrypt data with RSA public key in chunks
     * 
     * @param string $data Data to encrypt
     * @param $publicKey RSA public key resource
     * @return string Base64 encoded encrypted data
     * @throws Exception if encryption fails
     */
    private function encryptWithRSA(string $data, $publicKey): string
    {
        $output = '';
        
        while ($data !== '') {
            // Extract chunk of allowed maximum length
            $chunk = substr($data, 0, self::RSA_MAX_CHUNK_SIZE);
            $data = substr($data, self::RSA_MAX_CHUNK_SIZE);

            // Encrypt the chunk
            if (!openssl_public_encrypt($chunk, $encryptedChunk, $publicKey)) {
                throw new Exception('Encryption failed for data chunk: ' . openssl_error_string());
            }

            $output .= $encryptedChunk;
        }

        return base64_encode($output);
    }

    /**
     * Get transaction ID
     */
    public function getTranID(int $count = 1): string
    {
        return $this->generateTransactionId($count);
    }

    /**
     * Create payway log (public wrapper)
     */
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
    ): int {
        return $this->createPaywayLog($tranId, $user, [
            'type' => $type,
            'provider' => $provider,
            'items' => [],
            'currency' => '',
            'amount' => '',
            'payload' => json_decode($payload, true)
        ]);
    }
}
