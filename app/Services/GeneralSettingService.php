<?php

namespace App\Services;
use App\Enums\BranchType;
use App\Enums\Currency;
use App\Enums\PaymentMethod;
use App\Enums\TransactionType;
use App\Enums\TransferStatus;
use App\Enums\WarehouseStatus;
use App\Enums\WarehouseType;
use App\Models\AppModule;
use App\Models\Bank;
use App\Models\Branch;
use App\Models\BusinessType;
use App\Models\City;
use App\Models\ClientType;
use App\Models\Commune;
use App\Models\Country;
use App\Models\DefaultAddress;
use App\Models\DefaultRemark;
use App\Models\Delivery;
use App\Models\DeliveryPackage;
use App\Models\District;
use App\Models\ExchangeRate;
use App\Models\FeedbackForm;
use App\Models\MerchantPriceList;
use App\Models\Order;
use App\Models\Package;
use App\Models\PackageTransfer;
use App\Models\Permission;
use App\Models\PriceList;
use App\Models\PriceListname;
use App\Models\PriceListZone;
use App\Models\ProductType;
use App\Models\Role;
use App\Models\TermCondition;
use App\Models\TrackingStatus;
use App\Models\User;
use App\Models\VehicleType;
use App\Models\Warehouse;
use App\Models\Zone;
use DataResponse;
use Illuminate\Support\Facades\DB;
use Helper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;


class GeneralSettingService
{
    // Your service methods go here
    // protected static $deliveryTypes = [
    //     ['value' => 'fast', 'label' => 'Fast', 'description' => __('messages.fast_desc')],
    //     ['value' => 'normal', 'label' => 'Normal', 'description' => __('messages.normal_desc')],
    // ];

    public static $feeXrate = 4000;


    public static $payerTrans = [
        'sender' => 'អ្នកផ្ញើ',
        'receiver' => 'អ្នកទទួល'
    ];

    public static $pmtStatusTrans = [
        'pending' => 'ចាំការអនុម័ត',
        'unpaid' => 'មិនទាន់ទូរទាត់',
        'paid' => 'បានទូរទាត់',
        'all' => 'ទាំងអស់'
    ];

    public static $statusCodeTrans = [
        0 => 'ទាំងអស់',
        1 => 'កំពុងរង់​ចាំ',//'មិនទាន់មានអ្នកប្រមូល',
        2 => 'បានប្រមូល',
        3 => 'បានទទួល',
        4 => 'ទទួលនិងបញ្ចូល',
        5 => 'ដល់ឃ្លាំង',
        6 => 'កំពុងដឹក',
        9 => 'ជេាគជ័យ',
        10 => 'បរាជ័យ',
        11 => 'កំពុងត្រឡប់',
        19 => 'បរាជ័យគិតសេវា',
        16 => 'រូចរាល់​',
        14 => 'កំពុងដឹក',
    ];

    public static $channels = [
        ['value' => 'driver','label' => 'Driver'],
        ['value' => 'merchant', 'label'=>'Merchant']
    ];



    public static function getRadiusTypeOptions(): array {
        return [
            'flexible' => 'Flexible (Unlimited)',
            'fixed' => 'Fixed (Set distance)',
        ];
    }
    static function optionsClaimType($lang='en'){
        return Helper::translateOptions([
            '1' => ['en' => 'Free'],
            '2' => ['en' => 'Purchase'],
            '3' => ['en' => 'Redeem'],
            '4' => ['en' => 'Event'],
        ],$lang);
    }

    static function optionsRewardUsage($lang='en'){
        return Helper::translateOptions([
            '0' => ['en' => 'Unlimited'],
            '1' => ['en' => '1 Time'],
            '2' => ['en' => '2 Times'],
            '3' => ['en' => '3 Times'],
        ],$lang,'label');
    }

    static function optionsUnit($lang='en'){
        return Helper::translateOptions([
            'coin' => ['en' => 'Coin'],
            'point' => ['en' => 'point'],
            'percent' => ['en' => 'Percent'],
        ],$lang);
    }


    static function optionsRewardType($lang='en'){
        return Helper::translateOptions([
            'cashback' => ['en' => 'Cashback'],
            'promotion' => ['en' => 'Promotions'],
            'referal' => ['en' => 'Referral Rewards'],
            'challenge' => ['en' => 'Challenges']

        ],$lang);
    }

    public static function optionChannels($idx=null){
        if(is_int($idx) && $idx >= 0){
            return isset(self::$channels[$idx]) ? [self::$channels[$idx]] : [];
        }
        return self::$channels;
    }

    public static function optionsRole($type=null){
        $qR = Role::selectRaw('id,name_en as name');
        if($type) $qR->where('group', $type);
        $roles = $qR->limit(1)->orderBy('id')->get();
        return $roles;
    }

    public static function optionsBranch($lang='en'){
        return Branch::where('is_deleted',false)
        ->select(['name_'.$lang.' as name','id'])
        ->get();
    }

    public static function optionsPaymentMethod(){
        return PaymentMethod::optionsMethod();
    }


    public static function optionsCommissionType($lang='en'){
        return Helper::translateOptions([
            // 'percentage' => ['en' => '%','km'=>'%'],
            'amount' => ['en' => '$','km'=>'$'],
        ],$lang);
    }

    public static function getDefaultMerchantLocation($merchatnId){
        return User::where('id',$merchatnId)->where('is_deleted',0)
        ->where('account_type','merchant')
        ->select('address','pin_address','latitude','longitude')
        ->first();
    }

    // public static function optionsRewardType(){
    //     return [
    //         ['value' => 'cashback', 'label' => 'Cashback']
    //     ];
    // }

    public static function optionsModule(){
        return AppModule::selectRaw('id,native_name as name')->orderBy('display_order')->get();
    }

    public static function optionsPermission(){
        return Permission::selectRaw('id,name')->get();
    }

    public static function getWarehouse($user){
        return Warehouse::where('is_deleted',false)
        ->where('company_id',$user->company_id)
        ->where('branch_id',$user->branch_id)->first();
    }

    static function optionsRemarkCategory(){
        return [
            (object)[
                'label' => 'Failure',
                'value' => 'failure'
            ],
            (object)[
                'label' => 'Fail With Fee',
                'value' => 'fail with fee'
            ]
        ];
    }

    static function disclaimerText($text=null){
        return $text ? $text : 'សូមអរគុណនូវការប្រើប្រាស់សេវាកម្មដឹកជញ្ជូន Arrizon របស់ខ្ញុំ។';
    }
    static function optionsGender(){
        return [
            (object)[
                'label' => 'Female',
                'value' => 'F'
            ],
            (object)[
                'label' => 'Male',
                'value' => 'M'
            ]
        ];
    }

    static function optionsUserStatus(){
        return [
            // ['name' => 'All', 'value' => null],
            ['name' => 'Active', 'value' => 1],
            ['name' => 'Inactive', 'value' => 0]
        ];
    }



    static function optionsEmployeeType(){
        return [
            (object)[
                'label' => 'Full-Time',
                'value' => 'full-time'
            ],
            (object)[
                'label' => 'Half-Time',
                'value' => 'half-time'
            ]
        ];
    }
    static function optionsShiftType(){
        return [
            (object)[
                'label' => 'Day Shift',
                'value' => 'day shift'
            ],
            (object)[
                'label' => 'Night Shift',
                'value' => 'night shift'
            ]
        ];
    }

    static function optionsDefaultAddress(){
        return DefaultAddress::where('is_deleted',0)->select('name')->get();
    }

    public static function optionsPriceList($user){
        $pricelist = PriceListname::where('is_deleted',0)->selectRaw( 'id,id as price_list_name_id,name')->get();
        //  PriceList::where('is_deleted', 0)
        //     ->selectRaw('price_list_name_id,delivery_type') // Select only price_list_name_id
        //     ->distinct() // Ensure distinct results
        //     ->with('priceListName')
        //     ->whereHas('priceListName',function($q){
        //         $q->where('is_deleted',0);
        //     }) // Load the relationship
        //     ->get();

        // foreach($pricelist as $pl){
        //     $pl->name = $pl->priceListName?->name;
        //     unset($pl->priceListName);
        // }
        return $pricelist;
    }

    public static function optionsDriverRemarks($category=null,$isFailedWithFee=false){
        if($isFailedWithFee == 'true') $category = 'fail with fee';
        $qR = DefaultRemark::where('channel','driver')->where('is_deleted',0);
        if($category) $qR->where('category',$category);
        $remarks = $qR->where('hidden',0)->selectRaw('id,remarks')->get();
        return $remarks;
    }

    // public static function optionsZone($user,$identity='child',$parentId=null,?Request $filter=null){
    //     $merchantId = $filter->merchant_id ?? null;
    //     $plNameId = MerchantPriceList::where('merchant_id',$merchantId)->take(1)->value('price_list_id');
    //     if($plNameId){
    //         $plIds = PriceList::where('price_list_name_id',$plNameId)
    //         ->pluck('id')->toArray();
    //         if(!empty($plIds)){
    //             $merchantZoneIds = PriceListZone::whereIn('price_list_id',$plIds)->pluck('zone_id')->toArray();
    //         }
    //         Log::info($plNameId);
    //     }
    //     $qZ = Zone::where('status',1)->where('company_id',$user->company_id)->where('is_deleted',false);
    //     if($identity){
    //         $qZ->where('identity',$identity);
    //         // ->whereNotNull('parent_id');
    //     }
    //     if($merchantId){
    //         $qZ->whereIn('id',$merchantZoneIds);
    //     }
    //     if($parentId){
    //         $qZ->where('parent_id',$parentId);
    //     }

    //     $hasChild = $filter->hasChild ?? null;
    //     $exceptId = $filter->exceptId ?? null;
    //     if($hasChild == 'false'){
    //         $qZ->whereNull('parent_id') // Only top-level zones
    //         ->whereNotIn('id', function ($query) {
    //             $query->select('parent_id')
    //                 ->from('zones')
    //                 ->whereNotNull('parent_id'); // Exclude zones that are parents
    //         });
    //     }
    //     if($exceptId){
    //         $qZ->where('id','!=',$exceptId);
    //     }
    //     $zone = $qZ->selectRaw('id,zone_name,identity,zone_code,parent_id')->orderByDesc('id')
    //     ->get()->each(function ($q){
    //         // Log::info($q);
    //     });
    //     return $zone;
    // }

    public static function optionsZone($user,$identity=null,$parentId=null,?Request $filter=null){
        $qZ = Zone::where('status',1)->where('company_id',$user->company_id)->where('is_deleted',0);
        if($identity){
            $qZ->where('identity',$identity);
        }
        if($parentId){
            $qZ->where('parent_id',$parentId);
        }

        $hasChild = $filter->hasChild ?? null;
        $exceptId = $filter->exceptId ?? null;
        if($hasChild == 'false'){
            $qZ->whereNull('parent_id') // Only top-level zones
            ->whereNotIn('id', function ($query) {
                $query->select('parent_id')
                    ->from('zones')
                    ->whereNotNull('parent_id'); // Exclude zones that are parents
            });
        }
        if($exceptId){
            $qZ->where('id','!=',$exceptId);
        }
        // Log::info($qZ->count());
        return $qZ->selectRaw('id,zone_name,identity,zone_code,parent_id')->orderByDesc('id')->get();
    }

    public static function optionsZoneByPriceListNameId($user,$id=null){
        $qZ = Zone::where('status',1)->where('company_id',$user->company_id)->where('is_deleted',0)->selectRaw('id,zone_name,zone_code')->orderByDesc('id');
        if($id){
            $plIds = PriceList::where('is_deleted',0)->where('price_list_name_id',$id)->pluck('id')->toArray();
            $qZ->whereHas('priceListZone', function ($q) use ($plIds) {
                $q->whereIn('price_list_zones.price_list_id', $plIds);
            });
        }
        $zone = $qZ->get();
        return $zone;
    }


    public static function termAndConditions($user){
        return TermCondition::where('channel',$user->account_type)->selectRaw('text')->first();
    }

    public static function optionsBusinessType($user){
        return BusinessType::where('company_id',$user->company_id)
        ->where('is_deleted',0)->selectRaw('id,name_en as name')->get();
    }
    public static function optionsClientType($user){
        return ClientType::where('company_id',$user->company_id)
        ->where('is_deleted',0)
        ->selectRaw('id,name_en as name')->get();
    }

    public static function optionsTrackingStatus($user,$exludeIds=[],$selectIds=[],$stage=null,$selectCols=null,$lang='en'){
        if(!$selectCols) $selectCols = 'id,name';
        $q = TrackingStatus::where('hidden',0)->where('is_deleted',0)
        ->where('company_id',$user->company_id)
        ->selectRaw($selectCols);
        if($stage){
            $q->where('stage',$stage);
        }
        if(isset($exludeIds[0])){
            $q->whereNotIn('id',$exludeIds);
        }
        if(isset($selectIds[0])){
            $q->whereIn('id',$selectIds);
        }
        $statuses = $q->get();
        foreach($statuses as $status){
            if($lang == 'km') $status->name = self::$statusCodeTrans[$status->id] ?? null;
        }
        return $statuses;
    }
    public static function optionsWarehouse($user,$branch_id=null){
        $qW = Warehouse::where('is_deleted',0)->where('company_id',$user->company_id)
        ->select('name_en as name','id')->orderByDesc('id');
        if(!$user->system_admin){
            $qW->where('branch_id',$user->branch_id);
        }
        if($branch_id){
            $qW->where('branch_id',$branch_id);
        }

        return $qW->get();
    }

    public static function optionsTransferStatus(){
        return TransferStatus::optionsTransfer();
    }

    public static function optionsPickupStatus($user){
        return TrackingStatus::where(function($q){
            $q->where('is_deleted',0)->orWhere('hidden',0);
        })->where('stage','pick')->selectRaw('id,name')->orderByDesc('id')->get();
    }

    public static function optionsDriver($user,$vehicleType=null,$warehousId=null){
        $qD = User::where('is_deleted',0)->where('company_id',$user->company_id)->where('account_type','driver')->selectRaw('id,username,phone,name_km');
        if($vehicleType) $qD->where('vehicle_type','ilike',$vehicleType);
        if($warehousId) {
            $qD->where('driver_warehouse_id',$warehousId);
        }
        $drivers = $qD->orderByDesc('id')->get();
        foreach($drivers as $d){
            // $d->username = $d->username . '(' .$d->phone. ')';
            $d->username = $d->username.($d->name_km ? (' - '.$d->name_km):'')." ($d->phone)";
        }
        return $drivers;
    }

    public static function optionsDriverinfo($user,$vehicleType=null){
        $qD = User::where('is_deleted',0)->where('company_id',$user->company_id)
        ->where('account_type','driver')
        ->selectRaw('id,username,phone,name_km,vehicle_type,plate_number');
        if($vehicleType) $qD->where('vehicle_type','ilike',$vehicleType);
        $drivers = $qD->orderByDesc('id')->get();
        return $drivers;
    }

    public static function optionsEnumVehicleType(){
        return \App\Enums\VehicleType::options();
    }


    public static function optionsOperator($user,int $branchId=null){
        $qD = User::where(function($q){
            $q->where('lock',0)->orWhere('is_deleted',0);
        })->where('has_account',true)->where('company_id',$user->company_id)->where('account_type','admin')->selectRaw('id,username,phone');
        if($branchId){
            $qD->where('branch_id',$branchId);
        }
        $drivers = $qD->orderByDesc('id')->get();
        return $drivers;
    }

    static function optionsFeedbackForm($lang='en',$takeIds=[]){
        $nameKey = 'name_'.$lang;
        $q = FeedbackForm::where('is_deleted',0)->select('id',$nameKey)->orderByDesc('id');
        if(!empty($takeIds)){
            $q->whereIn('id',$takeIds);
        }
        return $q->get();
    }

    public static function optionsDriverByVehicleType($vehicle_type,$user){
        return User::where(function($q){
            $q->where('lock',0)->orWhere('is_deleted',0);
        })->where('company_id',$user->company_id)->where('account_type','driver')->selectRaw('id,username,phone')->where('vehicle_type',$vehicle_type)->orderByDesc('id')->get();
    }

    public static function getDriverById($id,$warehouseId=null){
        $qD = User::where('is_deleted',0)->where('delete_account',0)
        ->where('account_type','driver')->orderByDesc('id');
        if($warehouseId){
            $qD->where('warehouse_id',$warehouseId);
        }

        return $qD->find($id);
    }
    public static function getMerchantById($id,$select=['*']){
        return User::where('is_deleted',0)->where('delete_account',0)
        ->select($select)
        ->where('account_type','merchant')->orderByDesc('id')->find($id);
    }

    public static function sumDeliveryFee($baseFee,$extraCharge,$taxi_fee,$payer){
        $total = 0;
        if($payer == 'receiver') {
            $total += $baseFee + $taxi_fee + $extraCharge;
        }
        return $total;
    }

    public static function optionsBank($user){
        return Bank::where('is_deleted',0)->where('company_id',$user->company_id)->selectRaw('id,name,photo_file_name')
        ->get()->map(function ($b){
            $b->image = Helper::getImageUrl($b->photo_file_name,1,'payment_method');
            return $b;
        });
    }

    public static function optionsPaymentBank(){
        return PaymentMethod::optionsBank();
    }

    public static function optionsApplyCommission(){
        return Helper::translateOptions([
            true => [
                'en' => 'Commission-based',
                'km' => 'Commission-based'
            ],
            false => [
                'en' => 'Fixed Salary',
                'km' => 'Fixed Salary'
            ]
        ]);
    }

    public static function optionsTransactionType(){
        return TransactionType::options();
    }

    public static function optionsMerchant($user){
        $merchants = User::where('lock',0)->where('is_deleted',0)
        ->where(function ($q) {
            $q->whereNull('register_status')
            ->orWhere('register_status', '!=', 'in-progress');
        })
        ->where('company_id',$user->company_id)->where('account_type','merchant')->selectRaw('id,username,name_km,phone')->orderByDesc('id')->get();
        foreach($merchants as $m){
            $m->username = $m->username.($m->name_km ? (' - '.$m->name_km):'')." ($m->phone)";
        }
        return $merchants;
    }

    public static function optionsDailyActiveMerchant($user,$startDate=null,$endDate=null,$stage = null,int $branchId = null){
        // Log::error($startDate.'---'.$endDate);
        $query = User::join('packages', 'users.id', '=', 'packages.merchant_id')
        ->where('packages.is_deleted',0)
        ->where('packages.outstanding',0)
        // ->where('users.company_id', $user->company_id)
        ->where('users.account_type', 'merchant')
        ->where('users.is_deleted',0)
        ->selectRaw('DISTINCT users.id, users.username, users.name_km, users.phone,users.photo_file_name')
        ->orderByDesc('users.id');
        if($branchId){
            $query->where('users.branch_id',$branchId);
        }
        if($startDate && $endDate){
            $startDatetime = Helper::dateYMD($startDate).' 00:00:00';
            $endDatetime = Helper::dateYMD($endDate).' 23:59:59';
            $query->where(function ($q) use ($startDatetime, $endDatetime) {
                $q->whereRaw(
                    '(packages.status_id = 5 AND packages.arrive_warehouse_datetime BETWEEN ? AND ?)
                    OR (packages.status_id = 6 AND packages.assign_driver_datetime BETWEEN ? AND ?)
                    OR (packages.status_id = 10 AND packages.failed_datetime BETWEEN ? AND ?)
                    OR (packages.status_id = 19 AND packages.failed_datetime BETWEEN ? AND ?)
                    OR (packages.status_id = 9 AND packages.delivered_datetime BETWEEN ? AND ?)
                    OR (packages.status_id = 11 AND packages.returned_datetime BETWEEN ? AND ?)',
                    [$startDatetime, $endDatetime, $startDatetime, $endDatetime, $startDatetime, $endDatetime, $startDatetime, $endDatetime, $startDatetime, $endDatetime, $startDatetime, $endDatetime]
                );
            });
        }

        $pkgInfo = null;
        if($stage == 'transaction'){
            $clM = clone $query;
            $select = [
                'merchant_id',
                DB::raw("COUNT(*) as package_count")
            ];
            $qP = Package::query()
            ->where('is_deleted', false)
            // ->with(['merchant:id,username'])
            ->whereIn('status_id', [9, 19])
            ->whereIn('merchant_id',$clM->pluck('id'))
            ->select($select)
            ->groupBy(
                'merchant_id',
            );
            // if ($startDate && $endDate) {
            //     $qP->where(function ($q) use ($startDate, $endDate) {
            //         $startDate = Helper::dateYMD($startDate).' 00:00:00';
            //         $endDate = Helper::dateYMD($endDate).' 23:59:59';
            //         $q->where(function ($query) use ($startDate, $endDate) {
            //             $query->where('status_id', 9)
            //                 ->whereBetween('delivered_datetime',[$startDate,$endDate]);
            //         })->orWhere(function ($query) use ($startDate, $endDate) {
            //             $query->where('status_id', 19)
            //             ->whereBetween('failed_datetime',[$startDate,$endDate]);
            //         });
            //     });
            // }
            $pkgInfo = $qP->get()->keyBy('merchant_id');
            // return $pkgInfo;
        }

        $merchants = $query->get();

        // $query = User::where(function($q){
        //     $q->where('lock',0)->orWhere('is_deleted',0);
        // })->where('company_id',$user->company_id)
        // ->where('account_type','merchant')
        // ->selectRaw('id,username,name_km,phone')->orderByDesc('id');

        // if($startDate && $endDate){
        //     $startDatetime = Helper::dateYMD($startDate).' 00:00:00';
        //     $endDatetime = Helper::dateYMD($endDate).' 23:59:59';
        //     $query->whereHas('merchantPackages', function ($q) use($startDatetime, $endDatetime) {
        //         $q->whereRaw(
        //             '(status_id = 5 AND arrive_warehouse_datetime BETWEEN ? AND ?)
        //             OR (status_id = 6 AND assign_driver_datetime BETWEEN ? AND ?)
        //             OR (status_id = 10 AND failed_datetime BETWEEN ? AND ?)
        //             OR (status_id = 19 AND failed_datetime BETWEEN ? AND ?)
        //             OR (status_id = 9 AND delivered_datetime BETWEEN ? AND ?)
        //             OR (status_id = 11 AND returned_datetime BETWEEN ? AND ?)',
        //             [$startDatetime, $endDatetime, $startDatetime, $endDatetime, $startDatetime, $endDatetime, $startDatetime, $endDatetime, $startDatetime, $endDatetime, $startDatetime, $endDatetime]
        //         );
        //     });
        // }

        // $merchants = $query->get();
        foreach($merchants as $m){
            $m->image_url = Helper::getImageUrl($m->photo_file_name,1,'user_profile');
            $m->username = $m->username.($m->name_km ? (' - '.$m->name_km):'')." ($m->phone)";
            if(!empty($pkgInfo)){
                $m->package_count += $pkgInfo[$m->id]?->package_count ?? 0;
            }
        }
        return $merchants;
    }

    public static function optionsVehicleType($user,$lang='en'){

        $userType = $user->account_type;
        $select = 'name_en as name,name_en as value,id';
        if($userType == 'merchant'){
            $select .= ',description_'.$lang.' as description';
            if($lang == 'km'){
                $select .= ',name_km as name';
            }
        }
        $vT = VehicleType::where('company_id',$user->company_id)->where('is_deleted',0)
        ->selectRaw($select)->orderByRaw('id');
        if($user->account_type == 'driver'){
            $vT->where('name_en',$user->info->vehicle_type);
        }
        $vehicleTypes = $vT->get();
        return $vehicleTypes;
    }

    public static function optionsBranchType(){
        return BranchType::options();
    }

    public static function optionsWarehouseType(){
        return WarehouseType::options();
    }
    public static function optionsWarehouseStatus(){
        return WarehouseStatus::options();
    }

    public static function optionsProductType($user){
        return ProductType::where('company_id',$user->company_id)->where('is_deleted',0)
        ->selectRaw('name,id')->orderByDesc('id')->get();
    }

    public static function optionsCityByCountry($countryId,$user){
        return City::where('is_deleted',0)->where('company_id',$user->company_id)
        ->where('country_id',$countryId)
        ->selectRaw('name_en as name,id')->orderByDesc('id')->get();
    }

    public static function optionsZoneType(){
        return [
            [
                'label' => 'Local',
                'value' => 'local',
            ],
            [
                'label' => 'International',
                'value' => 'international',
            ]
        ];
    }

    public static function optionsCountry($user){
        return Country::where('is_deleted',0)->where('company_id',$user->company_id)
        ->selectRaw('name_en as name,id')->orderByDesc('id')->get();
    }

    public static function optionsCity($user){
        return City::where('is_deleted',0)->where('company_id',$user->company_id
        )->selectRaw('name_en as name,id')->orderByDesc('id')->get();
    }
    public static function optionsDistrict($user,$cityId){
        $q = District::where('is_deleted',0)->where('company_id',$user->company_id)
        ->selectRaw('name_en as name,id')
        ->orderByDesc('id');
        if($cityId){
            $q->where('city_id',$cityId);
        }
        return $q->get();

    }

    public static function getGeneralTopics($companyId=null,$channel=null,$userId=null){
        $topic = env('TOPIC_PREFIX');
        $obj = (object)[
            'private' => $companyId.$topic.$channel.'private'.$userId,
            'public' => $companyId.$topic.$channel.'public',
            'general' => $topic.'general'
        ];
        return $obj;
    }

    public static function optionsCommune($user){
        return Commune::where('is_deleted',0)->where('company_id',$user->company_id)
        ->selectRaw('name_en as name,name_en,id')->orderByDesc('id')->get();
    }

    public static function optionsDistrictByCity($cityId,$user){
        return District::where('is_deleted',0)->where('company_id',$user->company_id)->where('city_id',$cityId)
        ->selectRaw('name_en as name,name_en,id')->orderByDesc('id')->get();
    }

    public static function optionsCommuneByDistrict($cityId,$user){
        return Commune::where('is_deleted',0)->where('company_id',$user->company_id)
        ->where('district_id',$cityId)
        ->selectRaw('name_en as name,name_en,id')->orderByDesc('id')->get();
    }

    public static function optionsStatusPackageOnDelivery(){

    }

    // public static function optionsPackage($user,$barcode=null,$statusIds=[]){
    //     $qP = Package::where('is_deleted',0)
    //     ->where('company_id',$user->company_id);
    //     // ->selectRaw('id,qr_code,cod,fee_payer,');
    //     // if($barcode) $qP->where('barcode',$barcode);
    //     $packages = $qP->get();
    //     return $packages;
    // }

    public static function priceByZone($zone_id,$user,$merchant_id=null,$delivery_type='normal'): object|null{
        if(!$delivery_type) $delivery_type = 'normal';
        $priceList = PriceList::with(['zones'])
            ->where('status',1)
            // ->where('company_id',$user->company_id)
            // ->where('is_deleted',0)
            ->whereHas('zones',function($q) use($zone_id){
                $q->where('zone_id',$zone_id);
            })
            ->where('delivery_type',$delivery_type)
            // ->orderByDesc('id')
            ->where('base_fee','>',0)
            ->selectRaw('base_fee,taxi_fee,other_fee,below_kg,below_kg_price,id,price,above_kg_price,above_kg,delivery_type')
            ->first();
            $plZone = PriceListZone::with('priceList:taxi_fee,other_fee,base_fee,below_kg,below_kg_price,id,price,above_kg_price,above_kg,delivery_type')->where('zone_id',$zone_id)->where('price_list_id',$priceList?->id)->first();
            if($merchant_id){
                $plNameId = MerchantPriceList::where('merchant_id',$merchant_id)->take(1)->value('price_list_id');
                if($plNameId){
                    $plIds = PriceList::where('price_list_name_id',$plNameId)
                    ->where('delivery_type',$delivery_type)
                    ->pluck('id')->toArray();
                    if(!empty($plIds)){
                        $plZone = PriceListZone::with('priceList:taxi_fee,other_fee,base_fee,below_kg,below_kg_price,id,price,above_kg_price,above_kg,delivery_type')
                            ->where(function ($query) use ($zone_id, $plIds) {
                                $query->whereIn('price_list_id', $plIds)
                                    ->orWhere('zone_id', $zone_id);
                            })
                            ->first();

                    }
                }
            }
            $row = (object)[];
            if($plZone) {
                $row->base_fee = $plZone->priceList->base_fee;
                $row->price = $plZone->priceList->base_fee;
                $row->above_kg_price = $plZone->priceList->above_kg_price;
                $row->above_kg = $plZone->priceList->above_kg;
                $row->below_kg = $plZone->priceList->below_kg;
                $row->below_kg_price = $plZone->priceList->below_kg_price;
                $row->delivery_type = $plZone->priceList->delivery_type;
                $row->taxi_fee = $plZone->priceList->taxi_fee;
                $row->other_fee = $plZone->priceList->other_fee;
                unset($plZone->zones,$plZone->price,$plZone->priceList);
            }else if($priceList){
                $row->base_fee = $priceList->base_fee;
                $row->price = $priceList->price;
                $row->above_kg_price = $priceList->above_kg_price;
                $row->above_kg = $priceList->above_kg;
                $row->below_kg = $priceList->below_kg;
                $row->delivery_type = $priceList->delivery_type;
                $row->below_kg_price = $priceList->below_kg_price;
                $row->taxi_fee = $priceList->taxi_fee;
                $row->other_fee = $priceList->other_fee;
            }else $row=null;
        return $row;
    }

    public static function optionsCOD($lang='en',$valueType='int'){
        $options = [
            '0' => ['en' => 'No','km' => 'No'],
            '1' => ['en' => 'Yes','km' => 'Yes']
        ];
        return Helper::translateOptions($options,$lang,'label','value',$valueType);
    }

    public static function optionsMerchantType($user){
        return ClientType::where('is_deleted',0)->selectRaw('id,name')->get();
    }


    public function getAvailableTransfer(){
        return PackageTransfer::where('')->get();
    }


    public static function optionsPayer($lang){
            return [
                [
                    'value' => 'sender',
                    'label' => __('messages.sender')
                ],
                [
                    'value' => 'receiver',
                    'label' => __('messages.receiver')
                ]
            ];
    }

    public static function optionsDeliveryType(){
        return [
            ['value' => 'normal', 'label' => __('messages.normal'), 'description' => __('messages.normal_desc')],
            // ['value' => 'fast', 'label' => __('messages.fast'), 'description' => __('messages.fast_desc')]
        ];
    }

    public static function optionsPackage(int $locationId,array $statusIds=[5,6,10,12]){
        return Package::where('is_deleted',false)
        ->whereIn('status_id',$statusIds)
        ->where('warehouse_id',$locationId)
        ->with(['merchant:id,username'])
        ->select([
            'id','qr_code','driver_id','receiver_address','receiver_phone','cod','merchant_id',
            'price','remarks','driver_total as total','zone_name','zone_code'
        ])
        ->get()->each(function ($q){
            $q->merchant_name = $q->merchant->username;
            $q->makeHidden('merchant');
        });
    }

    public static function optionCurrencyPair(){
        return [
            [
                'label' => 'USD-KHR',
                'value' => 'USD-KHR'
            ]
        ];
    }

    public static function optionsTransferByLocation(int $fromLocationId,int $toLocationId){
        return ($fromLocationId && $toLocationId) ? PackageTransfer::where('is_deleted',false)
        ->where('from_location_id',$fromLocationId)
        ->where('to_location_id',$toLocationId)
        ->where('status_id','!=',TransferStatus::DELIVERED->value)
        ->select('id','code')
        ->get():[];
    }

    public static function paymentStatus($lang='en'){
        $rows = [
            [
                'label' => 'All',
                'value' => 0
            ],
            [
                'label' => 'Paid',
                'value' => 2
            ],
            [
                'label' => 'Unpaid',
                'value' => 1
            ]
        ];
        if($lang != 'en'){
            foreach($rows as &$row){
                $lw = strtolower($row['label']);
                $row['label'] = isset(self::$pmtStatusTrans[$lw]) ? self::$pmtStatusTrans[$lw]: $row['label'];
            }
        }
        return $rows;
    }

    public static function optionsCurrency(){
        return Currency::options();
    }
    public static function optionsPriceListName($user){
        return PriceListname::where('company_id',$user->company_id)->where('is_deleted',0)->orderByDesc('id')->selectRaw('id,name,kg_marker')->get();
    }

    // public static function getZonePriceByCode($zone_code,$user){
    //     // $user = UserService::getAuthUser();
    //     return PriceList::with('zones')->where('is_deleted',0)
    //     ->where('status',1)
    //     ->where('company_id',$user->company_id)
    //     ->whereHas('zones',function ($q) use ($zone_code){
    //         $q->where('zone_code',$zone_code);
    //     })
    //     ->first();
    // }

    public static function concatBankInfo($bankName,$bankNumber,$accountName){
        $info = $bankName;
        if($bankNumber) $info .= '|'.$bankNumber;
        if($accountName) $info .= '|'.$accountName;
        return $info;
    }

    public static function calculatePackageFee($zone_code,$price,$billedKg,$actualKg,$payer,$cod,$extraCharge,$user,$taxi_fee=0,$merchant_id=null,$status_id=null,$otherFee=0){
        // $priceList = GeneralSettingService::getZonePriceByCode($zone_code,$user);
        $zoneId = Zone::where('zone_code',$zone_code)->where('is_deleted',0)->take(1)->value('id');
        $priceList = GeneralSettingService::priceByZone($zoneId,$user,$merchant_id);
        if(!$priceList) return DataResponse::NotFound('Zone price not found');
        $baseFee = $priceList->price > 0 ? $priceList->price : $priceList->base_fee;
        if($baseFee <=0) return DataResponse::NotFound('Please set price to your zone');
        $zPrice = $baseFee + $extraCharge;
        $selectKg = $billedKg ?? $actualKg;
        $additionalPrice = 0;
        $merchant_total = $zPrice;
        if(!empty($selectKg) && $selectKg > 0){
            if($selectKg >= $priceList->above_kg){
                $additionalPrice = $priceList->above_kg_price;
            }else if($selectKg < $priceList->above_kg && $selectKg >= $priceList->below_kg){
                $additionalPrice = $priceList->below_kg_price;
            }
        }
        $driverTotal = 0;
        if($cod) $driverTotal += $price;
        if($status_id == 19) $driverTotal = 0;
        $merchant_total += $additionalPrice;
        $total = $price + $additionalPrice;
        if($payer == 'receiver'){
            $total += $zPrice;
            $merchant_total = 0;
            $driverTotal += $zPrice;
            $driverTotal += $otherFee;
            // Log::info('other fee: '.$otherFee);
        }
        $driverTotal -= $taxi_fee;
        return (object)[
            "error" => false,
            'message' => 'Success',
            'delivery_fee' => $baseFee,
            'driver_total' => Helper::getNumber($driverTotal,2),
            'merchant_total' => Helper::getNumber($merchant_total,2),
            'total' => Helper::getNumber($total,2)
        ];
    }

    public static function getLatestXRate($user=null){
        $today = now();
        $xRate = ExchangeRate::where('is_deleted', 0)
        ->orderByRaw('ABS(DATE_PART(\'day\', x_date::timestamp - ?::timestamp)) ASC', [$today]) // Closest date
        ->orderByDesc('x_date') // Resolve ties by picking the latest
        ->selectRaw('buy_rate, sell_rate')
        ->first();
        if(!$xRate) $xRate = (object)[
            'buy_rate' => 4000,
            'sell_rate' => 4000
        ];
        return $xRate;
    }

    public static function optionMerchantOrder($merchant_id,$startDate,$endDate,$statusIds=[]){
        if(!$merchant_id) return [];
        $startDate = $startDate? Helper::dateYMD($startDate):null;
        $endDate = $endDate? Helper::dateYMD($endDate):null;
        $qO = Order::where('is_deleted',0)
        ->where('merchant_id',$merchant_id)
        ->selectRaw('code,id,qty,order_datetime,is_deleted')
        ->take(600)
        ->orderByDesc('order_datetime');
        if(!empty($statusIds)){
            $qO->whereIn('status_id',$statusIds);
        }
        if($startDate && $endDate){
            $qO->whereRaw('order_datetime >= ? AND order_datetime <= ?', [$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
        }
        $orders = $qO->get();
        foreach($orders as $order){
            $order->order_ref = $order->code.' | '.Helper::formatCustomDateTime($order->order_datetime);
        }

        return $orders;
    }

    public static function updateTripStatus($id,$user): void{
        $trip = Delivery::where('is_deleted',0)->where('company_id',$user->company_id)->find($id);
        if($trip){
            $queryDeliveryPackage = DeliveryPackage::where('delivery_id',$id)
            ->whereHas('package',function($q) use($user){
                $q->whereIn('status_id',[6,9,10,19]);
            })
            ->whereIn('status_id',[6,9,10,19])
            ->with(['package:id,qr_code,is_deleted,status_id,driver_id'])
            ->where('has_swap',0)
            ->where('is_deleted',0)
            ->where('delay_count',0)
            ->orderByDesc('id');
            // ->where(function ($q){
            //     $q->where('is_deleted',0)->orWhere('delay_count',0);
            // });
            $deliveredCount = 0;
            $isCompleted = 1;
            $failCount = 0;
            $OnDeliveryCount = 0;
            $stillOnDelivery = 0;
            $doneTrip = \App\Enums\TrackingStatus::DONE_TRIP->value;
            $status_id = $doneTrip;
            $packages = $queryDeliveryPackage->get();
            foreach($packages as $pck){
                if($pck->status_id == 9){
                    $deliveredCount += 1;
                }else if($pck->status_id == 10 || $pck->status_id == 19){
                    $failCount += 1;
                }
                if($pck->status_id == 6 && $pck->has_swap == false){
                    $stillOnDelivery = 6;
                    $OnDeliveryCount += 1;
                    // Log::error('test');
                }
            }
            if($trip->package_count == $failCount){
                $status_id = $doneTrip;
                $isCompleted = 1;
            }else if($trip->package_count == $deliveredCount && $OnDeliveryCount == 0){
                $status_id = $doneTrip;
                $isCompleted = 1;
            }else
            if($trip->package_count >= $deliveredCount){
                $status_id = $doneTrip;
                if($stillOnDelivery) {
                    $status_id = \App\Enums\TrackingStatus::ON_DELIVERY_TRIP->value;
                    $isCompleted = 0;
                }
            }
            // else if($trip->package_count == ($failCount + $deliveredCount)) $isCompleted = 1;
            $updateArr = [
                'is_completed' => $isCompleted,
                'finished' => $isCompleted,
                'update_uid' => $user->id,
                'failed_count' => $failCount,
                'status_id' => $status_id,
                'delivered_count' => $deliveredCount,
                'package_count' => $failCount + $deliveredCount + $OnDeliveryCount,
            ];
            if($isCompleted) {
                $updateArr['finished_datetime'] = now();
                $updateArr['finished_uid'] = $user->id;
            }
            // Log::info($updateArr);
            $trip->update($updateArr);
        }
    }

    public static function optionsUnpaidUser($userClass){
        $mP = Package::with($userClass)->get();
        // ->whereNotExists(function ($sub) {
        //         $sub->select(DB::raw(1))
        //             ->from('payment_packages as pp')
        //             ->whereColumn('pp.package_id', 'p.id')
        //             ->where('pp.payer_type', $userClass)
        //             ->where('pp.is_deleted', false);
        //     })
        //     ->whereNotExists(function ($sub) {
        //         $sub->select(DB::raw(1))
        //             ->from('disbursement_packages as dp')
        //             ->whereColumn('dp.package_id', 'p.id')
        //             ->where('dp.payee_type', $userClass)
        //             ->where('dp.is_deleted', false);
        //     });
        return $mP;
    }

}
