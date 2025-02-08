<?php

namespace App\Http\Controllers\Mobile\Merchant\V1;

use ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Mobile\V1\GeneralSettingController;
use App\Models\Banner;
use App\Models\BrandImage;
use App\Models\FeedBack;
use App\Models\Notification;
use App\Models\Order;
use App\Models\Package;
use App\Models\Promotion;
use App\Models\SocialMedia;
use App\Models\UserBank;
use App\Services\CompanyProfileService;
use App\Services\GeneralSettingService;
use App\Services\Mobile\ReusableService;
use App\Services\PickupCenterService;
use App\Services\TransactionService;
use App\Services\UserService;
use Cache;
use Helper;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    //
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

    public function trackingActivitySummary(Request $req){
        $user = UserService::getAuthUser('merchant');
        $pendingCount = Order::where('merchant_id',$user->id)->where('is_deleted',0)->where('status_id',1)->count();
        $pickCount = Order::where('merchant_id',$user->id)->where('is_deleted',0)->whereIn('status_id',[2,3,4])->count();
        $onDeliveryCount = Package::where('merchant_id',$user->id)->where('is_deleted',0)->where('status_id',6)->count();
        $successCount = Package::where('merchant_id',$user->id)->where('is_deleted',0)->where('status_id',9)->count();
        $failCount = Package::where('merchant_id',$user->id)->where('is_deleted',0)->whereIn('status_id',[10,19])->count();
        $returnCount = Package::where('merchant_id',$user->id)->where('is_deleted',0)->where('status_id',11)->count();
        $totalCount = $pendingCount + $pickCount + $onDeliveryCount + $successCount + $failCount + $returnCount;
        $obj = [
            'pending' => $pendingCount,
            'pick' => $pickCount,
            'on_delivery' => $onDeliveryCount,
            'success' => $successCount,
            'fail' => $failCount,
            'return' => $returnCount,
            'total' => $totalCount,
            'date' => Helper::getDateTime('d-M-Y')
        ];
        return ApiResponse::JsonResult($obj);
    }

    public function getBankAccount(){
        $user = UserService::getAuthUser('merchant');
        $userBanks = UserBank::where('user_id',$user->id)->selectRaw('id,bank_name,bank_number,account_name,is_primary')->orderByDesc('is_primary')->get();
        $displayBanks = $userBanks->toArray();

        // Check the number of existing records
        if ($userBanks->isEmpty()) {
            // No records, add two: one primary and one secondary
            $displayBanks[] = [
                'bank_name' => '',
                'bank_number' => '',
                'account_name' => '',
                'is_primary' => true, // First record is primary
                'skip' => 1
            ];
            $displayBanks[] = [
                'bank_name' => '',
                'bank_number' => '',
                'account_name' => '',
                'is_primary' => false, // Second record is not primary
                'skip' => 1
            ];
        } elseif ($userBanks->count() === 1) {
            // One record exists, check its `is_primary` value
            $existing = $userBanks->first();
            if ($existing->is_primary) {
                // If the existing record is primary, add a secondary row
                $displayBanks[] = [
                    'bank_name' => '',
                    'bank_number' => '',
                    'account_name' => '',
                    'is_primary' => false,
                    'skip' => 1
                ];
            } else {
                // If the existing record is not primary, add a primary row first
                $displayBanks = array_merge([
                    [
                        'bank_name' => '',
                        'bank_number' => '',
                        'account_name' => '',
                        'is_primary' => true,
                        'skip' => 1
                    ]
                ], $displayBanks);
            }
        }
        return ApiResponse::JsonResult($displayBanks);
    }

    public function saveBankAccount(Request $req){
        $user = UserService::getAuthUser('merchant');
        if(!$req->bank_info) return ApiResponse::ValidateFail('You must provide a bank_info');
        $saveBank = UserService::saveUserBanks($req->bank_info,$user->id,$user);
        return ApiResponse::flex($saveBank);
    }

    public function deleteBankAccount(Request $req){
        $user = UserService::getAuthUser('merchant');
        $saveBank = UserService::deleteBank($req->id,$user);
        return ApiResponse::flex($saveBank);
    }

    public function getPendingOrders(Request $req){
        $user = UserService::getAuthUser('merchant');
        $orders = Order::where('merchant_id',$user->id)
        ->where('is_deleted',0)
        ->with('driver')
        ->selectRaw('id,code,qty,product_type,vehicle_type,order_datetime,status_id')->where('status_id',1)->get();
        foreach($orders as $order){
            $order->status_code = 'Pending';
            $order->order_datetime = Helper::formatCustomDateTime($order->order_datetime);
            $order->driver_name = $order->driver?->user_name;
        }
        return ApiResponse::Pagination($orders,$req);
    }

    public function getPickOrders(Request $req){
        $user = UserService::getAuthUser('merchant');
        $orders = Order::where('merchant_id',$user->id)
        ->with(['tracking_status','driver'])
        ->where('is_deleted',0)
        ->selectRaw('id,code,qty,product_type,vehicle_type,order_datetime,status_id,driver_id')->whereIn('status_id',[2,3,4])->get();
        foreach($orders as $order){
            $order->status_code = $order->tracking_status->name;
            $order->driver_phone = $order->driver->phone;
            $order->driver_name = $order->driver->user_name;
            $order->order_datetime = Helper::formatCustomDateTime($order->order_datetime);
            unset($order->tracking_status,$order->driver);
        }
        return ApiResponse::Pagination($orders,$req);
    }

    public function getOnDeliveryPackages(Request $req){
        $user = UserService::getAuthUser('merchant');
        $packages = Package::where('merchant_id', $user->id)
        ->with('driver')
        ->where('status_id', 6)
        ->where('is_deleted', 0)
        ->selectRaw('id,merchant_id,receiver_phone,receiver_address,receiver_name,cod,price,delivery_fee,remarks,driver_id,arrive_warehouse_datetime')
        ->get()
        ->map(function ($package) {
            // Cast price to float manually
            $package->price = (float) $package->price;
            $package->cod_fee = $package->cod ? $package->price : 0;
            $package->status_code = 'On Delivery';
            $package->driver_phone = $package->driver->phone ?? null; // Ensure driver relationship exists
            $package->driver_name = $package->driver->user_name ?? null;
            $package->total = (float) $package->cod_fee + $package->delivery_fee;
            $package->delivery_fee = (float) $package->delivery_fee;
            $package->fee = $package->delivery_fee;
            $package->arrive_warehouse_datetime = Helper::formatCustomDateTime($package->arrive_warehouse_datetime);

            // Remove the driver relationship if not needed in the response
            unset($package->driver);

            return $package;
        });

    return ApiResponse::Pagination($packages, $req);
    }

    public function getTermConditions(Request $req){
        $user = UserService::getAuthUser('merchant');
        return ApiResponse::JsonResult(GeneralSettingService::termAndConditions($user));
    }

    public function getSuccessPackages(Request $req){
        $user = UserService::getAuthUser('merchant');
        $packages = Package::where('merchant_id',$user->id)
        ->with('driver')
        ->where('status_id',9)
        ->where('is_deleted',0)
        ->selectRaw('id,merchant_id,receiver_phone,receiver_address,receiver_name,cod,price,delivery_fee,remarks,driver_id,delivered_datetime,arrive_warehouse_datetime')
        ->get()->map(function($package){
            $package->price = (float) $package->price;
            $package->cod_fee = $package->cod ? $package->price : 0;
            $package->status_code = 'Delivered';
            $package->driver_phone = $package->driver->phone;
            $package->driver_name = $package->driver->user_name;
            $package->total = (float)$package->cod_fee + $package->delivery_fee;
            $package->delivery_fee = (float)$package->delivery_fee;
            $package->arrive_warehouse_datetime = Helper::formatCustomDateTime($package->arrive_warehouse_datetime);
            $package->delivered_datetime = Helper::formatCustomDateTime($package->delivered_datetime);
            $package->fee = $package->delivery_fee;
            unset($package->driver);
            return $package;
        });


        return ApiResponse::Pagination($packages,$req);
    }

    public function getFailPackages(Request $req){
        $statusId = $req->status_id;
        $user = UserService::getAuthUser('merchant');
        $packages = Package::where('merchant_id',$user->id)
        ->with(['driver','status'])
        ->where('status_id',$statusId)
        ->where('is_deleted',0)
        ->selectRaw('id,merchant_id,arrive_warehouse_datetime,receiver_phone,receiver_address,receiver_name,cod,price,delivery_fee,status_id,remarks,driver_id,failed_datetime')
        ->get()
        ->map(function($package){
            $package->price = (float)$package->price;
            $package->cod_fee = $package->cod ? $package->price : 0;
            $package->status_code = $package->status->name;
            $package->driver_phone = $package->driver->phone;
            $package->driver_name = $package->driver->user_name;
            $package->total = (float)Helper::getNumber($package->cod_fee + $package->delivery_fee,2);
            $package->fee = (float)$package->delivery_fee;
            $package->delivery_fee = (float)$package->delivery_fee;
            $package->arrive_warehouse_datetime = Helper::formatCustomDateTime($package->arrive_warehouse_datetime);
            $package->failed_datetime = Helper::formatCustomDateTime($package->failed_datetime);
            unset($package->driver,$package->status);
            return $package;
        });
        return ApiResponse::Pagination($packages,$req);
    }

    public function getReturnPackages(Request $req){
        $user = UserService::getAuthUser('merchant');
        $packages = Package::where('merchant_id',$user->id)
        ->with(['driver','status'])
        ->whereIn('status_id',[11])
        ->selectRaw('id,merchant_id,arrive_warehouse_datetime,receiver_phone,receiver_address,receiver_name,cod,price,delivery_fee,status_id,remarks,driver_id,failed_datetime,returned_datetime,updated_at')
        ->get()
        ->map(function($package){
            $package->price = (float)$package->price;
            $package->cod_fee = $package->cod ? $package->price : 0;
            $package->status_code = $package->status->name;
            $package->driver_phone = $package->driver?->phone;
            $package->driver_name = $package->driver?->user_name;
            $package->total = (float) $package->cod_fee + $package->delivery_fee;
            $package->delivery_fee = (float)$package->delivery_fee;
            $package->fee = $package->delivery_fee;
            $returnDate = $package->return_datetime ? $package->return_datetime : $package->updated_at;
            $package->returned_date = Helper::dateDMY($returnDate);
            $package->return_time = Helper::formatCustomDateTime($returnDate, 'h:i:s');
            unset($package->driver,$package->status);
            return $package;
        });
        return ApiResponse::Pagination($packages,$req);
    }

    public function getPromotions(Request $req){
        $user = UserService::getAuthUser('merchant');
        $today = date('Y-m-d');
        $promotions = Promotion::where('is_deleted',0)->selectRaw('id,title,description,photo_file_name,start_date,end_date')
        ->where('channel','merchant')
        ->whereRaw('DATE(start_date) >= ? AND DATE(end_date) <= ?',[$today,$today])
        ->orWhereDate('start_date','>=',$today)
        ->orderByDesc('start_date')
        ->get();
        foreach($promotions as $promotion){
            $xDays = Helper::getAnalyzDiffDate($promotion->start_date,$promotion->end_date,'days');
            $promotion->expires_at = $xDays;
            $promotion->time_ago = Helper::timeAgo($promotion->start_date,false);
            $promotion->image_url = Helper::getImageUrl($promotion->photo_file_name,$user->company_id,'promotion');
        }

        return ApiResponse::Pagination($promotions,$req);
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


    public function getHomeScreen(Request $req,$user){
        $user = UserService::getAuthUser('merchant');
        $bannerImages = BrandImage::where('is_deleted',0)
        ->where('channel',$user->account_type)->pluck('photo_file_name')
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
        $dateaAgo = Helper::getDateDaysAgo(90);
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
        if(!$phone) return ApiResponse::ValidateFail(__('messages.info',[
            'info' => 'Enter your customer phone number to continue'
        ]));
        $packages = Package::where('merchant_id',$user->id)
        ->with(['driver','status'])
        ->whereIn('status_id',[10,19,9,6])
        ->where('is_deleted',0)
        ->where('receiver_phone',$phone)
        ->selectRaw('id,merchant_id,arrive_warehouse_datetime,receiver_phone,receiver_address,receiver_name,cod,price,delivery_fee,status_id,remarks,driver_id,failed_datetime')
        ->get();
        foreach($packages as $package){
            $package->price = (float)$package->price;
            $package->cod_fee = $package->cod ? $package->price : 0;
            $package->status_code = $package->status->name;
            $package->driver_phone = $package->driver->phone;
            $package->driver_name = $package->driver->user_name;
            $package->total = (float)$package->cod_fee + $package->delivery_fee;
            unset($package->driver,$package->status);
        }
        return ApiResponse::Pagination($packages,$req);
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
