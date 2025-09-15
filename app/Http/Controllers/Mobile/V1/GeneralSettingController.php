<?php

namespace App\Http\Controllers\Mobile\V1;

use ApiResponse;
use App\Enums\ImageDirectory;
use App\Enums\PaymentMethod;
use App\Enums\TrackingStatus;
use App\Http\Controllers\Controller;
use App\Jobs\SendNotificationJob;
use App\Models\Delivery;
use App\Models\DeliveryPackage;
use App\Models\Notification;
use App\Models\Package;
use App\Models\PackageAttachment;
use App\Services\CloudMessagingService;
use App\Services\GeneralSettingService;
use App\Services\PickupCenterServiceImpl;
use App\Services\TransferServiceImpl;
use App\Services\UserService;
use App\Services\V1\FleetServiceImpl;
use Cache;
use DataResponse;
use DB;
use Exception;
use Helper;
use Illuminate\Http\Request;
use Log;
use WebSocket\Client;


class GeneralSettingController extends Controller
{
    //
    public function getOptionsDriverFailRemarks(Request $req){
        return ApiResponse::JsonResult(GeneralSettingService::optionsDriverRemarks('failure',$req->isFailWithFee));
    }

    // public function getMerchantFormBooking(){
    //     $user = UserService::getAuthUser('merchant');
    //     $obj = (object)[
    //         'vehicle_types' => GeneralSettingService::optionsVehicleType($user),
    //         'product_types' => GeneralSettingService::optionsProductType($user)
    //     ];
    //     return ApiResponse::JsonResult($obj);
    // }
    public function getMerchantFormBooking(Request $req){
        $user = UserService::getAuthUser('merchant');
        $lang = $req->lang;
        $obj = (object)[
            'vehicle_types' => GeneralSettingService::optionsVehicleType($user,$lang),
            'product_types' => GeneralSettingService::optionsProductType($user),
            'delivery_types' => GeneralSettingService::optionsDeliveryType(),
            'address_info'  => [
                'loc_lat' => $user->info->latitude,
                'loc_lng' => $user->info->longitude,
                'address' => $user->info->address,
                'pin_address' => $user->info->pin_address
            ]
        ];
        return ApiResponse::JsonResult($obj);
    }

    public function getFormOptionsHistory(Request $req){
        $user = UserService::getAuthUser('driver');
        $lang = $req->lang;
        $stCode = $lang != 'en' ? 'ទាំងអស់':'All';
        $bonusRow = collect([['id' => 0, 'name' => $stCode]]);
        $results = GeneralSettingService::optionsTrackingStatus($user,[],[9,10,11,19],'delivery',null,$req->lang);
        // Merge the bonus row with the fetched results
        $results = $bonusRow->merge($results);
        foreach($results as $st){
            if($lang != 'en'){
                $st['name'] = isset(GeneralSettingService::$statusCodeTrans[$st['id']]) ? GeneralSettingService::$statusCodeTrans[$st['id']] : null;
            }
        }
        $obj = (object)[
            'payment_statuses' => GeneralSettingService::paymentStatus($lang),
            'statuses' =>$results
        ];
        return ApiResponse::JsonResult($obj);
    }

    public function getPackageImages(Request $req){
        $id = $req->package_id;
        $user = UserService::getAuthUser();
        $images = PackageAttachment::where('package_id', $id)
        ->where('hidden', 0)
        ->orderBy('created_at', 'desc') // Ensure most recent images are fetched
        ->take(2) // Limit to 2 images
        ->selectRaw("file_name,file_dir, TO_CHAR(created_at, 'YYYY-MM-DD') as date") // Correct usage of DATE()
        ->get();
        // ->toArray();
        // $imageUrls = array_map(fn($img) => Helper::getImageUrl($img, $user->company_id, 'submit_package'), $images);
        $imageUrls = $images->map(fn($img) => Helper::getImageUrl($img->file_name, $user->company_id,$img->file_dir,$img->date))
                    ->toArray();
        return ApiResponse::JsonResult($imageUrls);
    }

    public function getFormBooking(){
        $user = UserService::getAuthUser('driver');
        $obj = (object)[
            'vehicle_types' => GeneralSettingService::optionsVehicleType($user),
            'merchants' => GeneralSettingService::optionsMerchant($user),
            'product_types' => GeneralSettingService::optionsProductType($user),
        ];
        return ApiResponse::JsonResult($obj);
    }

    public function scanPackage(Request $req)
    {
        $user = UserService::getAuthUser('driver');
        $itemRef = $req->item_ref;

        // Get basic package data; avoid loading unnecessary relations yet
        $package = Package::query()
            ->when(is_numeric($itemRef), fn($q) => $q->where(function ($q2) use ($itemRef) {
                $q2->where('qr_code', $itemRef)->orWhere('id', $itemRef);
            }), fn($q) => $q->where('qr_code', $itemRef))
            ->where('is_deleted', 0)
            ->first([
                'id', 'qr_code', 'status_id', 'driver_id', 'merchant_id', 'is_contact',
                'assign_driver_datetime', 'receiver_address', 'receiver_phone', 'receiver_name',
                'product_type', 'cod', 'zone_name', 'zone_code', 'price', 'delivery_fee','delivery_remarks',
                'driver_total as total', 'taxi_fee', 'additional_fee', 'extra_charge', 'payer','assigned_return_at',
                'price_khr','other_fee'
            ]);

        if (!$package) {
            return ApiResponse::NotFound();
        }

        // Status validation
        $statusMap = [
            TrackingStatus::DELIVERED->value => ['Package is already delivered.', 'កញ្ចប់បានដឹករួចហើយ'],
            TrackingStatus::RETURNED->value => ['Package has returned.', 'កញ្ចប់បានយកត្រឡប់ទៅហាងរួចហើយ'],
            TrackingStatus::FAILED_WITH_FEE->value => ['Package is already failed with fee.', 'កញ្ចប់ធ្លាប់បរាជ័យគិតសេវា'],
        ];

        if (isset($statusMap[$package->status_id])) {
            [$info, $khInfo] = $statusMap[$package->status_id];
            return ApiResponse::Duplicated(__('messages.info', compact('info', 'khInfo')));
        }

        if ($package->status_id === TrackingStatus::PENDING_PICK->value) {
            return ApiResponse::ValidateFail(__('messages.info', [
                'info' => 'Please ensure package has arrived warehouse',
                'khInfo' => 'កញ្ចប់ត្រូវបញ្ចាក់ថាមកដល់​ឃ្លាំងទើបអាចដឹកបាន',
            ]));
        }

        $diffDriver = $package->driver_id && $user->id !== $package->driver_id;
        $isOnDelivery = $package->status_id === TrackingStatus::ON_DELIVERY->value;
        $isReturning = $package->status_id === TrackingStatus::RETURNING->value;

        $info = null;

        if ((!$diffDriver && $isOnDelivery) || $isReturning) {
            $xRate = GeneralSettingService::$feeXrate;
            $package->load(['status:id,name', 'merchant:id,username,phone']);
            $telegram = Helper::generateTelegramLink($package->merchant->phone);
            $priceKhr = $package->price_khr;
            $fees = $package->delivery_fee + $package->other_fee;
            $totalKhr = $priceKhr > 0 ? (string)number_format($priceKhr + $fees * $xRate,2,'.',''):"0";
            $info = [
                'id' => $package->id,
                'qr_code' => $package->qr_code,
                'receiver_address' => $package->receiver_address,
                'receiver_phone' => $package->receiver_phone,
                'receiver_name' => $package->receiver_name,
                'assign_driver_datetime' => $package->assign_driver_datetime,
                'return_date' => $package->assigned_return_at ? Helper::formatCustomDateTime($package->assigned_return_at,'d M,Y') : null,
                'return_time' => $package->assigned_return_at ? Helper::formatCustomDateTime($package->assigned_return_at,'h:i A') : null,
                'product_type' => $package->product_type,
                'cod' => $package->cod ? 'Yes' : 'No',
                'zone_name' => $package->zone_name,
                'zone_code' => $package->zone_code,
                'price' => $package->price,
                'delivery_fee' => $package->delivery_fee,
                'total' => $package->total,
                'total_khr' => $totalKhr,
                'taxi_fee' => $package->taxi_fee,
                'additional_fee' => $package->additional_fee,
                'extra_charge' => $package->extra_charge,
                'payer' => $package->payer,
                'delivery_remarks' => $package->delivery_remarks ?? null,
                'merchant_name' => $package->merchant?->username,
                'status_code' => $package->status?->name,
                'telegram_links' => $telegram,
                'fees_usd' => (string)$fees,
                'fees_khr' => (string)($fees * $xRate),
                'fee' => PickupCenterServiceImpl::getFees(
                    $package->payer,
                    $package->delivery_fee,
                    $package->extra_charge,
                    $package->taxi_fee
                ),
            ];
        }

        return ApiResponse::JsonResult([
            'is_contact' => $package->is_contact,
            'diff_driver' => $diffDriver,
            'is_delivery' => $isOnDelivery,
            'is_returning' => $isReturning,
            'info' => $info,
        ]);
    }

    // public function scanPackage(Request $req){
    //     $user = UserService::getAuthUser('driver');
    //     $item_ref = $req->item_ref;
    //     $package = Package::where('qr_code',$item_ref)->where('is_deleted',0)->selectRaw('status_id,driver_id,merchant_id,is_contact')->first();
    //     if(!$package && is_numeric($item_ref)) $package = Package::where('is_deleted',0)->find($item_ref);
    //     if(!$package) return ApiResponse::NotFound();
    //     if($package->status_id == 9) return ApiResponse::Duplicated(__('messages.info',[
    //         'info' => 'Package is already delivered.',
    //         'khInfo' => 'កញ្ចប់បានដឹករួចហើយ'
    //     ]));
    //     if($package->status_id == 11) return ApiResponse::Duplicated(__('messages.info',[
    //         'info' => 'Package has returned.',
    //         'khInfo' => 'កញ្ចប់បានយកត្រឡប់ទៅហាងរួចហើយ'
    //     ]));
    //     if($package->status_id == 19) return ApiResponse::Duplicated(__('messages.info',[
    //         'info' => 'Package is already failed with fee.',
    //         'khInfo' => 'កញ្ចប់ធ្លាប់បរាជ័យគិតសេវា'
    //     ]));
    //     if($package->status_id == 7) {
    //         return ApiResponse::ValidateFail(__('messages.info',[
    //             'info' => 'Please ensure package has arrived warehouse',
    //             'khInfo' => 'កញ្ចប់ត្រូវបញ្ចាក់ថាមកដល់​ឃ្លាំងទើបអាចដឹកបាន'
    //         ]));
    //     }
    //     $diffDriver = $package->driver_id ? ($user->id != $package->driver_id) : false;
    //     $isOnDelivery = $package->status_id == 6;
    //     $data = null;
    //     if(!$diffDriver && $isOnDelivery)
    //         $data = Package::where('qr_code',$item_ref)
    //         ->with(['status:id,name','merchant:id,username'])
    //         ->selectRaw('receiver_address,merchant_id,id,qr_code,status_id,assign_driver_datetime,receiver_phone,receiver_name,product_type,cod,zone_name,zone_code,price,delivery_fee,driver_total as total,taxi_fee,additional_fee,extra_charge,payer')
    //         // ->selectRaw('p.delivered_datetime,p.failed_datetime,p.assign_driver_datetime,p.merchant_id,p.qr_code,p.price,p.cod,p.receiver_name,p.receiver_phone,p.zone_code,p.zone_name,ts.name as status_code
    //         // ,d.username as driver_name,d.phone as driver_phone,m.username as merchant_name,m.phone as merchant_phone,p.id as package_id,dp.delivery_id,p.zone_code,p.zone_name,p.delivery_fee as base_fee,p.driver_total,p.driver_total as delivery_fee,p.taxi_fee,p.product_type,dp.status_id')
    //         ->first();
    //         if($data){
    //             $data->merchant_name = $data->merchant?->username;
    //             $data->cod = $data->cod ? 'Yes' : 'No';
    //             $data->status_code = $data->status->name;
    //             $data->fee = PickupCenterService::getFees($data->payer,$data->delivery_fee,$data->extra_charge,$data->taxi_fee);
    //             unset($data->status,$data->merchant);
    //         }
    //     return ApiResponse::JsonResult([
    //         'is_contact' => $package->is_contact,
    //         'diff_driver' => $diffDriver,
    //         'is_delivery' => $isOnDelivery,
    //         'info' => $data
    //     ]);
    // }

    public static function markReadNotification(Request $req,$user){
        $id = $req->id ?? null;
        $msg = 'Mark read all';
        $notification = Notification::where('user_id',$user->id)->where('is_read',0)->selectRaw('id,is_read,title,body');
        if($id) {
            $msg = 'Read';
            $notification->find($id);
            if(!$notification) return DataResponse::Duplicated('Already marked');
            $notification->update([
                'is_read' => true,
                'read_datetime' => now()
            ]);
        }else{
            $notification->update([
                'is_read' => true,
                'read_datetime' => now()
            ]);
        }

        return DataResponse::JsonResult(null,$msg);
    }

    public function scanPackageChooseAction(Request $req){
        $user = UserService::getAuthUser('driver');
        $item_ref = $req->item_ref;
        $changeDriver = $req->change_driver;
        $markContact = $req->mark_contact ?? 0;
        $confirmDelivery = $req->confirm_delivery ?? 0;
        $isReturn = $req->returned == 1 ? true : false;
        $returnImg = $req->image ?? null;
        // $cms = new CloudMessagingService();
        $package = Package::where('is_deleted', false)
        ->with('driver')
        ->when(is_int($item_ref), function ($query) use ($item_ref) {
            return $query->where('id', $item_ref);
        }, function ($query) use ($item_ref) {
            return $query->where('qr_code', $item_ref);
        })
        ->first();
        // return ApiResponse::NotFound($package, __('messages.info', [
        //     'info' => 'Package not found',
        //     'khInfo' => 'រកមិនឃើញកញ្ចប់'
        // ]));

        if(!$package) return ApiResponse::NotFound(__('messages.info',[
            'info' => 'Package not found',
            'khInfo' => 'រកមិនឃើញកញ្ចប់'
        ]));
        if($package->outstanding == 1) return ApiResponse::ValidateFail(__('messages.info',[
            'info' => 'Please ensure that the package has marked as arrived before scan',
            'khInfo' => 'កញ្ចប់ត្រូវតែបញ្ចាក់ថាមកដល់ឃ្លាំងមុនចេញដឹក'
        ]));

        if($package->status_id == TrackingStatus::DELIVERED->value) return ApiResponse::Duplicated(__('messages.info',[
            'info' => 'Package is already delivered.',
            'khInfo' => 'កញ្ចប់បានដឹករួចហើយ'
        ]));

        if($package->status_id == TrackingStatus::FAILED_WITH_FEE->value) return ApiResponse::Duplicated(__('messages.info',[
            'info' => 'Package is already failed with fee.',
            'khInfo' => 'កញ្ចប់ធ្លាប់បរាជ័យគិតសេវា'
        ]));

        if($package->status_id == TrackingStatus::RETURNED->value)  return ApiResponse::Duplicated(__('messages.info',[
            'info' => 'Package has been returned.',
            'khInfo' => 'កញ្ចប់បានយកត្រឡប់ទៅហាងរួចហើយ'
        ]));
        // $statusId = $package->status_id;
        $driver = $package->driver;
        $updateArr = [];
        if($confirmDelivery){
            if($package->status_id == TrackingStatus::RETURNED->value){
                return ApiResponse::ValidateFail(__('messages.info',[
                    'info' => 'Package is already returned',
                    'khInfo' => 'កញ្ចប់បានយកត្រឡប់ទៅហាងរួចហើយ'
                ]));
            }

            if($package->status_id == TrackingStatus::ON_DELIVERY->value) return ApiResponse::Duplicated(__('messages.info',[
                'info' => 'Package is already on delivery',
                'khInfo' => 'កញ្ចប់បានដឹករួចហើយ'
            ]));
            if($changeDriver) return ApiResponse::ValidateFail(__('messages.info',[
                'info' => 'You cannot change the driver and confirm delivery the same time!',
                'khInfo' => 'អ្នកមិនអាចផ្លាស់ប្តូរនៅពេលដែលអ្នកបញ្ជាក់ថាកញ្ចប់បានដឹកទេ!'
            ]));
            $updateArr['status_id'] = 6;
            $updateArr['driver_id'] = $user->id;
            $updateArr['assign_driver_datetime'] = now();
            $notes = $package->tracking_notes."|[$user->id]Driver ($user->username) scan on delivery (".Helper::getDateTime().")";
            // $notifRequpdateArr['tracking_notes'] = $notes;
            $pckTl = new PickupCenterServiceImpl();
            // DB::beginTransaction();
            // try{
                $trip = $pckTl->createOrUpdateTrip($user->id,$package->id,$package->drivervehicle_type,$user,$notes,6,'assign',$package);
                if($trip->error) return ApiResponse::flex($trip);
                // DB::commit();
            // }catch(Exception $e){
            //     DB::rollBack();
            // }
            Helper::clearCacheByTags([
                'package_trail'
            ]);
        } else $confirmDelivery = ($package->status_id == 6);
        if($markContact && !$confirmDelivery) return ApiResponse::ValidateFail(__('messages.info',[
            'info' => 'You cannot mark contact on package which is not on delivery',
            'khInfo' => 'អ្នកមិនអាចបញ្ជាក់ថាមានទំនាក់ទំនងនៅលើកញ្ចប់ដែលមិនបានដឹកទេ'
        ])); else {
            $updateArr['is_contact'] = $markContact;
            // $topics = GeneralSettingService::getGeneralTopics($user->company_id,'merchant',$package->merchant_id);
            // $notifReq = new Request([
            //     'topic' => $topics->private,
            //     'title' => 'Contact',
            //     'body' => 'Driver has contacted your customer ('.$package->receiver_phone.')',
            // ]);

            // $cms->sendNotificationByTopic($notifReq,$user);
        }
        if($isReturn){
            if($package->status_id !== TrackingStatus::RETURNING->value) {
                return ApiResponse::ValidateFail(__('messages.info',[
                    'info' => 'Package is not on returning status',
                    'khInfo' => 'កញ្ចប់មិននៅលើស្ថានភាពត្រឡប់ទេ'
                ]));
            }
            $updateArr['status_id'] = TrackingStatus::RETURNED->value;
            $updateArr['returned_datetime'] = now();
            $updateArr['tracking_notes'] = $package->tracking_notes."|[$user->id]Driver ($user->username) scan returning (".Helper::getDateTime().")";
            if($returnImg){
                $maxSize = Helper::validTotalImageSize([$returnImg]);
                if($maxSize->error) return ApiResponse::ValidateFail($maxSize->message);
                $img = Helper::saveImageFileOrBase64($returnImg,$user->company_id,ImageDirectory::RETURNED_IMAGE->value,date('Y-m-d'));
                $returnImg = $img->filename;
            }
        }

        try{
            if($changeDriver){
                if($user->id == $package->driver_id) return ApiResponse::Duplicated(__('messages.info',[
                    'info' => 'It seems like you tried to confirm delivery package again'
                    // 'info' => 'This package is already marked as out for delivery. Please check the delivery status before proceeding.'
                ]));
                $requester = $user->info->phone."($user->username)";
                $topics = GeneralSettingService::getGeneralTopics($user->company_id,'driver',$package->driver_id);
                $ttl = 300;
                Cache::put($topics->private,(object)[
                    'requester' => $requester,
                    'requester_id' => $user->id,
                    'created_at' => now(),
                ],now()->addSeconds($ttl));
                Log::info("Cache key: ".$topics->private);
                $notifReq = new Request([
                    'topic' => $topics->private,
                    'title' => 'Change Driver',
                    'body' => "$requester request change package ",
                    'data' => [
                        'action' => 'change-driver',
                        'time_to_live' => now()->addSeconds(60),
                        'requester' => $requester,
                        'barcode' => $item_ref,
                        "en_message" => "$requester request change package ",//$requester." request swap the package",
                        "km_message" => $requester." ស្នើរសុំកញ្ចប់"
                    ]
                ]);
                // Log::info($notifReq);
                // var_dump($requester,$topics->private);
                // $cms->sendNotificationByTopic($notifReq,$user);
                $queueFCMName = config('queue_job_names.'.config('app.env').'.notification');
                SendNotificationJob::dispatch($notifReq, $user)->onQueue($queueFCMName);
                $driverName = $driver?->username;
                $updateArr['tracking_notes'] = $package->tracking_notes."|[$user->id]Driver ($user->username) ask [$package->driver_id]Driver $driverName to change driver";
            }
            // if(empty($updateArr)) return ApiResponse::JsonResult(null,__('messages.updated'));
            DB::beginTransaction();
            if(!$isReturn){
                $trxSImpl = new TransferServiceImpl();
                $rct = $trxSImpl->driverScanReceive($user,$package->id);
                if($rct->error) return $rct;
                else{
                    $client = new Client(config('app.cl_socket'),[
                        'headers' => [
                            'Origin' => 'https://dev.ngexpresscambodia.com'
                        ]
                    ]);
                    $message = json_encode([
                        'topic' => 'ng_express',
                        'type' => 'receive',
                        'message' => $package->id,
                    ]);

                    $client->send($message);

                    $client->close();
                }
            }
            $package->update($updateArr);
            PackageAttachment::insert([
                'package_id' => $package->id,
                'file_dir' => $isReturn ? ImageDirectory::RETURNED_IMAGE->value:ImageDirectory::SUBMIT_PACKAGE->value,
                'file_name' => $returnImg,
                'created_at' => now(),
                'updated_at' => now()
            ]);
            DB::commit();
            return ApiResponse::JsonResult(null,__('messages.updated'));
        }catch(Exception $e){
            DB::rollBack();
            Log::error($e->getMessage());
            Log::error($e->getTraceAsString());
            Helper::deleteImageFile($returnImg,$user->company_id,ImageDirectory::ORDER_IMAGE->value,date('Y-m-d'));
            return ApiResponse::Error(__('messages.error'));
        }

    }

    public function confirmOrCancelSwapPackage(Request $req){
        $user = UserService::getAuthUser('driver');
        $item_ref = $req->item_ref;
        $confirm = $req->confirm;
        $package = Package::where('qr_code',$item_ref)->where('is_deleted',0)->with('driver')->first();
        if(!$package) $package = Package::where('is_deleted',0)->find($item_ref);
        if(!$package) return ApiResponse::NotFound();
        if($package->status_id == 9) return ApiResponse::Duplicated(__('messages.arrived',[
            'info' => 'Package'
        ]));

        $selfTopic = GeneralSettingService::getGeneralTopics($user->company_id,'driver',$user->id)->private;
        $cache = Cache::get($selfTopic);
        if(!$cache) return ApiResponse::NotFound("Request not found or has expired");
        $requester = $cache?->requester;
        // return $cache;
        $requester_id = $cache?->requester_id;
        Log::info("Cache data: ".json_encode($cache));
        Log::info("Cache key: ".$selfTopic);
        Cache::forget($selfTopic);

        if($requester_id == $package->driver_id) return ApiResponse::Duplicated(__('messages.info',[
            'info' => 'It seems like you try to confirm self request'
        ]));
        $requesterTopic = GeneralSettingService::getGeneralTopics($user->company_id,'driver',$requester_id);
        // $cms = new CloudMessagingService();
        $notifTitle = 'Confirm';
        $notifBody = $user->username.' has confirmed your request';
        if(!$confirm){
            $notifTitle = 'Cancelled';
            $notifBody = 'Your request has been denied';
        }else{
            // $today = now();
            // return $requester_id;
            $fleet = new FleetServiceImpl();
            $fleetArr = new Request([
                'packages' => [
                    [
                        'package_id' => $package->id
                    ]
                ],
                'depart_datetime' => now(),
                'driver_id' => $requester_id
            ]);
            // return $requester_id;
            // DB::beginTransaction();
            // try{
                $createOrUpdate = $fleet->createOrUpdateTripService($fleetArr,$user,[6]);
                if($createOrUpdate->error) return ApiResponse::flex($createOrUpdate);
            //     DB::commit();
            // }catch(Exception $e){
            //     DB::rollBack();
            // }
            $selfTrip = Delivery::where('driver_id',$package->driver_id)
            ->where('is_deleted',0)->where('finished',0)
            ->selectRaw('package_count,id')->orderByDesc('id')->first();
            //** remove self pacakge */
            $selfTrip->update([
                'package_count' => $selfTrip->package_count - 1
            ]);
            $dP = DeliveryPackage::where('delivery_id',$selfTrip->id)
            ->where('package_id',$package->id)
            ->where('is_deleted',0)
            ->where('delay_count',0)
            ->where('has_swap',0)
            ->orderByDesc('id')
            ->first();
            if($dP) {
                $dP->update([
                    'has_swap' => true,
                    'delay_count' => 1,
                    'notes' => DB::raw('notes || \'| confirm to change swap package\'')
                ]);
            }
            $currTrip = Delivery::where('id',$selfTrip->id)->selectRaw('id,package_count,tracking_notes,delivered_count,failed_count,is_deleted,deleted_datetime,deleted_uid,finished,finished_datetime,status_id')->first();
            if($currTrip->package_count == 0) {
                $currTrip->update([
                    'is_deleted' => 1,
                    'deleted_datetime' => now(),
                    'deleted_uid' => $user->id,
                    'tracking_notes' => $currTrip->tracking_notes.'|Trip delete because driver has swapped package to '.$requester.'('.Helper::getDateTime().')'
                ]);
            }else if($currTrip->package_count == ($currTrip->delivered_count + $currTrip->failed_count)){
                $currTrip->update([
                    'finished' => 1,
                    'status_id' => 16,
                    'finished_datetime' => now(),
                ]);
            }
            $onDeliveryCount = $currTrip->package_count - ($currTrip->delivered_count + $currTrip->failed_count);
            if($onDeliveryCount == 0 && $currTrip->package_count > 0) {
                $currTrip->update([
                    'finished' => 1,
                    'is_completed' => 1,
                    'status_id' => 16
                ]);
                // Log::error(json_encode(Delivery::select('status_id','is_completed','finished')->find($trip_id)));
            }

            $package->update([
                'driver_id' => $requester_id,
                'status_id' => 6,
                'tracking_notes' => $package->tracking_notes.'|Package tranferred from ['.$package->driver_id.']'.$package->driver->username.' to ['.$requester_id.']'.$requester
            ]);
        }
        // return DeliveryPackage::where('package_id',29)->where('is_deleted',0)->get();
        $notifReq = new Request([
            'topic' => $requesterTopic->private,
            'title' => $notifTitle,
            'body' => $notifBody,
            'data' => [
                'action' => 'change-driver',
                'sender' => $user->username,
            ]
        ]);

        SendNotificationJob::dispatch($notifReq, $user)->onQueue(config('queue_job_names.'.config('app.env').'.notification'));
        // $cms->sendNotificationByTopic($notifReq,$user);
        return ApiResponse::JsonResult(null,$confirm ? 'Success':'Declined change driver');
    }

    public function getOptionsZone(Request $req){
        $user = UserService::getAuthUser('driver');
        return ApiResponse::JsonResult(GeneralSettingService::optionsZone($user));
    }

    public function getZonePrice(Request $req){
        $user = UserService::getAuthUser('driver');
        $id = $req->zone_id;
        return ApiResponse::JsonResult(GeneralSettingService::priceByZone($id,$user,$req->merchant_id));
    }

    public function getProfileFormOptions(){
        $user = UserService::getAuthUser();
        return ApiResponse::JsonResult([
            'product_types' => GeneralSettingService::optionsProductType($user),
            'cities' => GeneralSettingService::optionsCity($user),
            // 'districts' => GeneralSettingService::optionsDistrict($user,$req)
        ]);
    }

    public function getOptionsDistrict(Request $req){
        $user = UserService::getAuthUser();
        return ApiResponse::JsonResult(GeneralSettingService::optionsDistrict($user,$req->city_id));
    }

    public function getOptionBanks(){
        $user = UserService::getAuthUser();
        return ApiResponse::JsonResult(GeneralSettingService::optionsBank($user));
    }

    public function getOptionsSearchStatus(){
        $user = UserService::getAuthUser();
        return ApiResponse::JsonResult(GeneralSettingService::optionsTrackingStatus($user,[],[6,9,10,11,19]));
    }

    public function getOptionsPaymentMethod(){
        return ApiResponse::JsonResult([
            'methods' => GeneralSettingService::optionsPaymentMethod(),
            'currencies' => GeneralSettingService::optionsCurrency()
        ]);
    }

    public function getFormOptionsTransactionPaymentMethod(){
        return ApiResponse::JsonResult([
            'methods' => PaymentMethod::optionsTransactionMethod(),
            'currencies' => GeneralSettingService::optionsCurrency()
        ]);
    }
}
