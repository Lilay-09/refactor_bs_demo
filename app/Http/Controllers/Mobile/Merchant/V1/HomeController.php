<?php

namespace App\Http\Controllers\Mobile\Merchant\V1;

use ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Mobile\V1\GeneralSettingController;
use App\Models\Bank;
use App\Models\Banner;
use App\Models\BrandImage;
use App\Models\FeedBack;
use App\Models\Notification;
use App\Models\Order;
use App\Models\Package;
use App\Models\PackageAttachment;
use App\Models\Promotion;
use App\Models\SocialMedia;
use App\Models\UserBank;
use App\Services\AppSetting;
use App\Services\CompanyProfileService;
use App\Services\GeneralSettingService;
use App\Services\Mobile\ReusableService;
use App\Services\PickupCenterService;
use App\Services\TransactionService;
use App\Services\UserService;
use Cache;
use Helper;
use Illuminate\Http\Request;
use Illuminate\Validation\Validator;

class HomeController extends Controller
{
    //
    protected $reuseableService;
    public function __construct(){
        $this->reuseableService = new ReusableService();
    }
    public function createBooking(Request $req){
        $user = UserService::getAuthUser('merchant');
        $pck = new PickupCenterService();
        $details = $req->details ?? [];
        $req->merge(['merchant_id' => $user->id]);
        if($details){
            $details = Helper::convertJsonTextToJson($details);
            if($details->error) return ApiResponse::ValidateFail($details->message);
            $req->merge(['details' => $details->result]);//$details->result;
        }
        $create = $pck->createOrder($req,$user);
        if($create->error) return ApiResponse::flex($create);
        return ApiResponse::JsonResult(null,__('messages.info',[
            'info' => 'Your order has been submitted'
        ]));
    }

    public function cancelOrder(Request $req){
        $user = UserService::getAuthUser('merchant');
        $id = $req->id;
        $cancelNotes = $req->notes ?? null;
        $order = Order::where('is_deleted',0)->find($id);
        if(!$order) return ApiResponse::NotFound(__('messages.not_found',[
            'info' => 'Order'
        ]));
        $order->update([
            'cancel_uid' => $user->id,
            'cancel_datetime' => now(),
            'cancel_notes' => $cancelNotes,
            'status_id' => 20 //cancel
        ]);
        return ApiResponse::JsonResult(null,__('messages.canceled'));
    }
    public function trackingActivitySummary(Request $req)
    {
        $today = now();
        $dateaAgo = Helper::getDateDaysAgo(0);
        $user = UserService::getAuthUser('merchant');
        // Consolidate counts into a single query for Order and Package models
        $orderCounts = Order::where('merchant_id', $user->id)
        ->where('is_deleted', 0)
        ->selectRaw('
            SUM(CASE WHEN status_id = 1 THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN status_id IN (2, 3, 4) THEN 1 ELSE 0 END) as pick
        ')
        ->first();


    $packageCounts = Package::where('merchant_id', $user->id)
        ->where('is_deleted', 0)
        ->selectRaw('
            SUM(CASE WHEN cod = TRUE THEN price ELSE 0 END) as total_cod,
            SUM(CASE WHEN status_id = 6 THEN 1 ELSE 0 END) as on_delivery,
            SUM(CASE WHEN status_id = 9 AND delivered_datetime BETWEEN ? AND ? THEN 1 ELSE 0 END) as success,
            SUM(CASE WHEN status_id = 11 AND returned_datetime BETWEEN ? AND ? THEN 1 ELSE 0 END) as return,
            SUM(CASE WHEN status_id IN (10, 19) THEN 1 ELSE 0 END) as fail
        ', [$dateaAgo, $today, $dateaAgo, $today])
        ->first();
        //SUM(CASE WHEN status_id IN (10, 19) AND failed_datetime BETWEEN ? AND ? THEN 1 ELSE 0 END) as fail

        // Calculate total counts by summing the values from both queries
        $totalCount = $orderCounts->pending + $orderCounts->pick + $packageCounts->on_delivery +
                    $packageCounts->success + $packageCounts->fail + $packageCounts->return;

        // Build the response object
        $obj = [
            'pending' => $orderCounts->pending ?? 0,
            'pick' => $orderCounts->pick ?? 0,
            'on_delivery' => $packageCounts->on_delivery ?? 0,
            'success' => $packageCounts->success ?? 0,
            'fail' => $packageCounts->fail ?? 0,
            'return' => $packageCounts->return ?? 0,
            'total' => $totalCount ?? 0,
            'total_cod' => Helper::getNumber($packageCounts->total_cod,2),
            'date' => Helper::getDateTime('d-M-Y'),
        ];

        return ApiResponse::JsonResult($obj);
    }

    public function getBankAccount(){
        $user = UserService::getAuthUser('merchant');
        $userBanks = UserBank::where('user_id',$user->id)->selectRaw('id,bank_name,bank_number,account_name,is_primary')->orderByDesc('is_primary')->first();
        return ApiResponse::JsonResult($userBanks);
    }

    // public function getBankAccount(){
    //     $user = UserService::getAuthUser('merchant');
    //     $userBanks = UserBank::where('user_id',$user->id)->selectRaw('id,bank_name,bank_number,account_name,is_primary')->orderByDesc('is_primary')->get();
    //     $displayBanks = $userBanks->toArray();

    //     // Check the number of existing records
    //     if ($userBanks->isEmpty()) {
    //         // No records, add two: one primary and one secondary
    //         $displayBanks[] = [
    //             'bank_name' => '',
    //             'bank_number' => '',
    //             'account_name' => '',
    //             'is_primary' => true, // First record is primary
    //             'skip' => 1
    //         ];
    //         $displayBanks[] = [
    //             'bank_name' => '',
    //             'bank_number' => '',
    //             'account_name' => '',
    //             'is_primary' => false, // Second record is not primary
    //             'skip' => 1
    //         ];
    //     } elseif ($userBanks->count() === 1) {
    //         // One record exists, check its `is_primary` value
    //         $existing = $userBanks->first();
    //         if ($existing->is_primary) {
    //             // If the existing record is primary, add a secondary row
    //             $displayBanks[] = [
    //                 'bank_name' => '',
    //                 'bank_number' => '',
    //                 'account_name' => '',
    //                 'is_primary' => false,
    //                 'skip' => 1
    //             ];
    //         } else {
    //             // If the existing record is not primary, add a primary row first
    //             $displayBanks = array_merge([
    //                 [
    //                     'bank_name' => '',
    //                     'bank_number' => '',
    //                     'account_name' => '',
    //                     'is_primary' => true,
    //                     'skip' => 1
    //                 ]
    //             ], $displayBanks);
    //         }
    //     }
    //     return ApiResponse::JsonResult($displayBanks);
    // }

    private function bankValidator(Request $req):Validator{
        return validator($req->all(), [
            'bank_id' => 'required|int',
            'bank_name' => 'nullable|string:max:50',
            'bank_number' => 'string|max:50',
            'account_name' => 'string|max:50'
        ]);
    }
    public function saveBankAccount(Request $req){
        $user = UserService::getAuthUser('merchant');
        $validator = $this->bankValidator($req);
        if($validator->fails()) return ApiResponse::ValidateFail($validator->errors()->first());
        $userBank = UserBank::where('user_id',$user->id)->first();
        $inputs = $validator->validated();
        $bankInfo = Bank::select('name')->find($inputs['bank_id']);
        if(!$bankInfo) return ApiResponse::ValidateFail('Bank not found');
        $inputs['bank_name'] = $bankInfo->name;
        $inputs['update_uid'] = $user->id;
        $inputs['branch_id'] = $user->branch_id;
        $inputs['company_id'] = $user->company_id;
        if($userBank){
            $userBank->update($inputs);
        }else{
            $inputs['user_id'] = $user->id;
            $inputs['create_uid'] = $user->id;
            Bank::create($inputs);
        }
        return ApiResponse::JsonResult(null,__('messages.saved'));
        // $saveBank = UserService::saveUserBanks($req->bank_info,$user->id,$user);
        // return ApiResponse::flex($saveBank);
    }

    public function deleteBankAccount(Request $req){
        $user = UserService::getAuthUser('merchant');
        $saveBank = UserService::deleteBank($req->id,$user);
        return ApiResponse::flex($saveBank);
    }

    public function getPendingOrders(Request $req){
        // $today = now();
        // $dateaAgo = Helper::getDateDaysAgo(0);
        $lang = $req->lang;
        $user = UserService::getAuthUser('merchant');
        $qO = Order::where('merchant_id',$user->id)
        ->where('is_deleted',0)
        ->with('driver')
        ->selectRaw('id,code,qty,product_type,vehicle_type,order_datetime,status_id')->where('status_id',1);
        // $qO->where(function ($q) use ($dateaAgo, $today) {
        //     $q->whereBetween('order_datetime', [$dateaAgo, $today]);
        // });
        $orders = $qO->orderByDesc('id');
        $callback = function($order) use($lang){
            if($lang == 'km') $order->status_code = 'រង់ចាំ';
            else $order->status_code = 'Pending';
            $order->order_date = Helper::formatCustomDateTime($order->order_datetime,'d-M-Y');
            $order->order_time = Helper::formatCustomDateTime($order->order_datetime,'h:i A');
            $order->driver_name = $order->driver?->user_name;
            return $order;
        };
        return ApiResponse::PaginationV1($orders,$req,'',[],100,$callback);
    }

    public function getPickOrders(Request $req){
        // $today = now();
        // $dateaAgo = Helper::getDateDaysAgo(0);
        $user = UserService::getAuthUser('merchant');
        $lang = $req->lang;
        $qO = Order::where('merchant_id',$user->id)
        ->with(['tracking_status','driver'])
        ->where('is_deleted',0)
        ->selectRaw('id,code,qty,product_type,vehicle_type,order_datetime,status_id,driver_id')->whereIn('status_id',[2,3,4]);
        // $qO->where(function ($q) use ($dateaAgo, $today) {
        //     $q->whereBetween('order_datetime', [$dateaAgo, $today]);
        // });
        $orders = $qO->orderByDesc('id');
        $callback = function($order) use($lang){
            if($lang == 'km') $order->status_code = GeneralSettingService::$statusCodeTrans[$order->status_id];
            else $order->status_code = $order->tracking_status->name;
            $order->driver_phone = $order->driver->phone;
            $order->telegram_url = Helper::generateTelegramLink($order->driver->phone);
            $order->driver_name = $order->driver->user_name;
            // $order->order_datetime = Helper::formatCustomDateTime($order->order_datetime);
            $order->order_date = Helper::formatCustomDateTime($order->order_datetime,'d-M-Y');
            $order->order_time = Helper::formatCustomDateTime($order->order_datetime,'h:i A');
            unset($order->tracking_status,$order->driver);
            return $order;
        };
        return ApiResponse::PaginationV1($orders,$req,'',[],200,$callback);
    }

    public function getOnDeliveryPackages(Request $req){
        // $today = now();
        // $dateaAgo = Helper::getDateDaysAgo(0);
        $lang = $req->lang;
        $user = UserService::getAuthUser('merchant');
        $qP = Package::where('merchant_id', $user->id)
        ->with(['driver:id,user_name,phone','activeDeliveryPackage:package_id,id,delivery_id','activeDeliveryPackage.delivery:id,fleet_tracking_number'])
        ->where('status_id', 6)
        ->where('is_deleted', 0)
        ->selectRaw('id,merchant_id,receiver_phone,receiver_address,taxi_fee,receiver_name,cod,price,delivery_fee,remarks,driver_id,arrive_warehouse_datetime')
        // $qP->whereBetween('arrive_warehouse_datetime',[$dateaAgo,$today]);
        ->orderByDesc('assign_driver_datetime');
        $callback = function ($package) use($lang){
            $package->price = (float) $package->price;
            $package->taxi_fee = (float) $package->taxi_fee;
            $package->cod_fee = $package->cod ? $package->price : 0;
            $package->tracking_number = $package->activeDeliveryPackage->delivery->fleet_tracking_number;
            if($lang == 'km') $package->status_code = GeneralSettingService::$statusCodeTrans[6];
            else $package->status_code = 'On Delivery';
            $package->driver_phone = $package->driver->phone ?? null; // Ensure driver relationship exists
            $package->telegram_url = AppSetting::getTelegramLink('merchant',$package->receiver_phone,$package->driver->phone);
            $package->driver_name = $package->driver->user_name ?? null;
            $package->total = (float) $package->cod_fee;
            $package->delivery_fee = (float) $package->delivery_fee;
            $package->fee = $package->delivery_fee;
            $package->arrive_warehouse_datetime = Helper::formatCustomDateTime($package->arrive_warehouse_datetime);
            $package->arrive_warehouse_date = Helper::formatCustomDateTime($package->arrive_warehouse_datetime,'d-M-Y');
            $package->arrive_warehouse_time = Helper::formatCustomDateTime($package->arrive_warehouse_datetime,'h:i A');
            // Remove the driver relationship if not needed in the response
            unset($package->driver,$package->activeDeliveryPackage);
            return $package;
        };
        return ApiResponse::PaginationV1($qP,$req,'',[],200,$callback);
    }

    public function getTermConditions(Request $req){
        $user = UserService::getAuthUser('merchant');
        return ApiResponse::JsonResult(GeneralSettingService::termAndConditions($user));
    }

    public function getSuccessPackages(Request $req){
        $user = UserService::getAuthUser();
        return ApiResponse::flex($this->reuseableService::getTrackingPackages($req,$user,9));
        // $today = now();
        // $dateaAgo = Helper::getDateDaysAgo(0);
        // $lang = $req->lang;
        // $user = UserService::getAuthUser('merchant');
        // $attachments = PackageAttachment::where('hidden', 0)
        // ->whereBetween('updated_at', [$dateaAgo, $today])
        // ->limit(700)
        // ->pluck('package_id')
        // ->toArray();
        // $attachmentsLookup = array_flip($attachments);
        // $packages = Package::where('merchant_id',$user->id)
        // ->with('driver')
        // ->where('status_id',9)
        // ->where('is_deleted',0)
        // ->whereBetween('delivered_datetime',[$dateaAgo,$today])
        // ->selectRaw('id,merchant_id,receiver_phone,receiver_address,receiver_name,cod,price,delivery_fee,remarks,driver_id,delivered_datetime,arrive_warehouse_datetime');
        // $callback = function($package) use($lang,$attachmentsLookup){
        //     $package->price = (float) $package->price;
        //     $package->cod_fee = $package->cod ? $package->price : 0;
        //     $package->has_img = isset($attachmentsLookup[$package->id]);
        //     if($lang == 'km') $package->status_code = GeneralSettingService::$statusCodeTrans[9];
        //     else $package->status_code = 'Delivered';
        //     $package->driver_phone = $package->driver->phone;
        //     $package->driver_name = $package->driver->user_name;
        //     $package->total = (float)$package->cod_fee;
        //     $package->telegram_url = AppSetting::getTelegramLink('merchant',$package->receiver_phone,$package->driver->phone);
        //     $package->delivery_fee = (float)$package->delivery_fee;
        //     $package->arrive_warehouse_datetime = Helper::formatCustomDateTime($package->arrive_warehouse_datetime);
        //     $package->delivered_datetime = Helper::formatCustomDateTime($package->delivered_datetime);
        //     $package->fee = $package->delivery_fee;
        //     unset($package->driver);
        //     return $package;
        // };
        // return ApiResponse::PaginationV1($packages,$req,'',[],200,$callback);
    }

    public function getFailPackages(Request $req){
        $today = now();
        $dateaAgo = Helper::getDateDaysAgo(0);
        $lang = $req->lang;
        $statusId = $req->status_id;
        $user = UserService::getAuthUser('merchant');
        return ApiResponse::flex($this->reuseableService::getTrackingPackages($req,$user,$statusId));
        //
        // $attachments = PackageAttachment::where('hidden', 0)
        // ->whereBetween('updated_at', [$dateaAgo, $today])
        // ->limit(700)
        // ->pluck('package_id')
        // ->toArray();
        // $attachmentsLookup = array_flip($attachments);
        // $packages = Package::where('merchant_id',$user->id)
        // ->with(['driver','status'])
        // ->where('status_id',$statusId)
        // ->where('is_deleted',0)
        // ->whereBetween('failed_datetime',[$dateaAgo,$today])
        // ->selectRaw('id,merchant_id,arrive_warehouse_datetime,receiver_phone,receiver_address,receiver_name,cod,price,delivery_fee,status_id,remarks,driver_id,failed_datetime');

        // $callback = function($package) use($lang,$attachmentsLookup){
        //     $package->price = (float)$package->price;
        //     $package->cod_fee = $package->cod ? $package->price : 0;
        //     $package->has_img = isset($attachmentsLookup[$package->id]);
        //     if($lang == 'km') $package->status_code = GeneralSettingService::$statusCodeTrans[$package->status_id];
        //     else $package->status_code = $package->status->name;
        //     $package->driver_phone = $package->driver->phone;
        //     $package->driver_name = $package->driver->user_name;
        //     $package->telegram_url = AppSetting::getTelegramLink('merchant',$package->receiver_phone,$package->driver->phone);
        //     $package->total = (float)$package->code_fee;
        //     $package->fee = (float)$package->delivery_fee;
        //     $package->delivery_fee = (float)$package->delivery_fee;
        //     $package->arrive_warehouse_datetime = Helper::formatCustomDateTime($package->arrive_warehouse_datetime);
        //     $package->failed_datetime = Helper::formatCustomDateTime($package->failed_datetime);
        //     unset($package->driver,$package->status);
        //     return $package;
        // };
        // return ApiResponse::PaginationV1($packages,$req,'',[],200,$callback);
    }

    public function getReturnPackages(Request $req){
        // $today = now();
        // $dateaAgo = Helper::getDateDaysAgo(0);
        // $lang = $req->lang;
        $user = UserService::getAuthUser('merchant');
        return ApiResponse::flex($this->reuseableService::getTrackingPackages($req,$user,11));
        // $packages = Package::where('merchant_id',$user->id)
        // ->with(['returnUser:id,phone,user_name','status'])
        // ->where('status_id',11)
        // ->whereBetween('returned_datetime',[$dateaAgo,$today])
        // ->selectRaw('id,merchant_id,arrive_warehouse_datetime,receiver_phone,receiver_address,receiver_name,cod,price,delivery_fee,status_id,remarks,returned_uid,failed_datetime,returned_datetime,updated_at');
        // $callback = function($package) use($lang){
        //     $package->price = (float)$package->price;
        //     $package->cod_fee = $package->cod ? $package->price : 0;
        //     if($lang == 'km') $package->status_code = GeneralSettingService::$statusCodeTrans[$package->status_id];
        //     else $package->status_code = $package->status->name;
        //     $package->driver_phone = $package->returnUser?->phone;
        //     $package->driver_name = $package->returnUser?->user_name;
        //     $package->telegram_url = AppSetting::getTelegramLink('merchant',$package->receiver_phone,$package->driver_phone);
        //     $package->total = (float)$package->code_fee;
        //     $package->delivery_fee = (float)$package->delivery_fee;
        //     $package->fee = $package->delivery_fee;
        //     $returnDate = $package->return_datetime ? $package->return_datetime : $package->updated_at;
            // $package->returned_date = Helper::dateDMY($returnDate);
            // $package->return_time = Helper::formatCustomDateTime($returnDate, 'h:i:s');
        //     unset($package->returnUser,$package->status);
        //     return $package;
        // };
        // return ApiResponse::PaginationV1($packages,$req,'',[],200,$callback);
    }

    public function getPromotions(Request $req){
        $user = UserService::getAuthUser('merchant');
        $today = date('Y-m-d');
        $promotions = Promotion::where('is_deleted',0)->selectRaw('id,title,description,photo_file_name,start_date,end_date')
        ->where('channel','merchant')
        ->whereRaw('DATE(start_date) >= ? AND DATE(end_date) <= ?',[$today,$today])
        ->orWhereDate('start_date','>=',$today)
        ->orderByDesc('start_date');
        $callback = function($promotion) use($user){
            $xDays = Helper::getAnalyzDiffDate($promotion->start_date,$promotion->end_date);
            $promotion->expires_at = $xDays;
            $promotion->time_ago = Helper::timeAgo($promotion->start_date,false);
            $promotion->image_url = Helper::getImageUrl($promotion->photo_file_name,$user->company_id,'promotion');
            return $promotion;
        };
        return ApiResponse::PaginationV1($promotions,$req,'',[],100,$callback);
    }

    public function getOptionsZone(Request $req){
        $user = UserService::getAuthUser('merchant');
        return ApiResponse::JsonResult(GeneralSettingService::optionsZone($user));
    }

    public function getZonePrice(Request $req){
        $user = UserService::getAuthUser('merchant');
        $id = $req->zone_id;
        return ApiResponse::JsonResult(GeneralSettingService::priceByZone($id,$user,$user->id));
    }

    public function getHomeScreen(Request $req){
        $user = UserService::getAuthUser('merchant');
        $bannerImages = BrandImage::where('is_deleted',0)
        ->where('channel','merchant')->pluck('photo_file_name')
        ->map(fn($img) => Helper::getImageUrl($img, $user->company_id, 'brand_image'))
        ->toArray();
        $obj = [
            'banners' => $bannerImages,
            'daily_summaries' => $this->daily_summaries($req,$user),
        ];
        return ApiResponse::JsonResult($obj);
    }

    private function daily_summaries(Request $req,$user){
        $today = now();
        $dateaAgo = Helper::getDateDaysAgo(0);
        $successCount = Package::where('merchant_id',$user->id)->where('is_deleted',0)
        ->where('status_id',9)
        ->whereBetween('delivered_datetime',[$dateaAgo,$today])->count();
        $failCount = Package::where('merchant_id',$user->id)
        ->where('is_deleted',0)->whereIn('status_id',[10,19])
        ->whereBetween('failed_datetime',[$dateaAgo,$today])->count();
        $returnCount = Package::where('merchant_id',$user->id)->where('is_deleted',0)
        ->whereIn('status_id',[11])
        ->where(function ($query) use ($dateaAgo, $today) {
        $query->where(function ($q) use ($dateaAgo, $today) {
            $q->whereNull('returned_datetime')
              ->whereBetween('updated_at', [$dateaAgo, $today]);
        })
        ->orWhere(function ($q) use ($dateaAgo, $today) {
            $q->whereNotNull('returned_datetime')
              ->whereBetween('returned_datetime', [$dateaAgo, $today]);
            });
        })->count();
        $balanceDues = TransactionService::getMobileUserBalance($req,$user,'merchant');
        return [
            'balance' => (float)Helper::getNumber($balanceDues['total']),
            'delivered' => $successCount,
            'failed' => $failCount,
            'returned' => $returnCount,
            'total' => $successCount + $failCount + $returnCount
        ];
    }

    public function getConnectWithUs(){
        $user = UserService::getAuthUser('merchant');
        $socialMedias = SocialMedia::where('is_deleted',0)->selectRaw('id,name,photo_file_name,url')->get();
        $companyInfo = CompanyProfileService::profileInfo($user);
        foreach($socialMedias as $sm){
            $sm->image_url = Helper::getImageUrl($sm->photo_file_name,$user->company_id,'social_media');
        }
        $bannerImages = Banner::where('is_deleted',0)
        ->where('channel',$user->account_type)->pluck('photo_file_name')
        ->map(fn($img) => Helper::getImageUrl($img, $user->company_id, 'banner'))
        ->toArray()[0] ?? null;
        $obj = [
            'banner' => $bannerImages,
            'contact' => [
                'phone_1' => $companyInfo->phone,
                'phone_2' => $companyInfo->cp_phone,
                'email' => $companyInfo->email
            ],
            'social_medias' => $socialMedias
        ];
        return ApiResponse::JsonResult($obj);
    }

    public function findPackage(Request $req){
        $user = UserService::getAuthUser('merchant');
        $phone = $req->phone;
        $startDate = $req->startDate;
        $endDate = $req->endDate;
        $statusId = $req->status_id;
        if(!$phone) return ApiResponse::ValidateFail(__('messages.info',[
            'info' => 'Enter your customer phone number to continue'
        ]));
        $qP = Package::query()->where('merchant_id',$user->id)
        ->with(['driver','returnUser','status'])
        ->whereIn('status_id',[10,11,19,9,6])
        ->where('is_deleted',0)
        ->where('receiver_phone',$phone)
        ->selectRaw('id,delivered_datetime,merchant_id,returned_uid,arrive_warehouse_datetime,receiver_phone,receiver_address,receiver_name,cod,price,delivery_fee,status_id,remarks,driver_id,failed_datetime,returned_datetime');
         $qAt = PackageAttachment::where('hidden', 0);
        if($startDate && $endDate){
            $startDate = Helper::dateYMD($startDate);
            $endDate = Helper::dateYMD($endDate);
            // $qFp->whereRaw('p.failed_datetime::DATE >= ? AND p.failed_datetime::DATE <= ? OR p.delivered_datetime::DATE >= ? AND p.delivered_datetime::DATE <= ? OR p.returned_datetime::DATE >= ? AND p.returned_datetime::DATE <= ?', [
            //     $startDate, $endDate,
            //     $startDate, $endDate,
            //     $startDate, $endDate,
            // ]);
             $qP->where(function($q) use ($startDate, $endDate) {
                $q->where(function($q) use ($startDate, $endDate) {
                    // For status_id 10 or 19, query only failed_datetime
                    $q->whereRaw('
                        (failed_datetime::DATE >= ? AND failed_datetime::DATE <= ?)', [$startDate, $endDate])
                        ->whereIn('status_id', [10, 19]);
                })
                ->orWhere(function($q) use ($startDate, $endDate) {
                    // For status_id 9, query only delivered_datetime
                    $q->whereRaw('
                        (delivered_datetime::DATE >= ? AND delivered_datetime::DATE <= ?)', [$startDate, $endDate])
                        ->where('status_id', 9);
                })
                ->orWhere(function($q) use ($startDate, $endDate) {
                    // For status_id 6, query only assign_driver_datetime
                    $q->whereRaw('
                        (assign_driver_datetime::DATE >= ? AND assign_driver_datetime::DATE <= ?)', [$startDate, $endDate])
                        ->where('status_id', 6);
                })
                ->orWhere(function($q) use ($startDate, $endDate) {
                    // For status_id 11, query only returned_datetime
                    $q->whereRaw('
                        (returned_datetime::DATE >= ? AND returned_datetime::DATE <= ?)', [$startDate, $endDate])
                        ->where('status_id', 11);
                });
            });
            $qAt->whereBetween('updated_at', ["$startDate 00:00:00", "$endDate 23:59:59"]);
        }
        if($statusId) $qP->where('status_id', $statusId);
        $attachments = $qAt->limit(700)
        ->pluck('package_id')
        ->toArray();
        $attachmentsLookup = array_flip($attachments);
        $callbackMapper = function($package) use($attachmentsLookup){
            $package->price = (float)$package->price;
            $package->has_img = isset($attachmentsLookup[$package->package_id]);
            $package->cod_fee = $package->cod ? $package->price : 0;
            $package->status_code = $package->status->name;
            $package->driver_phone = $package->driver?->phone;
            if($package->status_id != 11){
                $package->telegram_url = $package->telegram_url = AppSetting::getTelegramLink('merchant',$package->receiver_phone,$package->driver?->phone);
                //::generateTelegramLink($package->driver?->phone);
            }else $package->telegram_url = $package->telegram_url = AppSetting::getTelegramLink('merchant',$package->receiver_phone,$package->returnUser?->phone);
            Helper::generateTelegramLink($package->returnUser?->phone);
            $package->driver_name = $package->driver?->user_name;
            $package->total = (float)$package->cod_fee + $package->delivery_fee;
            $rowStatusId = $package->status_id;
            $finished_date = null;
            if($rowStatusId == 6) $finished_date = $package->arrive_warehouse_datetime;
            if($rowStatusId == 11) $finished_date = $package->returned_datetime;
            if($rowStatusId == 19 || $rowStatusId == 10) $finished_date = $package->failed_datetime;
            if($rowStatusId == 9) $finished_date = $package->delivered_datetime;
            $package->finished_datetime = Helper::formatCustomDateTime($finished_date,'d-M-Y h:i A');
            unset($package->driver,$package->status,$package->returnUser);
            return $package;
        };

        return ApiResponse::PaginationV1($qP,$req,'get packages',[],500,$callbackMapper);
    }

    public function getSearchPackages(Request $req){
        $user = UserService::getAuthUser('merchant');
        return ApiResponse::flex(ReusableService::getHistoryPackages($req,$user,true,'merchant'));

    }

    public function feedBack(Request $req){
        $user = UserService::getAuthUser('merchant');
        $validate = validator($req->all(),[
            'rate' => 'required|int|min:1|max:5',
            'comments' => 'nullable|string|max:350'
        ]);
        if($validate->fails()) return ApiResponse::ValidateFail($validate->errors()->first());
        $inputs = $validate->validated();
        $comments = $inputs['comments'] ?? null;
        $rate = $inputs['rate'];
        $cache = Cache::get('feedback');
        if($cache) return ApiResponse::Duplicated('You have already feedback');
        FeedBack::create([
            'channel' => 'merchant',
            'comments' => $comments,
            'rate' => $rate,
            'create_uid' => $user->id,
            'update_uid' => $user->id,
            'branch_id' => $user->branch_id,
            'company_id' => $user->company_id,
        ]);
        Cache::set('feedback',$user->id,60);

        return ApiResponse::JsonResult(null,__('messages.info',[
            'info' => 'Thanks for your feedback!',
            'khInfo' => 'អរគុណសម្រាប់ការបញ្ចេញមតិ'
        ]));
    }


public function getNotifications(){
        $user = UserService::getAuthUser('merchant');
        $notifications = Notification::where('user_id',$user->id)->where('is_read',0)->orderByDesc('sent_datetime')->selectRaw('id,is_read,title,body,sent_datetime')->get();
        $groupedPackages = collect($notifications)->map(function ($item) {
            $item->groupKey = date('d-M-Y',strtotime($item->sent_datetime));
            $item->time = Helper::formatCustomDateTime($item->sent_datetime,'h:i A');
            return $item;
        })
        ->groupBy('groupKey')
        ->map(function ($group, $date) {
            $group->each(function ($item) use ($group) {
                unset($item->groupKey,$item->sent_datetime);
            });
            return [
                'date' => $date,
                'details' => $group->values(),
            ];
        })->values();
        return ApiResponse::JsonResult($groupedPackages);
    }

    public function readNotification(Request $req){
        $user = UserService::getAuthUser('merchant');
        $mr = GeneralSettingController::markReadNotification($req,$user);
        return ApiResponse::flex(null,$mr);
    }
}
