<?php

namespace App\Services;
use App\Models\AppModule;
use App\Models\Bank;
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
use Helper;
use Log;
// use Log;


class GeneralSettingService
{
    // Your service methods go here
    protected static $deliveryTypes = [
        ['value' => 'fast','label' => 'Fast'],
        ['value' => 'normal','label' => 'Normal'],
    ];

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
        1 => 'មិនទាន់មានអ្នកប្រមូល',
        2 => 'បានប្រមូល',
        3 => 'ទទួលការប្រមូល',
        4 => 'បានប្រមូលនិងបញ្ចូល',
        5 => 'ដល់ឃ្លាំង',
        6 => 'កំពុងដឹក',
        9 => 'ជេាគជ័យ',
        10 => 'បរាជ័យ',
        11 => 'ត្រឡប់ទៅហាង',
        19 => 'បរាជ័យគិតសេវា',
        16 => 'រូចរាល់​',
        14 => 'កំពុងដឹក'
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
        $qR = Role::selectRaw('id,name');
        if($type) $qR->where('group', $type);
        $roles = $qR->get();
        return $roles;
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
        return Warehouse::where('company_id',$user->company_id)->first();
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

    public static function optionsZone($user){
        return Zone::where('status',1)->where('company_id',$user->company_id)->where('is_deleted',0)->selectRaw('id,zone_name,zone_code')->orderByDesc('id')->get();
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
        return BusinessType::where('company_id',$user->company_id)->where('is_deleted',0)->selectRaw('id,name')->get();
    }
    public static function optionsClientType($user){
        return ClientType::where('company_id',$user->company_id)->where('is_deleted',0)->selectRaw('id,name')->get();
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
    public static function optionsWarehouse($user){
        return Warehouse::where('is_deleted',0)->where('company_id',$user->company_id)->selectRaw('name,id')->orderByDesc('id')->get();
    }

    public static function optionsPickupStatus($user){
        return TrackingStatus::where(function($q){
            $q->where('is_deleted',0)->orWhere('hidden',0);
        })->where('stage','pick')->selectRaw('id,name')->orderByDesc('id')->get();
    }

    public static function optionsDriver($user,$vehicleType=null){
        $qD = User::where('is_deleted',0)->where('company_id',$user->company_id)->where('account_type','driver')->selectRaw('id,user_name,phone,name_km');
        if($vehicleType) $qD->where('vehicle_type','ilike',$vehicleType);
        $drivers = $qD->orderByDesc('id')->get();
        foreach($drivers as $d){
            // $d->user_name = $d->user_name . '(' .$d->phone. ')';
            $d->user_name = $d->user_name.($d->name_km ? (' - '.$d->name_km):'')." ($d->phone)";
        }
        return $drivers;
    }

    public static function optionsOperator($user){
        $qD = User::where(function($q){
            $q->where('lock',0)->orWhere('is_deleted',0);
        })->where('has_account',true)->where('company_id',$user->company_id)->where('account_type','admin')->selectRaw('id,user_name,phone');
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
        })->where('company_id',$user->company_id)->where('account_type','driver')->selectRaw('id,user_name,phone')->where('vehicle_type',$vehicle_type)->orderByDesc('id')->get();
    }

    public static function getDriverById($id){
        return User::where('is_deleted',0)->where('delete_account',0)->where('account_type','driver')->orderByDesc('id')->find($id);
    }
    public static function getMerchantById($id){
        return User::where('is_deleted',0)->where('delete_account',0)->where('account_type','merchant')->orderByDesc('id')->find($id);
    }

    public static function sumDeliveryFee($baseFee,$extraCharge,$taxi_fee,$payer){
        $total = 0;
        if($payer == 'receiver') {
            $total += $baseFee + $taxi_fee + $extraCharge;
        }
        return $total;
    }

    public static function optionsBank($user){
        return Bank::where('is_deleted',0)->where('company_id',$user->company_id)->selectRaw('id,name')->get();
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
        return [
            [
                'name' => 'Transfer Out',
                'value' => 'disbursement',
            ],
            [
                'name' => 'Transfer In',
                'value' => 'receive'
            ]
        ];
    }

    public static function optionsMerchant($user){
        $merchants = User::where('lock',0)->where('is_deleted',0)
        ->where(function ($q) {
            $q->whereNull('register_status')
            ->orWhere('register_status', '!=', 'in-progress');
        })
        ->where('company_id',$user->company_id)->where('account_type','merchant')->selectRaw('id,user_name,name_km,phone')->orderByDesc('id')->get();
        foreach($merchants as $m){
            $m->user_name = $m->user_name.($m->name_km ? (' - '.$m->name_km):'')." ($m->phone)";
        }
        return $merchants;
    }

    public static function optionsDailyActiveMerchant($user,$startDate=null,$endDate=null){
        // Log::error($startDate.'---'.$endDate);
        $query = User::join('packages', 'users.id', '=', 'packages.merchant_id')
        ->where('packages.is_deleted',0)
        ->where('packages.outstanding',0)
        ->where('users.company_id', $user->company_id)
        ->where('users.account_type', 'merchant')
        ->where('users.is_deleted',0)
        ->selectRaw('DISTINCT users.id, users.user_name, users.name_km, users.phone')
        ->orderByDesc('users.id');
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
        $merchants = $query->get();

        // $query = User::where(function($q){
        //     $q->where('lock',0)->orWhere('is_deleted',0);
        // })->where('company_id',$user->company_id)
        // ->where('account_type','merchant')
        // ->selectRaw('id,user_name,name_km,phone')->orderByDesc('id');

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
            $m->user_name = $m->user_name.($m->name_km ? (' - '.$m->name_km):'')." ($m->phone)";
        }
        return $merchants;
    }

    public static function optionsVehicleType($user){

        $vT = VehicleType::where('company_id',$user->company_id)->where('is_deleted',0)
        ->selectRaw('name,name as value,id')->orderByDesc('id');
        if($user->account_type == 'driver'){
            $vT->where('name',$user->info->vehicle_type);
        }
        $vehicleTypes = $vT->get();
        return $vehicleTypes;
    }

    public static function optionsProductType($user){
        return ProductType::where('company_id',$user->company_id)->where('is_deleted',0)
        ->selectRaw('name,id')->orderByDesc('id')->get();
    }

    public static function optionsCityByCountry($countryId,$user){
        return City::where('is_deleted',0)->where('company_id',$user->company_id)->where('country_id',$countryId)->selectRaw('name,id')->orderByDesc('id')->get();
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
        return Country::where('is_deleted',0)->where('company_id',$user->company_id)->selectRaw('name,id')->orderByDesc('id')->get();
    }

    public static function optionsCity($user){
        return City::where('is_deleted',0)->where('company_id',$user->company_id)->selectRaw('name,id')->orderByDesc('id')->get();
    }
    public static function optionsDistrict($user){
        return District::where('is_deleted',0)->where('company_id',$user->company_id)->selectRaw('name,id')->orderByDesc('id')->get();
    }

    public static function getGeneralTopics($companyId=null,$channel=null,$userId=null){
        $topic = env('TOPIC_PREFIX');
        $obj = (object)[
            'private' => $companyId.$topic.$channel.'private'.$userId,
            'public' => $companyId.$topic.$channel.'public'
        ];
        return $obj;
    }

    public static function optionsCommune($user){
        return Commune::where('is_deleted',0)->where('company_id',$user->company_id)->selectRaw('name,id')->orderByDesc('id')->get();
    }



    public static function optionsDistrictByCity($cityId,$user){
        return District::where('is_deleted',0)->where('company_id',$user->company_id)->where('city_id',$cityId)->selectRaw('name,id')->orderByDesc('id')->get();
    }

    public static function optionsCommuneByDistrict($cityId,$user){
        return Commune::where('is_deleted',0)->where('company_id',$user->company_id)->where('district_id',$cityId)->selectRaw('name,id')->orderByDesc('id')->get();
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

    public static function priceByZone($zone_id,$user,$merchant_id=null): object|null{
        $priceList = PriceList::with(['zones'])
            ->where('status',1)
            // ->where('company_id',$user->company_id)
            // ->where('is_deleted',0)
            ->whereHas('zones',function($q) use($zone_id){
                $q->where('zone_id',$zone_id);
            })
            // ->orderByDesc('id')
            ->where('base_fee','>',0)
            ->selectRaw('base_fee,id,price')
            ->first();
            // Log::info('$plid' .$priceListId.'Zid'.$zone_id);
            $plZone = PriceListZone::with('priceList:id,base_fee')->where('zone_id',$zone_id)->where('price_list_id',$priceList?->id)->first();
            if($merchant_id){
                // Log::info('merchant');
                $plNameId = MerchantPriceList::where('merchant_id',$merchant_id)->take(1)->value('price_list_id');
                if($plNameId){
                    // Log::info(' sdfsdfsd');
                    $plIds = PriceList::where('price_list_name_id',$plNameId)->pluck('id')->toArray();
                    if(!empty($plIds)){
                        $plZone = PriceListZone::with('priceList:id,base_fee')->where('zone_id',$zone_id)->whereIn('price_list_id',$plIds)->first();
                        // Log::info($row);
                    }
                }
            }
            $row = (object)[];
            if($plZone) {
                // Log::info($plZone);
                $row->base_fee = $plZone->priceList->base_fee;
                $row->price = $plZone->priceList->base_fee;
                unset($plZone->zones,$plZone->price,$plZone->priceList);
            }else if($priceList){
                $row->base_fee = $priceList->base_fee;
                $row->price = $priceList->price;
            }else $row=null;
        return $row;
    }

    public static function optionsCOD(){
        return [
            [
                'value' => 0,
                'label' => 'No'
            ],
            [
                'value' => 1,
                'label' => 'Yes'
            ],
        ];
    }

    public static function optionsMerchantType($user){
        return ClientType::where('is_deleted',0)->selectRaw('id,name')->get();
    }

    public static function optionsPayer($lang){
        if($lang == 'km') {
            return [
                [
                    'value' => 'sender',
                    'label' => 'អ្នកផ្ញើ'
                ],
                [
                    'value' => 'receiver',
                    'label' => 'អ្នកទទួល'
                ],
            ];
        }
        return [
            [
                'value' => 'sender',
                'label' => 'Sender'
            ],
            [
                'value' => 'receiver',
                'label' => 'Receiver'
            ],
        ];
    }

    public static function optionsDeliveryType(){
        return self::$deliveryTypes;
    }

    public static function optionCurrencyPair(){
        return [
            [
                'label' => 'USD-KHR',
                'value' => 'USD-KHR'
            ]
        ];
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

    public static function calculatePackageFee($zone_code,$price,$billedKg,$actualKg,$payer,$cod,$extraCharge,$user,$taxi_fee=0,$merchant_id=null,$status_id=null){
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
            ->where('has_swap',0)
            ->where('is_deleted',0)
            ->where('delay_count',0);
            // ->where(function ($q){
            //     $q->where('is_deleted',0)->orWhere('delay_count',0);
            // });
            $deliveredCount = 0;
            $isCompleted = 1;
            $failCount = 0;
            $OnDeliveryCount = 0;
            $stillOnDelivery = 0;
            $status_id = 16;
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
                $status_id = 16;
                $isCompleted = 1;
            }else if($trip->package_count == $deliveredCount){
                $status_id = 16;
                $isCompleted = 1;
            }else
            if($trip->package_count >= $deliveredCount){
                $status_id = 16;
                if($stillOnDelivery) {
                    $status_id = 14;
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
            // Log::info($status_id);
            if($isCompleted) {
                $updateArr['finished_datetime'] = now();
                $updateArr['finished_uid'] = $user->id;
                // Log::error($isCompleted);
            }
            // Delivery::find($id)->update($updateArr);
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
