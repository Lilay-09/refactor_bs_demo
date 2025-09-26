<?php

namespace App\Services;

use App\Enums\ImageDirectory;
use App\Enums\PaymentMethod;
use App\Enums\PaywayProvider;
use App\Enums\PaywayStatus;
use App\Enums\PaywayType;
use App\Jobs\VerifyBatchPaymentJob;
use App\Jobs\VerifyPaymentJob;
use App\Models\DeliveryPackage;
use App\Models\Package;
use App\Models\PackageAttachment;
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

    private function payloadValidation(Request $req){
        return validator($req->all(),[
            'package_id' => 'required',
            'method' => 'required',
            'delivery_remarks' => 'nullable|string',
            'status_id' => 'required|in:9,19',
            // 'driver_cod_khr' => 'nullable',
            // 'driver_cod_usd' => 'nullable',
            'images' => 'nullable',
            'amount' => 'required|numeric',
            'payer' => 'nullable',
            'currency' => 'required|in:USD,KHR'
        ]);
    }
    public function bankABAKHQRGeneratePayload(object $authUser,Request $req): object{
        $validator = $this->payloadValidation($req);
        if($validator->fails()){
            return DataResponse::ValidateFail($validator->errors()->first());
        }
        $inputs = $validator->validated();
        $images = $inputs['images'] ?? [];
        $payer = $inputs['payer'] ?? null;
        if($payer && $payer == 'sender' && $inputs['status_id'] == 19){
            return DataResponse::ValidateFail('Could not generate KHQR when payer is sender');
        }
        $method = $inputs['method'];
        unset($inputs['images']);
        // if(!$this->validateItem($authUser,$inputs['package_id'])){
        //     return DataResponse::ValidateFail('Wrong data');
        // }
        $tran_id = $this->getTranID();
        $return_deeplink = $this->encodedDeeplinkScheme();
        $amount = $inputs['amount'];
        $currency = $inputs['currency'];

        $pushbackParams = [
            'delivery_remarks' => $inputs['delivery_remarks'] ?? '',
            'status_id' => $inputs['status_id'],
            'package_id' => $inputs['package_id'],
            'amount' => $amount,
            'currency' => $currency,
            'method' => $method,
            'method_type' => PaymentMethod::ABA_KHQR->value ?? 'cod',
            'payer' => $payer
        ];
        $return_params = base64_encode(json_encode($pushbackParams));

        // Log::info(json_encode($inputs));
        try{
            DB::beginTransaction();
            $this->prepareTempData($inputs['package_id'],$tran_id,$images,$authUser);
            $details = json_encode(['items' => [$inputs['package_id']]]);
            $pwlId = $this->paywaylog($tran_id,$authUser,PaywayType::ABA_KHQR->value,$method,json_encode([
                'tran_id' => $tran_id,
                'currency' => $currency,
                'amount' => $amount,
                // 'method' => $method,
                'items' => [$inputs['package_id']]
            ]),null,json_encode($inputs));
            DB::commit();
            $callback_url = $this->prepareEncodeCallbackUrl($authUser,$tran_id,$pwlId,'receiver');
            // $data = [
            //     "req_time" => $req_time,
            //     "merchant_id" => $merchant_id,
            //     "tran_id" => $tran_id,
            //     "first_name" => $first_name,
            //     "last_name" => $last_name,
            //     "email" => $email,
            //     "phone" => $phone,
            //     "amount" => $amount,
            //     "purchase_type" => $purchase_type,
            //     "payment_option" => $payment_option,
            //     "callback_url" => $callback_url,
            //     "return_deeplink" => $return_deeplink,
            //     "currency" => $currency,
            //     "custom_fields" => $custom_fields,
            //     "return_params" => $return_params,
            //     "lifetime" => 6,
            //     "qr_image_template" => $qr_image_template,
            //     "hash" => $this->generateABAHash($api_key,$req_time,$merchant_id,$tran_id,$amount,$currency,$items,$first_name,$last_name,$email,$phone,$purchase_type,$payment_option,$callback_url,$return_deeplink,$custom_fields,$return_params,$payout,$lifetime,$qr_image_template)//"4sdZ+8KXMYx8/N6gmiW9CeYNGioBYks0JTGKo2ixOCGRyv98eYo9wks1By+Cg9Ju3QPqBvR/NsXinCmx+flgJw=="
            // ];
            $data = $this->bankABAKHQRPayloadGenerator($tran_id,$amount,$currency,$callback_url,$return_deeplink,$return_params);
            return DataResponse::JsonResult($data['payload']);
        }catch(Exception $e){
            Log::error($e->getMessage());
            Log::error($e->getTraceAsString());
            DB::rollBack();
            return DataResponse::Error('Failed');
        }

    }

    public function getTranID($count=1):string{
        return config('app.name').time().'c-'.$count;
    }

    private function bankABAKHQRPayloadGenerator(
        string $tranId,
        string $amount,
        string $currency,
        string $callback_url,
        string $return_deeplink,
        string $return_params
    ): array{
        $items = null;//base64_encode(json_encode($data, JSON_UNESCAPED_UNICODE));
        $first_name = null;
        $last_name = null;
        $email = null;
        $phone = null;
        $purchase_type = 'purchase';
        $payment_option = 'abapay_khqr';
        $api_key = config('services.aba.apikey');
        $req_time = date('Ymdhis');
        $merchant_id = config('services.aba.merchantid');
        $custom_fields = null;
        $payout = null;
        $lifetime = '6';
        // $amount = $inputs['amount'];
        // $currency = $inputs['currency'];
        $qr_image_template = 'template4_color';
        // $pushbackParams = [
        //     'delivery_remarks' => $inputs['delivery_remarks'],
        //     'status_id' => $inputs['status_id'],
        //     'package_id' => $inputs['package_id'],
        //     'amount' => $amount,
        //     'currency' => $currency,
        //     'method' => $method,
        //     'method_type' => PaymentMethod::ABA_KHQR->value ?? 'cod',
        //     'payer' => $payer
        // ];
        // $return_params = base64_encode(json_encode($pushbackParams));
        $data = [
            "req_time" => $req_time,
            "merchant_id" => $merchant_id,
            "tran_id" => $tranId,
            "first_name" => $first_name,
            "last_name" => $last_name,
            "email" => $email,
            "phone" => $phone,
            "amount" => $amount,
            "purchase_type" => $purchase_type,
            "payment_option" => $payment_option,
            "callback_url" => $callback_url,
            "return_deeplink" => $return_deeplink,
            "currency" => $currency,
            "custom_fields" => $custom_fields,
            "return_params" => $return_params,
            "lifetime" => 6,
            "qr_image_template" => $qr_image_template,
            "hash" => $this->generateABAHash($api_key,$req_time,$merchant_id,$tranId,$amount,$currency,$items,$first_name,$last_name,$email,$phone,$purchase_type,$payment_option,$callback_url,$return_deeplink,$custom_fields,$return_params,$payout,$lifetime,$qr_image_template)//"4sdZ+8KXMYx8/N6gmiW9CeYNGioBYks0JTGKo2ixOCGRyv98eYo9wks1By+Cg9Ju3QPqBvR/NsXinCmx+flgJw=="
        ];
        return [
            'api_key' => $api_key,
            'tran_id' => $tranId,
            'merchant_id' => $merchant_id,
            'req_time' => $req_time,
            'payload' => $data
        ];
    }

    private function encodedDeeplinkScheme(){
        $links = [
            'android_scheme' => config('app.mobile_deep_link.payway.android'),
            'ios_scheme' => config('app.mobile_deep_link.payway.ios'),
        ];
        return base64_encode(json_encode($links));
    }
    private function validateItem($user,$pkgId):bool{
        $useFK = $user->account_type.'_id';
        return Package::where('is_deleted',false)
        ->where($useFK,$user->id)
        ->where('status_id',6)
        ->where('id',$pkgId)->exists();
    }

    private function prepareTempData($pkgId,$tranId,$photos,$user){
        PackageAttachment::where('package_id',$pkgId)->update([
            'hidden' => 1
        ]);
        Package::where('id',$pkgId)->update(['tran_id' => $tranId]);
        $insertImage = [];
        if(isset($photos[0])) {
            foreach($photos as $p){
                $today = date('Y-m-d');
                $fileName = Helper::saveImageFileOrBase64($p,$user->company_id,ImageDirectory::SUBMIT_PACKAGE->value,$today)->filename;
                if($fileName){
                    $insertImage[] = [
                        'package_id' => $pkgId,
                        'submit_uid' => $user->id,
                        'file_name' => $fileName
                    ];
                }
            }
            if(!empty($insertImage)){
                PackageAttachment::insert($insertImage);
            }
        }
    }

    private function prepareEncodeCallbackUrl($user,$tran_id,$plId,$payerType){
        $query = http_build_query([
            'key' => config('services.payway.key'),
            'sub' => $user->id,
            'sub_type' => $user->account_type,
            'tran_id' => $tran_id,
            'pl_id' => $plId,
            'status' => PaywayStatus::PENDING->value
        ]);

        $key = 'services.payway.'.$payerType.'-callback';
        $fullUrl = config($key) . '?' . $query;
        $encoded = base64_encode($fullUrl);
        return $encoded;
    }

    private function paywayStatuses(string $provider, string $status): bool
    {
        $statuses = match ($provider) {
            PaywayProvider::ABA => [
                '0' => true,
                '1' => false,
            ],
            default => []
        };

        return $statuses[$status] ?? false;
    }

    private function callbackUrl(Request $req){
        $vlid = validator($req->all(),[
            'key' => 'required',
            'sub' => 'required',
            'sub_type' => 'required',
            'tran_id' => 'required',
            'status' => 'nullable',
            'apv' => 'nullable',
            'pl_id' => 'required',
            'return_params' => 'nullable',
        ]);
        if($vlid->fails()) {
            Log::error('204: Not allowed');
            return DataResponse::Forbidden('Not Allowed');
        }
        $validInputs = $vlid->validated();
        if(!$this->paywayKeypair($validInputs['key'])){
            Log::error('209: Not allowed');
            return DataResponse::Forbidden('Not Allowed');
        }
        $returnParams = $validInputs['return_params'] ?? null;
        $decodeReturnParam = $returnParams ? $this->decodeReturnParam($returnParams):null;
        $decodedJson = json_decode($decodeReturnParam, true);
        $decodedJson['tran_id'] = $validInputs['tran_id'];
        return [
            'inputs' => $validInputs,
            'return_params' => $decodedJson
        ];
    }

    public function DriverPayCallbackUrl(Request $req){
        $callback = $this->callbackUrl($req);
        $validInputs = $callback['inputs'];
        $decodedJson = $callback['return_params'];
        $packages = $decodedJson['packages'];
        try{
            DB::beginTransaction();
            $updatePkgs = $this->batchUpdatePackages(
                $packages,
                $validInputs['sub'],
                $decodedJson['method'],
                $decodedJson['method_type'],
                $validInputs['tran_id'],
                $decodedJson['currency'],
                $decodedJson['amount']
            );
            // $updatePkg = $this->updatePackage($decodedJson['package_id'],$validInputs['sub'],$validInputs['sub_type'],$decodedJson);
            if($updatePkgs->error){
                Log::error(json_encode($updatePkgs));
                return $updatePkgs;
            }
            PaywayLog::where('id',$validInputs['pl_id'])->update([
                'apv' => $validInputs['apv'] ?? null,
                // 'status' => PaywayStatus::DONE->value,
            ]);
            $queueFCMName = config('queue_job_names.'.config('app.env').'.payment');
            $topic = "paymentUpdate".$validInputs['sub'];
            // Log::info($topic);
            VerifyBatchPaymentJob::dispatch($validInputs['pl_id'],$topic,5)->onQueue($queueFCMName);
            // $this->deeplinkAfterKHQRScan(3,null);
            DB::commit();
        }catch(Exception $e){
            DB::rollBack();
            Log::error($e->getTraceAsString());
            Log::error($e->getMessage());
        }
        // Token valid, continue handling the request
        return DataResponse::JsonResult(null,false,'Success');
    }

    public function receiverPayCallbackUrl(Request $req){
        $callback = $this->callbackUrl($req);
        $validInputs = $callback['inputs'];
        $decodedJson = $callback['return_params'];
        try{
            DB::beginTransaction();
            $updatePkg = $this->updatePackage($decodedJson['package_id'],$validInputs['sub'],$validInputs['sub_type'],$decodedJson);
            if($updatePkg->error){
                Log::error(json_encode($updatePkg));
                return $updatePkg;
            }
            PaywayLog::where('id',$validInputs['pl_id'])->update([
                'apv' => $validInputs['apv'] ?? null,
                // 'status' => PaywayStatus::DONE->value,
            ]);

            $queueFCMName = config('queue_job_names.'.config('app.env').'.payment');
            $topic = "paymentUpdate".$validInputs['sub'];
            // Log::info($topic);
            // Log::info('queue => '.$queueFCMName);
            VerifyPaymentJob::dispatch($validInputs['pl_id'],$decodedJson['package_id'],$topic,5)->onQueue($queueFCMName);
            // $paymentStatus = $this->checkPaymentStatusForDriver($validInputs['sub'], $decodedJson['package_id']);

            // Store for SSE
            // cache()->put("payment:{$decodedJson['sub']}:{$decodedJson['package_id']}", $paymentStatus, now()->addMinutes(6));

            // $this->deeplinkAfterKHQRScan(3,null);
            DB::commit();
        }catch(Exception $e){
            DB::rollBack();
            Log::error($e->getTraceAsString());
            Log::error($e->getMessage());
        }
        // Token valid, continue handling the request
        return DataResponse::JsonResult(null,false,'Success');
    }

    private function prepareDriverSettlePayment($user,$packageIds,$currency,$amount,$method,$paymentRef){
        $tranx = new TransactionService();
        $makeData = [
            'driver_id' => $user->id,
            'packages' => $packageIds,
            'mark_settle' => "1",
            'method' => $method,
            'currency' => $currency,
            'payment_ref' => $paymentRef,
            'method_type' => PaymentMethod::ABA_KHQR->value ?? 'bank', // Default to bank, can be changed later
        ];
        // Log::error(json_encode($packageIds));
        if($currency == 'KHR'){
            $makeData['bank_amount_kh'] = $amount;
        }else if($currency == 'USD'){
            $makeData['bank_amount'] = $amount;
        }
        // Log::info(json_encode($makeData));
        $settleReq = new Request($makeData);
        return $tranx->receiveDriverSettleAmount($settleReq,$user,'driver');
    }

    private function prepareSettlePayment($user,$packageIds,$currency,$amount,$method,$paymentRef){
        $tranx = new TransactionService();
        $makeData = [
            'driver_id' => $user->id,
            'packages' => $packageIds,
            'mark_settle' => "1",
            'pay_via' => $method,
            'method' => $method,
            'currency' => $currency,
            'payment_ref' => $paymentRef,
            'exchange_rate' => GeneralSettingService::getLatestXRate()->sell_rate,
            'method_type' => PaymentMethod::ABA_KHQR->value ?? 'bank', // Default to bank, can be changed later
        ];
        // Log::error(json_encode($packageIds));
        if($currency == 'KHR'){
            $makeData['bank_amount_kh'] = $amount;
        }else if($currency == 'USD'){
            $makeData['bank_amount'] = $amount;
        }
        $settleReq = new Request($makeData);
        // Log::info(json_encode($makeData));
        return $tranx->receivePaymentService($settleReq,$user,'driver');
    }

    private function decodeReturnParam($params){
        return base64_decode($params);
    }

    private function batchUpdatePackages($packageIds,$driverId,$method,$methodType,$tran_id,$currency,$amount){
        $user = User::where('is_deleted',false)->where('account_type','driver')->find($driverId);
        if(!$user) {
            Log::error("No User");
            return DataResponse::NotFound('No user');
        }
        Package::where('is_deleted',false)
        ->where('driver_id',$user->id)
        ->where('status_id',6)
        ->whereIn('id',$packageIds)
        ->update([
            'method' => $method,
            'method_type' => $methodType
        ]);

        $pmt = $this->prepareDriverSettlePayment($user,$packageIds,$currency,$amount,$method ?? PaymentMethod::ABA->value ?? 'bank',$tran_id);
        Log::info(json_encode($pmt));
        if($pmt->error){
            Log::error(json_encode($pmt));
        }
        return $pmt;
    }

    private function updatePackage($packageId,$userId,$userType,$data){
        $user = User::where('is_deleted',false)->where('account_type',$userType)->find($userId);
        if(!$user) {
            // Log::error("No User");
            return DataResponse::NotFound('No user');
        }
        $useFK = $user->account_type.'_id';
        $pkg = Package::where('is_deleted',false)
        ->where($useFK,$user->id)
        ->where('status_id',6)
        ->where('id',$packageId)->first();
        if(!$pkg) {
            Log::error("276: No package found");
            return DataResponse::NotFound(message: __('messages.not_found'));
        }
        $payer = $data['payer'] ?? $pkg->payer;
        $data['payer'] = $payer;
        $todayDt = Helper::getDateTime();
        $deliveryRemarks = $data['delivery_remarks'];
        $driverName = $user->user_name;
        $statusId = $data['status_id'];
        $currency = $data['currency'];
        $statusCode = $statusId == 9 ? 'Delivered' : ($statusId == 10 ? 'Failed':($statusId == 19 ? 'Failed with fee':''));
        $data['tracking_notes'] = $pkg->tracking_notes."|[$user->id]Driver ($driverName) submit $statusCode ($todayDt)[Remark: $deliveryRemarks]";
        // if($codChange && $package->price != $amount){
        //     $data['tracking_notes'] .= "|[$user->id]Driver ($driverName) change cod $package->price to $amount ($todayDt)";
        // }
        if($statusId == 9) {
            $data['delivered_datetime'] = now();
            $data['delivery_remarks'] = $deliveryRemarks;
        }
        if($statusId == 10) {
            if(!$deliveryRemarks) return DataResponse::ValidateFail(__('messages.info',[
                'Please input remarks'
            ]));
            $data['failed_datetime'] = now();
            $data['failure_notes'] = $deliveryRemarks;
        }
        if($statusId == 19) {
            $data['failed_datetime'] = now();
            $data['failure_notes'] = $deliveryRemarks;
            // $package->price = 0;
        }

        if($currency == 'USD'){
            $data['driver_cod_usd'] = $data['amount'];
        }else if($currency == 'KHR'){
            $data['driver_cod_khr'] = $data['amount'];
        }

        if($payer){
            $calucalteFee = GeneralSettingService::calculatePackageFee($pkg->zone_code,$pkg->price,$pkg->billed_kg,$pkg->actual_kg,$payer,$pkg->cod,$pkg->extra_charge,$user,$pkg->taxi_fee,$pkg->merchant_id,$statusId);
            if($calucalteFee->error) return $calucalteFee;
            $data['merchant_total'] = $calucalteFee->merchant_total;
            $data['driver_total'] = $calucalteFee->driver_total;
        }
        // Log::error(json_encode($data));
        $pkg->update($data);
        // Log::error("Package updated: ".json_encode($data));
        // Log::info("Package updated: ".json_encode($pkg->toArray()));
        $dp = DeliveryPackage::where('package_id',$packageId)->where('driver_id',$user->id)
        ->where('is_deleted',0)
        ->where('has_swap',0)
        ->orderByDesc('id')->where('delay_count',0)->first();
        $dp->update([
            'notes' => $data['tracking_notes'],
            'status_id' => $statusId
        ]);
        GeneralSettingService::updateTripStatus($dp->delivery_id,$user);
        // Log::info("Package updated: ".json_encode($data));
        $pmt = $this->prepareSettlePayment($user,[(int)$packageId],$data['currency'],$data['amount'],$data['method'] ?? PaymentMethod::ABA->value ?? 'bank',$data['tran_id']);
        if($pmt->error){
            Log::error(json_encode($pmt));
        }
        // Log::info('success');
        return DataResponse::JsonResult(null);
    }

    public function paywaylog(string $tranId,object $user,string $type,string $privider,string $details,?string $apv=null,string $payload,?string $status=null,string $notes=''):int{
        return PaywayLog::insertGetId([
            'tran_id' => $tranId,
            'user_id' => $user->id,
            'user_type' => $user->account_type,
            'provider' => $privider,
            'details' => $details,
            'apv' => $apv,
            'status' => $status ?? PaywayStatus::PENDING->value,
            'type' => $type,
            'payload' => $payload
        ]);
    }

    private function generateABAHash(
        $api_key,
        $req_time,
        $merchant_id,
        $tran_id,
        $amount,
        $currency,
        $items,
        $first_name,
        $last_name,
        $email,
        $phone,
        $purchase_type,
        $payment_option,
        $callback_url,
        $return_deeplink,
        $custom_fields,
        $return_params,
        $payout,
        $lifetime ,
        $qr_image_template
        ): string{
        $b4hash = $req_time .
                $merchant_id .
                $tran_id .
                $amount .
                $items .
                $first_name .
                $last_name .
                $email .
                $phone .
                $purchase_type .
                $payment_option .
                $callback_url .
                $return_deeplink .
                $currency .
                $custom_fields .
                $return_params .
                $payout .
                $lifetime .
                $qr_image_template;

        // Encode key and message as UTF-8, then compute HMAC SHA512 and base64
        $hmac = hash_hmac('sha512', $b4hash, $api_key, true);
        $hash = base64_encode($hmac);
        return $hash;
    }


    private function paywayKeypair(string $key):bool{
        return $key === config('services.payway.key');
    }


    public function getPaywayLogs(Request $req,object $authUser):object{
        if($authUser->system_admin == false){
            return DataResponse::JsonResult([]);
        }
        $type = $req->type;
        $qPw = PaywayLog::query()
        ->orderByDesc('id');
        if($type){
            $qPw->where('type',$type);
        }
        $callback = function ($q){
            $q->date = Helper::formatCustomDateTime($q->generated_at);
            // $q->hello = "world";
            return $q;
        };
        return DataResponse::PaginationV1($qPw,$req,'',[],100,$callback);
    }



    public function streamRedirect(Request $req)
    {
        $driverId = $req->query('user');
        $package  = $req->query('package');

        return new StreamedResponse(function () use ($driverId, $package) {
            header('Content-Type: text/event-stream');
            header('Cache-Control: no-cache');
            header('Connection: keep-alive');
            header('Access-Control-Allow-Origin: *');

            $paymentStatus = cache()->get("payment:{$driverId}:{$package}");

            if ($paymentStatus) {
                $payload = [
                    'error'          => $paymentStatus->error,
                    'driver_id'      => $driverId,
                    'status_code'    => $paymentStatus->status_code,
                    'payment_status' => ($paymentStatus->status_code == 200) ? 'paid' : 'pending',
                    'message'        => $paymentStatus->message ?? null,
                    'data'           => $paymentStatus->data ?? null,
                ];
                echo "event: paymentUpdate\n";
                echo "data: " . json_encode($payload) . "\n\n";
                flush();
            } else {
                // nothing yet → client should retry
                echo "event: keepAlive\n";
                echo "data: {}\n\n";
                flush();
            }
        });
    }



    private function checkPaymentStatusForDriver($driverId,$packageId): object{
        $pkg = Package::where('is_deleted',false)
        ->select(['id','tran_id','qr_code'])
        ->where('driver_id',$driverId)->find($packageId);
        if (!$pkg) {
            Log::error("Package not found for driver {$driverId} and package {$packageId}");
            return DataResponse::NotFound('Wrong package or driver'); // Return a 404 response if package not found
        }
        if (!isset($pkg->tran_id) || trim($pkg->tran_id) === '') {
            // Log::info(json_encode($pkg));
            Log::error("Package {$pkg->qr_code} does not have a transaction ID");
            return DataResponse::NotFound('No transaction ID found for this package');
        }

        // if(empty($pkg->tran_id)) {
        //     Log::error("Package {$pkg->qr_code} does not have a transaction ID");
        //     return DataResponse::NotFound('No transaction ID found for this package');
        // }
        $pw = PaywayLog::where('tran_id',$pkg->tran_id)
        ->orderByDesc('id')
        ->first();
        if(!$pw){
            return DataResponse::BadRequest();
        }
        if($pw->status === PaywayStatus::PENDING->value){
            // Log::info(json_encode($pw->details));
            return DataResponse::ValidateFail();
        }
        return DataResponse::JsonResult([
            'tran_id' => $pkg->tran_id,
            'package_ref' => $pkg->qr_code ?? '',
            'amount' => $pw?->details['amount'].' '.$pw?->details['currency'],
            'status' => PaywayStatus::tryFrom($pw->status)->label(),
            'payment_method' => PaymentMethod::ABA_KHQR->label(),
            'payer' => $pw?->details['payer'] ?? '',
            'transaction_date' => $pw?->details['transaction_date'] ?? '',
            'payee' => $pw?->details['payee'] ?? '',
        ]);
    }

    public function generateCheckTransactionPayload(object $authUser, Request $req): object{
        $api_key = config('services.aba.apikey');
        $req_time = now()->format('YmdHis'); // Auto-generate current timestamp
        $merchant_id = config('services.aba.merchantid');
        $tran_id = 'JS1755498158c-1';//$pkg->tran_id; // Use package's transaction ID or a default value
        $client = new Client();
        $headers = [
            'Content-Type' => 'application/json',
        ];
        $b4hash = $req_time . $merchant_id . $tran_id;
        // Generate the HMAC hash using SHA-512 and encode it in Base64
        $hash = base64_encode(hash_hmac('sha512', $b4hash, $api_key, true));

        $body = json_encode([
            'req_time'   => $req_time,
            'merchant_id' => $merchant_id,
            'tran_id'     => $tran_id,
            'hash'        => $hash,
        ]);

        $url = rtrim(config('services.aba.baseURL'), '/') .config('services.aba.checkTransactionDetailsEndpoint'); '/api/payment-gateway/v1/payments/transaction-detail';

        try {
            $request = new \GuzzleHttp\Psr7\Request('POST', $url, $headers, $body);
            $response = $client->send($request);
            $statusCode = $response->getStatusCode();
            $content    = $response->getBody()->getContents();
            // Log::info("ABA API Response", [
            //     'status'  => $statusCode,
            //     'content' => $content
            // ]);
            $contentDecoded = json_decode($content, true);
            if($contentDecoded['status']['code'] !== '00'){
                return DataResponse::Error('Failed');
            }
            return DataResponse::JsonResult([
                'tran_id' => $tran_id,
                'package_ref' => '',
                'amount' => $contentDecoded['data']['payment_amount'].' '.$contentDecoded['data']['payment_currency'],
                'status' => $contentDecoded['data']['transaction_date'],
                'payment_method' => PaymentMethod::ABA_KHQR->label(),
                'payer' => $contentDecoded['data']['payer_account'],
                'payee' => 'NG Account',
            ]);
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            $statusCode = $e->hasResponse()
                ? $e->getResponse()->getStatusCode()
                : null;

            $content = $e->hasResponse()
                ? $e->getResponse()->getBody()->getContents()
                : null;

            Log::error("ABA API Error", [
                'status'  => $statusCode,
                'message' => $e->getMessage(),
                'content' => json_decode($content,true)
            ]);
            return DataResponse::Error(json_decode($content,true)['status']['message']);
        } catch (Exception $e) {
            // For non-HTTP exceptions
            Log::error("ABA API Fatal Error", [
                'message' => $e->getMessage(),
                'trace'   => $e->getTraceAsString()
            ]);
            return DataResponse::Error('Error');
        }
        // return DataResponse::JsonResult([
        //     'req_time' => $req_time,
        //     'merchant_id' => $merchant_id,
        //     'tran_id' => $tran_id,
        //     'hash' => $hash,
        //     'body' => $body
        // ]);
    }

    public function deeplinkAfterKHQRScan($userId,$redirectUrls)
    {
        Cache::put("payment_redirect_for_user_{$userId}", $redirectUrls, 10);

        return response()->json(['message' => 'Redirect triggered']);
    }

    public function bankABAKHQRSettleGeneratePayload(Request $req,object $authUser): object{
        // Log::info($req->all());
        $validator = validator($req->all(),[
            'amount' => 'required',
            'method' => 'required',
            'currency' => 'required|in:KHR,USD',
            'packages' => 'array'
        ]);
        // Log::info($req->all());
        if($validator->fails()){
            return DataResponse::ValidateFail($validator->errors()->first());
        }
        $inputs = $validator->validated();
        $amount = $inputs['amount'];
        $currency = $inputs['currency'];
        $method = $inputs['method'];
        $hasPaidPkg = $this->checkHasPaidPackages($inputs['packages']);
        if ($hasPaidPkg) {
            return DataResponse::Duplicated(__('messages.info', [
                'info'   => 'Found some packages have paid. Please try again.',
                'khInfo' => 'រកឃើញកញ្ចប់ខ្លះត្រូវបានបង់ប្រាក់រួចហើយ។ សូមព្យាយាមម្តងទៀត។'
            ]));
        }
        $pushbackParams = [
            'packages' => $inputs['packages'],
            'amount' => $amount,
            'currency' => $currency,
            'method' => $method,
            'method_type' => PaymentMethod::ABA_KHQR->value ?? 'cod',
        ];
        $return_params = base64_encode(json_encode($pushbackParams));
        $deepLink = ''; //$this->encodedDeeplinkScheme();
        $tranId = $this->getTranID(count($inputs['packages']));
        // $details = json_encode(['items' => $inputs['packages']]);
        $pwlId = $this->paywaylog($tranId,$authUser,PaywayType::ABA_KHQR->value,$method,json_encode([
            'tran_id' => $tranId,
            'currency' => $currency,
            'amount' => $amount,
            // 'method' => $method,
            'items' => $inputs['packages']
        ]),null,json_encode($inputs));
        $callbackUrl = $this->prepareDriverSettleEncodeCallbackUrl($authUser,$tranId,$pwlId);
        $data = $this->bankABAKHQRPayloadGenerator($tranId,$amount,$currency,$callbackUrl,$deepLink,$return_params);
        return DataResponse::JsonResult($data['payload']);
    }

    private function checkHasPaidPackages(array $packageIds): bool
    {
        return Package::whereIn('packages.id', $packageIds)
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
            ->exists(); // ✅ true if at least one paid package found
    }

    private function prepareDriverSettleEncodeCallbackUrl($user,$tran_id,$plId){
        return $this->prepareEncodeCallbackUrl($user,$tran_id,$plId,'driver');
    }

    public static function verifyTransaction($tranId){
        $client = new Client();
        $headers = [
            'Content-Type' => 'application/json',
        ];
        $api_key = config('services.aba.apikey');
        $req_time = now()->format('YmdHis'); // Auto-generate current timestamp
        $merchant_id = config('services.aba.merchantid');
        // $tran_id = $pkg->tran_id; // Use package's transaction ID or a default value

        $b4hash = $req_time . $merchant_id . $tranId;
        // Generate the HMAC hash using SHA-512 and encode it in Base64
        $hash = base64_encode(hash_hmac('sha512', $b4hash, $api_key, true));

        $body = json_encode([
            'req_time'   => $req_time,
            'merchant_id' => $merchant_id,
            'tran_id'     => $tranId,
            'hash'        => $hash,
        ]);
        $url = rtrim(config('services.aba.baseURL'), '/') . config('services.aba.checkTransactionDetailsEndpoint');

        try {
            $request = new \GuzzleHttp\Psr7\Request('POST', $url, $headers, $body);
            $response = $client->send($request);
            $statusCode = $response->getStatusCode();
            $content    = $response->getBody()->getContents();
            // Log::info("ABA API Response", [
            //     'status'  => $statusCode,
            //     'content' => $content
            // ]);
            $contentDecoded = json_decode($content, true);
            if($contentDecoded['status']['code'] !== '00'){
                return DataResponse::Error('Failed');
            }
            // Log::info('payer:'.$contentDecoded['data']['payer_account']);
            $payload = [
                'tran_id' => $tranId,
                // 'package_ref' => $pkg->qr_code ?? '',
                'amount' => $contentDecoded['data']['payment_amount'].' '.$contentDecoded['data']['payment_currency'],
                'status' => $contentDecoded['data']['transaction_date'],
                'payment_method' => PaymentMethod::ABA_KHQR->label(),
                'payer' => $contentDecoded['data']['payer_account'],
                'transaction_date' => $contentDecoded['data']['transaction_date'],
                'payee' => 'NG Account',
            ];
            return DataResponse::JsonResult($payload);
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            $statusCode = $e->hasResponse()
                ? $e->getResponse()->getStatusCode()
                : null;

            $content = $e->hasResponse()
                ? $e->getResponse()->getBody()->getContents()
                : null;

            Log::error("ABA API Error", [
                'status'  => $statusCode,
                'message' => $e->getMessage(),
                'content' => json_decode($content,true)
            ]);
            return DataResponse::Error(json_decode($content,true)['status']['message']);
        } catch (Exception $e) {
            // For non-HTTP exceptions
            Log::error("ABA API Fatal Error", [
                'message' => $e->getMessage(),
                'trace'   => $e->getTraceAsString()
            ]);
            return DataResponse::Error('Error');
        }
    }

    public static function sendDriverPaymentTransaction(array $payload,string $topic,$action='receiverPayViaDriverApp')
    {
        $uri = config('services.socket.chat_service_socket').'?key='.config('services.socket.chat_service_key');
        try {
            $client = new \WebSocket\Client($uri); // WebSocket server
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
            // Log::info("topic => ".$topic);
            // Log::info("action => receiverPayViaDriver");
            $client->close();
        } catch (Exception $e) {
            Log::error("WS failed: " . $e->getMessage());
        }
    }


    public function payout(string $currency,float $totalAmount,array $payoutAccounts,int $count): object{

        $api_key = config('services.aba.apikey');
        $custom_fields = null;
        // $currency = 'USD';
        $merchant_id = config('services.aba.merchantid');
        $endpoint = $this->getPayoutEndpoint();
        $tran_id = $this->getTranID($count);
        // $amount = 1;
        $beneficiaries_info = json_encode(
            $payoutAccounts
            // ['account' => '012538302', 'amount' => 200],
        );
        $path = storage_path('key/aba_rsa_public.pem');

        if (!file_exists($path)) {
            throw new Exception("Public key file not found at: {$path}");
        }

        $publicKey = file_get_contents($path);
        $publicKeyResource = openssl_pkey_get_public($publicKey);

        if ($publicKeyResource === false) {
            throw new Exception('Invalid public key: ' . openssl_error_string());
        }

        $beneficiaries = $this->opensslEncryption($beneficiaries_info, $publicKeyResource);
        // Prepare the data to be hashed
        $b4Hash = $merchant_id . $tran_id . $beneficiaries . $totalAmount . $custom_fields . $currency;
        // Generate the HMAC hash using SHA-512 and encode it in Base64
        $hash = hash_hmac('sha512', $b4Hash, $api_key);
        $client = new Client();
        $headers = [
            'Content-Type' => 'application/json'
        ];

        $body = json_encode([
            'merchant_id' => $merchant_id,
            'tran_id'     => $tran_id,
            'beneficiaries' => $beneficiaries,
            "amount" =>  $totalAmount,
            "currency" => $currency,
            "custom_fields" => null,
            "hash" => $hash
        ]);

        // return DataResponse::JsonResult(null);
        try {
            $request = new \GuzzleHttp\Psr7\Request('POST', $endpoint, $headers, $body);
            $res = $client->sendAsync($request)->wait();

            // $statusCode = $res->getStatusCode();
            $content    = $res->getBody()->getContents();

            $contentDecoded = json_decode($content, true);

            return DataResponse::JsonResult($contentDecoded,false);

        } catch (\GuzzleHttp\Exception\RequestException $e) {
            // Network error, invalid response, etc.
            Log::error("ABA API Request Exception", [
                'message' => $e->getMessage(),
                'req' => $e->getRequest()->getBody(),
                'response' => $e->hasResponse() ? (string) $e->getResponse()->getBody() : null,
            ]);
            $res = json_decode($e->getResponse()->getBody() ,true);
            return DataResponse::BadRequest($res['status']['message']);

        } catch (Exception $e) {
            // Any other PHP error
            Log::error("ABA API General Error", [
                'message' => $e->getMessage(),
            ]);

            return DataResponse::JsonResult(500, ['error' => 'Unexpected error occurred']);
        }
    }


    // public function prePayout(string $currency,float $totalAmount,array $payoutAccounts,int $count): object{

    //     $api_key = config('services.aba.apikey');
    //     $custom_fields = null;
    //     // $currency = 'USD';
    //     $merchant_id = config('services.aba.merchantid');
    //     $endpoint = $this->getPayoutEndpoint();
    //     $tran_id = $this->getTranID($count);
    //     $request_time = date('Ymdhis');
    //     $path = storage_path('key/aba_rsa_public.pem');

    //     if (!file_exists($path)) {
    //         throw new Exception("Public key file not found at: {$path}");
    //     }

    //     $publicKey = file_get_contents($path);
    //     $publicKeyResource = openssl_pkey_get_public($publicKey);

    //     if ($publicKeyResource === false) {
    //         throw new Exception('Invalid public key: ' . openssl_error_string());
    //     }

    //     // Prepare data to be encrypted for complete pre auth with payout
    //     // $data_object = json_encode([
    //     //     'mc_id' => $merchant_id,
    //     //     'tran_id' => $tran_id,
    //     //     'complete_amount' => $complete_amount,
    //     //     'payout' => [
    //     //         [
    //     //             'acc' => $aba_account,
    //     //             'amt' => $amount
    //     //          ], [
    //     //             'acc' => $mid,
    //     //             'amt' => $amount
    //     //         ],
    //     //         .....
    //     //      ]
    //     // ]);

    //     // Maximum length for encryption chunks
    //     $maxlength = 117;
    //     // Initialize output for encrypted data
    //     $encrypted_output = '';
    //     // Encrypt data in chunks
    //     while ($data_object !== '') {
    //         // Extract a substring of the allowed maximum length
    //         $chunk = substr($data_object, 0, $maxlength);
    //         $data_object = substr($data_object, $maxlength);
    //     // Encrypt the chunk using the public key
    //     if (openssl_public_encrypt($chunk, $encrypted_chunk, $publicKeyResource)) {
    //             $encrypted_output .= $encrypted_chunk;
    //         } else {
    //             // Handle encryption failure (optional: log the error or throw an exception)
    //             throw new Exception('Encryption failed for a data chunk.');
    //         }
    //     }
    //     // Encode the concatenated encrypted output in Base64
    //     $merchant_auth = base64_encode($encrypted_output);
    //     // Prepare the data to be hashed
    //     $b4hash = $merchant_auth . $request_time . $merchant_id;
    //     // Generate the HMAC hash using SHA-512 and encode it in Base64
    //     $hash = base64_encode(hash_hmac('sha512', $b4hash, $api_key, true));

    //     $client = new Client();
    //     $headers = [
    //         'Content-Type' => 'application/json'
    //     ];

    //     $body = json_encode([
    //         'request_time' => $merchant_id,
    //         'merchant_id' => $merchant_id,
    //         'merchant_auth' => $merchant_auth,
    //         "hash" => $hash
    //     ]);

    //     // return DataResponse::JsonResult(null);
    //     try {
    //         $request = new \GuzzleHttp\Psr7\Request('POST', $endpoint, $headers, $body);
    //         $res = $client->sendAsync($request)->wait();

    //         $statusCode = $res->getStatusCode();
    //         $content    = $res->getBody()->getContents();

    //         $contentDecoded = json_decode($content, true);

    //         return DataResponse::JsonResult($contentDecoded,false);

    //     } catch (\GuzzleHttp\Exception\RequestException $e) {
    //         // Network error, invalid response, etc.
    //         Log::error("ABA API Request Exception", [
    //             'message' => $e->getMessage(),
    //             'req' => $e->getRequest()->getBody(),
    //             'response' => $e->hasResponse() ? (string) $e->getResponse()->getBody() : null,
    //         ]);
    //         $res = json_decode($e->getResponse()->getBody() ,true);
    //         return DataResponse::BadRequest($res['status']['message']);

    //     } catch (Exception $e) {
    //         // Any other PHP error
    //         Log::error("ABA API General Error", [
    //             'message' => $e->getMessage(),
    //         ]);

    //         return DataResponse::JsonResult(500, ['error' => 'Unexpected error occurred']);
    //     }


    // }


    private function getPayoutEndpoint():string{
        return "https://checkout-sandbox.payway.com.kh/api/payment-gateway/v2/direct-payment/merchant/payout";
    }

    private function opensslEncryption($source, $publicKey)
    {
        //Assumes 1024 bit key and encrypts in chunks.
        $maxlength = 117;
        $output = '';
        while ($source) {
            $input = substr($source, 0, $maxlength);
            $source = substr($source, $maxlength);
            openssl_public_encrypt($input, $encrypted, $publicKey);
            $output .= $encrypted;
        }
        return base64_encode($output);
    }
}
