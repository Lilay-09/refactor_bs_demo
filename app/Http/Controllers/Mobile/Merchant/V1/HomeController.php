<?php

namespace App\Http\Controllers\Mobile\Merchant\V1;

use ApiResponse;
use App\DTO\Mobile\BalanceDTO;
use App\DTO\Mobile\MerchantBalanceDTO;
use App\DTO\Mobile\V2\MerchantPickupDTO;
use App\DTO\Mobile\MerchantSearchDTO;
use App\DTO\Mobile\MerchantTrackingActivityDTO;
use App\DTO\Mobile\V2\TrackingAtWarehouseDTO;
use App\DTO\Mobile\V2\TrackingFailPackageDTO;
use App\DTO\Mobile\V2\TrackingOnDeliveryPackageDTO;
use App\DTO\Mobile\V2\TrackingReturnDTO;
use App\DTO\Mobile\V2\TrackingSuccessPackageDTO;
use App\Enums\TrackingStatus;
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
use App\Services\PackageTrailServiceImpl;
use App\Services\PickupCenterServiceImpl;
use App\Services\TransactionService;
use App\Services\UserService;
use Illuminate\Support\Facades\Cache;
use Helper;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Validator;

class HomeController extends Controller
{
    //
    protected $reuseableService;
    protected string $dateFmt;
    public function __construct(){
        $this->dateFmt = 'd/m/Y';
        $this->reuseableService = new ReusableService();
    }
    public function createBooking(Request $req){
        $user = UserService::getAuthUser('merchant');
        $pck = new PickupCenterServiceImpl();
        $details = $req->details ?? [];
        $req->merge([
            'warehouse_id' => GeneralSettingService::getWarehouse($user)?->id,
            'branch_id' => $user->branch_id,
            'merchant_id' => $user->id
        ]);
        if($details){
            $details = Helper::convertJsonTextToJson($details);
            if($details->error) return ApiResponse::ValidateFail(__('messages.info',[
                'info' => 'Please check your details again',
                'khInfo' => "សូមពិនិត្យព័ត៌មានលម្អិតរបស់អ្នកម្តងទៀត"
            ]));
            $req->merge(['details' => $details->result]);//$details->result;
        }
        $create = $pck->createOrder($req,$user);
        if($create->error) return ApiResponse::flex($create);
        return ApiResponse::JsonResult(null,__('messages.info',[
            'info' => 'Your order has been submitted',
            'khInfo' => "បានបង្កើត"
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
        $user = UserService::getAuthUser('merchant');

        // Parse date range from request or fallback to today
        $startDate = $req->query('startDate') 
            ? \Carbon\Carbon::parse($req->query('startDate'))->startOfDay() 
            : now()->startOfDay();
        $endDate = $req->query('endDate') 
            ? \Carbon\Carbon::parse($req->query('endDate'))->endOfDay() 
            : now()->endOfDay();

        // Orders summary filtered by created_at
        $orderCounts = Order::where('merchant_id', $user->id)
            ->where('is_deleted', false)
            ->whereBetween('order_datetime', [$startDate, $endDate])
            ->selectRaw('
                SUM(CASE WHEN status_id = 1 THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status_id IN (2, 3, 4) THEN 1 ELSE 0 END) as pick
            ')
            ->first();

        // Packages summary with date filters applied
        $packageCounts = Package::where('merchant_id', $user->id)
            ->where('is_deleted', false)
            ->whereIn('status_id',[5,6,9,10,23,19])
            ->selectRaw('
                SUM(CASE WHEN status_id = 5 AND arrive_warehouse_datetime BETWEEN ? AND ? THEN 1 ELSE 0 END) as at_warehouse,
                SUM(CASE WHEN status_id = 6 AND assign_driver_datetime BETWEEN ? AND ? THEN 1 ELSE 0 END) as on_delivery,
                SUM(CASE WHEN status_id = 9 AND delivered_datetime BETWEEN ? AND ? THEN 1 ELSE 0 END) as success,
                SUM(CASE WHEN status_id = 23 AND returned_datetime BETWEEN ? AND ? THEN 1 ELSE 0 END) as returned,
                SUM(CASE WHEN status_id = 10 AND failed_datetime BETWEEN ? AND ? THEN 1 ELSE 0 END) as failed,
                SUM(CASE WHEN status_id = 19 AND failed_datetime BETWEEN ? AND ? THEN 1 ELSE 0 END) as failed_with_fee,
                SUM(CASE WHEN status_id != 23 AND (
                    delivered_datetime BETWEEN ? AND ? OR
                    returned_datetime BETWEEN ? AND ? OR
                    assign_driver_datetime BETWEEN ? AND ? OR
                    failed_datetime BETWEEN ? AND ?
                ) THEN price ELSE 0 END) as total_cod,
                
                SUM(CASE WHEN status_id != 23 AND (
                    delivered_datetime BETWEEN ? AND ? OR
                    returned_datetime BETWEEN ? AND ? OR
                    assign_driver_datetime BETWEEN ? AND ? OR
                    failed_datetime BETWEEN ? AND ?
                ) THEN price_khr ELSE 0 END) as total_cod_khr
            ', [
                $startDate, $endDate,       // at_warehouse
                $startDate, $endDate,       // on_delivery
                $startDate, $endDate,       // success
                $startDate, $endDate,       // returned
                $startDate, $endDate,       // failed
                $startDate, $endDate,       // failed_with_fee
                $startDate, $endDate,       // total_cod delivered
                $startDate, $endDate,       // total_cod returned
                $startDate, $endDate,       // total_cod on_delivery
                $startDate, $endDate,       // total_cod failed
                
                $startDate, $endDate,       // total_cod_khr delivered
                $startDate, $endDate,       // total_cod_khr returned
                $startDate, $endDate,       // total_cod_khr on_delivery
                $startDate, $endDate        // total_cod_khr failed
            ])
            ->first();


        $totalCount = $packageCounts->success + $packageCounts->failed_with_fee;

        return ApiResponse::JsonResult(new MerchantTrackingActivityDTO(
            total_package: $totalCount,
            total_cod: Helper::currencyAmount($packageCounts->total_cod,'USD'),
            total_cod_khr: Helper::currencyAmount($packageCounts->total_cod_khr,'KHR'),
            pending: $orderCounts->pending ?? 0,
            pickup: $orderCounts->pick ?? 0,
            at_warehouse: $packageCounts->at_warehouse,
            on_delivery: $packageCounts->on_delivery,
            delivered: $packageCounts->success,
            failed: $packageCounts->failed,
            returned: $packageCounts->returned,
            failed_with_fee: $packageCounts->failed_with_fee
        ));
    }

    public function getBankAccount(){
        $user = UserService::getAuthUser('merchant');
        $userBanks = UserBank::where('user_id',$user->id)->selectRaw('id,bank_id,currency,bank_name,bank_number,account_name,is_primary')->orderByDesc('is_primary')->first();
        return ApiResponse::JsonResult($userBanks);
    }

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
        $userBank = UserBank::where('user_id',$user->id)->orderByDesc('is_primary')->first();
        $inputs = $validator->validated();
        $bankInfo = Bank::select('name')->find($inputs['bank_id']);
        if(!$bankInfo) return ApiResponse::ValidateFail('Bank not found');
        $inputs['bank_name'] = $bankInfo->name;
        $inputs['update_uid'] = $user->id;
        $inputs['branch_id'] = $user->branch_id;
        $inputs['company_id'] = $user->company_id;
        $inputs['is_primary'] = true;
        if($userBank){
            $userBank->update($inputs);
        }else{
            $inputs['user_id'] = $user->id;
            $inputs['create_uid'] = $user->id;
            UserBank::create($inputs);
        }
        return ApiResponse::JsonResult(null,__('messages.saved'));
    }

    public function deleteBankAccount(Request $req){
        $user = UserService::getAuthUser('merchant');
        $saveBank = UserService::deleteBank($req->id,$user);
        return ApiResponse::flex($saveBank);
    }

    public function getPendingOrders(Request $req){
        // $today = now();
        // $dateaAgo = Helper::getDateDaysAgo(0);
        $startDate = $req->query('startDate');
        $endDate = $req->query('endDate');
        $lang = $req->lang;
        $user = UserService::getAuthUser('merchant');
        $qO = Order::where('merchant_id',$user->id)
        ->where('is_deleted',0)
        ->with('driver')
        ->selectRaw('id,code,qty,product_type,vehicle_type,order_datetime,pickup_address,loc_lat,loc_lng,status_id')->where('status_id',1);
        // $qO->where(function ($q) use ($dateaAgo, $today) {
        //     $q->whereBetween('order_datetime', [$dateaAgo, $today]);
        // });
        if($startDate && $endDate){
            $startDatetime = Helper::dateYMD($startDate). ' 00:00:00';
            $endDatetime = Helper::dateYMD($endDate). ' 23:59:59';
            $qO->whereBetween('order_datetime',[$startDatetime,$endDatetime]);
        }
        $orders = $qO->orderByDesc('id');
        $callback = function($order) use($lang){
            if($lang == 'km') $order->status_code = 'រង់ចាំ';
            else $order->status_code = 'Pending';
            // $order->order_datetime = Helper::formatCustomDateTime($order->order_datetime);
            $order->order_date = Helper::dateDMY($order->order_datetime,);
            $order->order_time = Helper::dateDMY($order->order_datetime,'h:i A');
            $order->driver_name = $order->driver?->username;
            $order->driver_phone = $order->driver?->phone;
            unset($order->driver);
            return $order;
        };
        return ApiResponse::PaginationV1($orders,$req,'',[],100,$callback);
    }

    public function getPickOrders(Request $req){
        // $today = now();
        // $dateaAgo = Helper::getDateDaysAgo(0);
        $startDate = $req->query('startDate');
        $endDate = $req->query('endDate');
        $user = UserService::getAuthUser('merchant');
        // $lang = $req->lang;
        $qO = Order::where('merchant_id',$user->id)
        ->where('is_deleted',false)
        ->with([
            'tracking_status',
            'driver',
            'images'
        ])
        ->where('is_deleted',0)
        ->selectRaw('id,code,qty,product_type,vehicle_type,order_datetime,status_id,pickup_datetime,driver_id')->whereIn('status_id',[2,3,4]);
        // $qO->where(function ($q) use ($dateaAgo, $today) {
        //     $q->whereBetween('order_datetime', [$dateaAgo, $today]);
        // });
        if($startDate && $endDate){
            $startDatetime = Helper::dateYMD($startDate). ' 00:00:00';
            $endDatetime = Helper::dateYMD($endDate). ' 23:59:59';
            $qO->whereBetween('pickup_datetime',[$startDatetime,$endDatetime]);
        }
        $orders = $qO->orderByDesc('id');
        $callback = function($order): MerchantPickupDTO{
            // if($lang == 'km') $order->status_code = GeneralSettingService::$statusCodeTrans[$order->status_id];
            // else $order->status_code = $order->tracking_status->name;
            // $order->status_code = TrackingStatus::tryFrom($order->status_id)->label();
            // $order->driver_phone = $order->driver->phone;
            // $order->telegram_url = Helper::generateTelegramLink($order->driver->phone);
            // $order->driver_name = $order->driver->username;
            // $order->order_datetime = Helper::formatCustomDateTime($order->order_datetime);
            unset($order->tracking_status,$order->driver);
            $imgs = [];
            if(!empty($order->images)){
                foreach($order->images as $img){
                    $imageAt = Helper::dateYMD($img->created_at);
                    $imgs[] = $img->image_url = Helper::getImageUrl($img->photo_file_name,1,'order_image',$imageAt);
                }
            }
            return new MerchantPickupDTO(
                id:$order->id,
                code:$order->code,
                qty:$order->qty,
                date:Helper::dateDMY($order->pickup_datetime),
                time:Helper::time($order->pickup_datetime),
                status_id: $order->status_id,
                status_code:TrackingStatus::tryFrom($order->status_id)->label() ?? '',
                vehicle_type: $order->vehicle_type,
                driver_name:$order->driver->username,
                driver_phone:$order->driver->phone,
                product_type:$order->product_type,
                images: $imgs
            );
        };

        return ApiResponse::PaginationV1($orders,$req,'',[],200,$callback);
    }

    private function getQueryPackages(
        $userId, 
        array $statusIds, 
        string $startDate, 
        string $endDate, 
        array $select = ['*'], 
        ?string $search = null,
        array $searchKeys = [],
    )
    {
        $qP = Package::query()
            ->where('is_deleted', false)
            ->where('merchant_id', $userId)
            ->select($select)
            ->with('image');
            
        if (!empty($statusIds)) {
            $qP->whereIn('status_id', $statusIds);
        }

        // Define common relationships
        $with = [
            'orderImage',
            'submittedImages' => fn($q) => $q->orderBy('updated_at', 'desc')->limit(2),
        ];

        // Add additional relationships dynamically if provided
        if (!empty($additionalWith)) {
            $with = array_merge($with, $additionalWith);
        }

        $qP->with($with);

        // Load specific relationships based on status
        if (array_intersect($statusIds, [11, 19])) {
            $qP->with(['returnUser:id,username,phone']);
        } else {
            $qP->with(['driver:id,username,phone']);
        }

        // Apply search conditions if `search` is provided
        if (!empty($search) && !empty($searchKeys)) {
            $this->applySearchConditions($qP, $search, $searchKeys);
            // Apply additional 15-day date filtering with status-specific columns
            $this->applyDynamicDateFilters(
                $qP,
                Carbon::now()->subDays(15)->startOfDay(),
                Carbon::now()->endOfDay(),
                $statusIds
            );
        } else {
            // Apply regular date filtering based on $startDate and $endDate
            if ($startDate && $endDate) {
                $this->applyDynamicDateFilters(
                    $qP,
                    Carbon::parse(Helper::dateYMD($startDate))->startOfDay(),
                    Carbon::parse(Helper::dateYMD($endDate))->endOfDay(),
                    $statusIds
                );
            }
        }

        return $qP;
    }


    private function applySearchConditions($query, string $search, array $searchKeys)
    {
        $query->where(function ($q) use ($search, $searchKeys) {
            foreach ($searchKeys as $key) {
                $q->orWhere($key, 'LIKE', "%{$search}%");
            }
        });
    }

    private function applyDynamicDateFilters($query, $startDatetime, $endDatetime, array $statusIds)
    {
        // Mapping of `status_id` to datetime columns
        $statusToColumnMap = [
            10 => 'failed_datetime',
            19 => 'failed_datetime',
            9  => 'delivered_datetime',
            11 => 'returned_datetime',
            6 => 'assign_driver_datetime',
            5  => 'arrive_warehouse_datetime',
        ];

        $query->where(function ($q) use ($statusToColumnMap, $startDatetime, $endDatetime, $statusIds) {
            $isFirstCondition = true;

            foreach ($statusToColumnMap as $statusId => $column) {
                if (in_array($statusId, $statusIds)) {
                    if ($isFirstCondition) {
                        $q->whereBetween($column, [$startDatetime, $endDatetime])
                        ->where('status_id', $statusId);
                        $isFirstCondition = false; // First condition added
                    } else {
                        $q->orWhere(function ($q) use ($column, $startDatetime, $endDatetime, $statusId) {
                            $q->whereBetween($column, [$startDatetime, $endDatetime])
                            ->where('status_id', $statusId);
                        });
                    }
                }
            }
        });
    }

    public function getOnDeliveryPackages(Request $req){
        $user = UserService::getAuthUser();
        $select = [
            'id','arrive_warehouse_datetime','receiver_address','receiver_phone','price','price_khr',
            'status_id','qr_code','delivery_fee','other_fee','driver_id','zone_name','taxi_fee',
            'payer','remarks'
        ];
        $query = $this->getQueryPackages(
            userId: $user->id,
            statusIds: [6],
            startDate: $req->query('startDate'),
            endDate: $req->query('endDate'),
            select: $select
        )->orderBy('id','desc');

        $callback = function ($q){
            $fees = ($q->payer == 'sender') ? ($q->delivery_fee + $q->other_fee) : 0;
            $feesFmt = Helper::amountStdFmt($fees);
            $driverCodUsd = $q->driver_cod_usd;
            $driverCodKhr = $q->driver_cod_khr;
            $codAmt = $this->merchantCod($driverCodUsd,$driverCodKhr,$fees);
            $driver_contacts = $q->driver?->userContacts?->toArray() ?? [];
            return new TrackingOnDeliveryPackageDTO(
                package_id: $q->id,
                code:$q->qr_code,
                receiver_phone:$q->receiver_phone,
                cod_usd: Helper::currencyAmount($codAmt['cod_usd'],'USD'),
                arrive_date: Helper::dateYMD($q->arrive_warehouse_datetime),
                arrive_time: Helper::time($q->arrive_warehouse_datetime),
                zone_name: $q->zone_name,
                status_id: $q->status_id,
                status_code: TrackingStatus::tryFrom($q->status_id)->label(),
                cod_khr: Helper::currencyAmount($codAmt['cod_khr'],'KHR'),
                receiver_address: $q->receiver_address,
                image: $q->image_url,
                driver_name: $q->driver->username,
                driver_phone: $q->driver->phone,
                taxi_fee: $q->taxi_fee,// ($q->taxi_fee > 0 && $q->payer == 'sender') ? Helper::currencyAmount($q->taxi_fee,'USD'):'$0',
                fees: $feesFmt,
                remarks: $q->remarks,
                other_fee: $q->other_fee,
                delivery_fee: $q->delivery_fee,
                driver_contacts: $driver_contacts
            );
        };
        return ApiResponse::PaginationV1($query,$req,'',[],200,$callback,$select); 
    }

    public function getTermConditions(Request $req){
        $user = UserService::getAuthUser('merchant');
        return ApiResponse::JsonResult(GeneralSettingService::termAndConditions($user));
    }

    public function getSuccessPackages(Request $req){
        $user = UserService::getAuthUser();
        $select = [
            'id','arrive_warehouse_datetime','receiver_address','receiver_phone','price','price_khr',
            'status_id','qr_code','delivery_fee','other_fee','driver_id','zone_name','method','taxi_fee',
            'driver_cod_khr','driver_cod_usd','merchant_id','delivered_datetime','payer','remarks'
        ];
        $query = $this->getQueryPackages(
            userId: $user->id,
            statusIds: [TrackingStatus::DELIVERED->value],
            startDate: $req->query('startDate'),
            endDate: $req->query('endDate'),
            select: $select
        );

        $callback = function ($q):TrackingSuccessPackageDTO{
            $driver_contacts = $q->driver?->userContacts?->toArray() ?? [];
            // $fees = $q->payer == 'sender' ? Helper::currencyAmount($q->delivery_fee + $q->other_fee,'USD'):'$0';
            $receivedAmtUsd = Helper::currencyAmount(0,'USD');
            $receivedAmtKhr = Helper::currencyAmount(0,'KHR');
            $fees = ($q->payer == 'sender') ? ($q->delivery_fee + $q->other_fee) : 0;
            $feesFmt = Helper::amountStdFmt($fees);
            $driverCodUsd = $q->driver_cod_usd;
            $driverCodKhr = $q->driver_cod_khr;
            $codAmt = $this->merchantCod($driverCodUsd,$driverCodKhr,$fees);
            $pmtStatus = 'pending';
            if($q->hasMerchantPayment()){
                if($q->driver_cod_usd > 0 && $q->driver_cod_khr > 0){
                    $receivedAmtUsd = Helper::currencyAmount($q->driver_cod_usd,'USD');
                    $receivedAmtKhr = Helper::currencyAmount($q->driver_cod_khr,'KHR');
                }else if($q->driver_cod_usd > 0){
                    $receivedAmtUsd = Helper::currencyAmount($q->driver_cod_usd,'USD');
                }else if($q->driver_cod_khr > 0){
                    $receivedAmtKhr = Helper::currencyAmount($q->driver_cod_khr,'KHR');
                }
                $pmtStatus = 'Received';
            }
            $image = $q->image_url;
            return new TrackingSuccessPackageDTO(
                package_id: $q->id,
                code:$q->qr_code,
                receiver_phone: $q->receiver_phone,
                cod_usd: Helper::currencyAmount($codAmt['cod_usd'],'USD'),
                arrive_date: Helper::dateYMD($q->arrive_warehouse_datetime),
                arrive_time: Helper::time($q->arrive_warehouse_datetime),
                zone_name: $q->zone_name,
                status_id: $q->status_id,
                status_code: TrackingStatus::tryFrom($q->status_id)->label(),
                cod_khr: Helper::currencyAmount($codAmt['cod_khr'],'KHR'),
                receiver_address: $q->receiver_address,
                image: $image,
                remarks: $q->remarks,
                submitted_image_urls: $q->submitted_image_urls,
                driver_name: $q->driver->username,
                driver_phone: $q->driver->phone,
                finished_date: Helper::dateDMY($q->delivered_datetime),
                finished_time: Helper::time($q->delivered_datetime),
                method: $q->method != 'cod' ? 'Bank':'',
                pmt_status: $pmtStatus,
                receiver_amt_usd: $receivedAmtUsd,
                receiver_amt_khr: $receivedAmtKhr,
                taxi_fee: $q->taxi_fee,// ($q->taxi_fee > 0 && $q->payer == 'sender') ? Helper::currencyAmount($q->taxi_fee,'USD'):'$0',
                fees: $feesFmt,
                other_fee: $q->other_fee,
                delivery_fee: $q->delivery_fee,
                driver_contacts: $driver_contacts
            );
        };
        return ApiResponse::PaginationV1($query,$req,'',[],200,$callback,$select); 
    }

    public function getFailPackages(Request $req){
        $statusId = $req->status_id;
        $user = UserService::getAuthUser();
        $select = [
            'id','arrive_warehouse_datetime','receiver_address','receiver_phone','price','price_khr',
            'status_id','qr_code','delivery_fee','other_fee','driver_id','zone_name','method',
            'driver_cod_khr','driver_cod_usd','merchant_id','failed_datetime','taxi_fee','delivery_remarks',
            'remarks'
        ];
        $query = $this->getQueryPackages(
            userId: $user->id,
            statusIds: [$statusId],
            startDate: $req->query('startDate'),
            endDate: $req->query('endDate'),
            select: $select
        );

        $callback = function ($q):TrackingFailPackageDTO{
            $driver_contacts = $q->driver?->userContacts?->toArray() ?? [];
            // $fees = $q->payer == 'sender' ? Helper::currencyAmount($q->delivery_fee + $q->other_fee,'USD'):'$0';
            $fees = ($q->payer == 'sender') ? ($q->delivery_fee + $q->other_fee) : 0;
            $feesFmt = Helper::amountStdFmt($fees);
            $driverCodUsd = $q->driver_cod_usd;
            $driverCodKhr = $q->driver_cod_khr;
            $codAmt = $this->merchantCod($driverCodUsd,$driverCodKhr,$fees);
            return new TrackingFailPackageDTO(
                package_id: $q->id,
                code:$q->qr_code,
                receiver_phone: $q->receiver_phone,
                cod_usd: Helper::currencyAmount($codAmt['cod_usd'],'USD'),
                arrive_date: Helper::dateYMD($q->arrive_warehouse_datetime),
                arrive_time: Helper::time($q->arrive_warehouse_datetime),
                zone_name: $q->zone_name,
                status_id: $q->status_id,
                status_code: TrackingStatus::tryFrom($q->status_id)->label(),
                cod_khr: Helper::currencyAmount($codAmt['cod_khr'],'KHR'),
                receiver_address: $q->receiver_address,
                image: $q->image_url,
                submitted_image_urls: $q->submitted_image_urls,
                driver_name: $q->driver->username,
                driver_phone: $q->driver->phone,
                finished_date: Helper::dateDMY($q->failed_datetime),
                finished_time: Helper::time($q->failed_datetime),
                taxi_fee: $q->taxi_fee,// ($q->taxi_fee > 0 && $q->payer == 'sender') ? Helper::currencyAmount($q->taxi_fee,'USD'):'$0',
                fees: $feesFmt,
                reason: $q->delivery_remarks,
                remarks: $q->remarks,
                other_fee: $q->other_fee,
                delivery_fee: $q->delivery_fee,
                driver_contacts: $driver_contacts
            );
        };
        return ApiResponse::PaginationV1($query,$req,'',[],200,$callback,$select); 
    }

    public function getReturnPackages(Request $req){
        $user = UserService::getAuthUser();
        $select = [
            'id','arrive_warehouse_datetime','receiver_address','receiver_phone','price','price_khr',
            'status_id','qr_code','delivery_fee','other_fee','returned_uid','zone_name','method','other_fee',
            'driver_cod_khr','driver_cod_usd','merchant_id','returned_datetime','taxi_fee','delivery_remarks',
            'remarks'
        ];
        $query = $this->getQueryPackages(
            userId: $user->id,
            statusIds: [TrackingStatus::RETURNED->value],
            startDate: $req->query('startDate'),
            endDate: $req->query('endDate'),
            select: $select
        );

        $callback = function ($q):TrackingReturnDTO{
            $fees = $q->payer == 'sender' ? Helper::currencyAmount($q->delivery_fee + $q->other_fee,'USD'):'$0';
            return new TrackingReturnDTO(
                package_id: $q->id,
                code:$q->qr_code,
                receiver_phone: $q->receiver_phone,
                cod_usd: Helper::currencyAmount($q->price,'USD'),
                arrive_date: Helper::dateYMD($q->arrive_warehouse_datetime),
                arrive_time: Helper::time($q->arrive_warehouse_datetime),
                zone_name: $q->zone_name,
                status_id: $q->status_id,
                status_code: TrackingStatus::tryFrom($q->status_id)->label(),
                cod_khr: Helper::currencyAmount($q->price_khr,'KHR'),
                receiver_address: $q->receiver_address,
                image: $q->image_url,
                return_images: $q->return_image_urls,
                driver_name: $q->returnUser?->username,
                driver_phone: $q->returnUser?->phone,
                finished_date: Helper::dateDMY($q->returned_datetime),
                finished_time: Helper::time($q->returned_datetime),
                taxi_fee: $q->taxi_fee,// ($q->taxi_fee > 0 && $q->payer == 'sender') ? Helper::currencyAmount($q->taxi_fee,'USD'):'$0',
                fees: $fees,
                other_fee: $q->other_fee,
                delivery_fee: $q->delivery_fee,
                reason: $q->delivery_remarks,
                remarks: $q->remarks
            );
        };
        return ApiResponse::PaginationV1($query,$req,'',[],200,$callback,$select); 
    }

    public function getAtWarehousePackages(Request $req){
        $user = UserService::getAuthUser();
        $select = [
            'id','arrive_warehouse_datetime','receiver_address','receiver_phone','price','price_khr',
            'status_id','qr_code','delivery_fee','other_fee','returned_uid','zone_name','method',
            'driver_cod_khr','driver_cod_usd','merchant_id','returned_datetime','taxi_fee','delivery_remarks',
            'remarks'
        ];
        $query = $this->getQueryPackages(
            userId: $user->id,
            statusIds: [TrackingStatus::AT_WAREHOUSE->value],
            startDate: $req->query('startDate'),
            endDate: $req->query('endDate'),
            select: $select
        );

        $callback = function ($q):TrackingAtWarehouseDTO{
            $fees = $q->payer == 'sender' ? Helper::currencyAmount($q->delivery_fee + $q->other_fee,'USD'):'$0';
            return new TrackingAtWarehouseDTO(
                package_id: $q->id,
                code:$q->qr_code,
                receiver_phone: $q->receiver_phone,
                cod_usd: Helper::currencyAmount($q->price,'USD'),
                arrive_date: Helper::dateYMD($q->arrive_warehouse_datetime),
                arrive_time: Helper::time($q->arrive_warehouse_datetime),
                zone_name: $q->zone_name,
                status_id: $q->status_id,
                status_code: TrackingStatus::tryFrom($q->status_id)->label(),
                cod_khr: Helper::currencyAmount($q->price_khr,'KHR'),
                receiver_address: $q->receiver_address,
                image: $q->image_url,
                driver_name: $q->returnUser?->username,
                driver_phone: $q->returnUser?->phone,
                other_fee: $q->other_fee,
                delivery_fee: $q->delivery_fee,
                taxi_fee: $q->taxi_fee,//($q->taxi_fee > 0 && $q->payer == 'sender') ? Helper::currencyAmount($q->taxi_fee,'USD'):'$0',
                fees: $fees,
                remarks: $q->remarks
            );
        };
        return ApiResponse::PaginationV1($query,$req,'',[],200,$callback,$select); 
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

    private function merchantCod($driverCodUsd,$driverCodKHR,$fees){
        Helper::deductAmountBase($driverCodUsd,$driverCodKHR,$fees);
        return [
            'cod_usd' => $driverCodUsd,
            'cod_khr' => $driverCodKHR
        ];
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

    public function getBalances(){
        return ApiResponse::JsonResult(new MerchantBalanceDTO(
            availableCOD: new BalanceDTO(
                amount_usd: Helper::amountStdFmtLabel(0,'USD',true),
                amount_khr: Helper::amountStdFmtLabel(0,'KHR',true)
            ),
            cashEarned: new BalanceDTO(
                amount_usd: Helper::amountStdFmtLabel(0,'USD',true),
                amount_khr: Helper::amountStdFmtLabel(0,'KHR',true)
            )
        ));
    }

    public function getConnectWithUs(){
        $user = UserService::getAuthUser('merchant');
        if($user->error){
            $user = (object)[
                'company_id' => 1,
                'account_type' => 'merchant'
            ];
        }
        $socialMedias = SocialMedia::where('is_deleted',0)->selectRaw('id,name,photo_file_name,url')->get();
        $companyInfo = CompanyProfileService::profileInfo((object)[
            'company_id' => 1
        ]);
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
            $package->driver_name = $package->driver?->username;
            $package->total = (float)$package->cod_fee + $package->delivery_fee;
            $rowStatusId = $package->status_id;
            $finished_date = null;
            if($rowStatusId == 6) $finished_date = $package->arrive_warehouse_datetime;
            if($rowStatusId == 11) $finished_date = $package->returned_datetime;
            if($rowStatusId == 19 || $rowStatusId == 10) $finished_date = $package->failed_datetime;
            if($rowStatusId == 9) $finished_date = $package->delivered_datetime;
            $package->finished_datetime = Helper::formatCustomDateTime($finished_date,$this->dateFmt.' h:i A');
            unset($package->driver,$package->status,$package->returnUser);
            return $package;
        };

        return ApiResponse::PaginationV1($qP,$req,'get packages',[],500,$callbackMapper);
    }

    public function searchPackages(Request $req){
        $user = UserService::getAuthUser();
        $select = [
            'id','arrive_warehouse_datetime','receiver_address','receiver_phone','price','price_khr',
            'status_id','qr_code','delivery_fee','extra_charge','driver_id','zone_name','method',
            'driver_cod_khr','driver_cod_usd','merchant_id','delivered_datetime','payer','remarks',
            'failed_datetime','returned_datetime','taxi_fee','delivery_remarks'
        ];

        $search = $req->query('search');
        $startDate = $req->query('startDate')
            ? Carbon::parse($req->query('startDate'))->startOfDay()
            : Carbon::now()->subDays(15)->startOfDay();

        $endDate = $req->query('endDate')
            ? Carbon::parse($req->query('endDate'))->endOfDay()
            : Carbon::now()->endOfDay();

        $query = $this->getQueryPackages(
            userId: $user->id,
            statusIds: [
                TrackingStatus::DELIVERED->value,
                TrackingStatus::RETURNED->value,
                TrackingStatus::ON_DELIVERY->value,
                TrackingStatus::FAILED->value,
                TrackingStatus::FAILED_WITH_FEE->value,
                TrackingStatus::AT_WAREHOUSE->value
            ],
            startDate: $startDate,
            endDate: $endDate,
            search: $search,
            searchKeys: ['receiver_phone','qr_code'],
            select: $select
        );

        if (empty($search)) {
            // force no results
            $query->whereRaw('1 = 0');
        }

        $callback = function ($q): MerchantSearchDTO{
            $fees = $q->payer == 'sender' ? Helper::amountStdFmt($q->delivery_fee + $q->extra_charge,'USD',true):Helper::amountStdFmt(0,'USD',true);
            $receivedAmtUsd = Helper::amountStdFmt(0,'USD',true);
            $receivedAmtKhr = Helper::amountStdFmt(0,'KHR',true);
            $pmtStatus = 'pending';
            $receiverFees = $q->payer == 'receiver' ? ($q->delivery_fee + $q->extra_charge) : 0;
            $totalCollected = PackageTrailServiceImpl::deductRowAmountBase($q->driver_cod_usd ?? 0, $q->driver_cod_khr ?? 0, $receiverFees);
            $receivedAmtUsd = Helper::amountStdFmt($totalCollected['amount_usd'],'USD',true);
            $receivedAmtKhr = Helper::amountStdFmt($totalCollected['amount_khr'],'KHR',true);
            // if($q->hasMerchantPayment()){
            //     if($q->driver_cod_usd > 0 && $q->driver_cod_khr > 0){
            //         $receivedAmtUsd = Helper::amountStdFmt($q->driver_cod_usd,'USD',true);
            //         $receivedAmtKhr = Helper::amountStdFmt($q->driver_cod_khr,'KHR',true);
            //     }else if($q->driver_cod_usd > 0){
            //         $receivedAmtUsd = Helper::amountStdFmt($q->driver_cod_usd,'USD',true);
            //     }else if($q->driver_cod_khr > 0){
            //         $receivedAmtKhr = Helper::amountStdFmt($q->driver_cod_khr,'KHR',true);
            //     }
            //     $pmtStatus = 'Received';
            // }
            $finishedDate = Helper::dateYMD($q->delivered_datetime,'d/m/Y');
            $finishedTime = Helper::time($q->delivered_datetime);
            if($q->status_id == TrackingStatus::FAILED->value || $q->status_id == TrackingStatus::FAILED_WITH_FEE->value){
                $finishedDate = Helper::dateYMD($q->failed_datetime,'d/m/Y');
                $finishedTime = Helper::time($q->failed_datetime);
            }else if($q->status_id == TrackingStatus::RETURNED->value){
                $finishedDate = Helper::dateYMD($q->returned_datetime,'d/m/Y');
                $finishedTime = Helper::time($q->returned_datetime);
            }
            return new MerchantSearchDTO(
                package_id: $q->id,
                code:$q->qr_code,
                receiver_phone: $q->receiver_phone,
                cod_usd: Helper::amountStdFmt($q->price,'USD',true),
                arrive_date: Helper::dateYMD($q->arrive_warehouse_datetime,'d/m/Y'),
                arrive_time: Helper::time($q->arrive_warehouse_datetime),
                zone_name: $q->zone_name,
                status_id: $q->status_id,
                status_code: TrackingStatus::tryFrom($q->status_id)->label(),
                cod_khr: Helper::amountStdFmt($q->price_khr,'KHR',true),
                receiver_address: $q->receiver_address,
                image: $q->image_url,
                remarks: $q->remarks,
                reason: $q->delivery_remarks,
                driver_name: $q->driver->username ?? '', 
                driver_phone: $q->driver->phone ?? '',
                finished_date: $finishedDate,
                finished_time: $finishedTime,
                method: $q->method != 'cod' ? 'Bank':'COD',
                pmt_status: $pmtStatus,
                receiver_amt_usd: $receivedAmtUsd,
                receiver_amt_khr: $receivedAmtKhr,
                taxi_fee: ($q->taxi_fee > 0 && $q->payer == 'sender') ? Helper::amountStdFmt($q->taxi_fee,'USD',true):Helper::amountStdFmt(0,'USD',true),
                fees: $fees,
                submitted_image_urls: $q->submitted_image_urls,
            );
        };
        return ApiResponse::PaginationV1($query,$req,'',[],200,$callback,$select); 
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
            $item->groupKey = date($this->dateFmt,strtotime($item->sent_datetime));
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

    public function getBanners(){
        $banners = Banner::where('is_deleted',false)
        ->where('channel','merchant')
        ->where('is_publish',true)
        ->select('id','photo_file_name','title')
        ->get()->each(function ($q){
            $q->banner_image = Helper::getImageUrl($q->photo_file_name,1,'banner');
        });
        return ApiResponse::JsonResult($banners);
    }

    public function getBannerById(Request $req){
        $id = $req->id;
        $lang = $req->lang;
        $banner = Banner::where('is_deleted',false)
        ->where('channel','merchant')
        ->select('id','cover_file_name','title','title_km','start_date','end_date','description','description_km','contact_link')
        ->find($id);
        if($banner){
            if($lang != 'en'){
                if(!empty($banner->title_km)){
                    $banner->title = $banner->title_km;
                }
                if(!empty($banner->description_km)){
                    $banner->description = $banner->description_km;
                }
            }
            $banner->cover_image = Helper::getImageUrl($banner->cover_file_name,1,'banner');
            $banner->start_date = Helper::dateYMD($banner->start_date,'d M, Y');
            $banner->end_date = Helper::dateYMD($banner->end_date,'d M, Y');
        }
        return ApiResponse::JsonResult($banner);
    }
}
