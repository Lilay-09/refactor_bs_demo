<?php

namespace App\Http\Controllers\V1;

use ApiResponse;
use App\Enums\ImageDirectory;
use App\Http\Controllers\Controller;
use App\Models\DeliveryPackage;
use App\Models\Order;
use App\Models\OrderImage;
use App\Models\Package;
use App\Models\StockLocation;
use App\Models\Tax;
use App\Models\User;
use App\Models\UserShop;
use App\Models\Zone;
use App\Services\GeneralSettingService;
use App\Services\UserService;
use Helper;
use Illuminate\Http\Request;
use Log;

class GeneralSettingController extends Controller
{
    //

    protected $currencyPair = [
        ['name' => 'USD-KHR']
    ];

    protected $gs;

    public function __construct(GeneralSettingService $gs){
        $this->gs = $gs;
    }

    public function getOptionsCountry(Request $req){
        $user = UserService::getAuthUser();
        return ApiResponse::JsonResult($this->gs::optionsCountry($user));
    }

    public function getOptionsDeliveryType(){
        return ApiResponse::JsonResult($this->gs::optionsDeliveryType());
    }

    public function getOptionsCity(Request $req){
        $user = UserService::getAuthUser();
        return ApiResponse::JsonResult($this->gs::optionsCity($user));
    }

    public function getOptionsDailyActiveMerchant(Request $req){
        $user = UserService::getAuthUser();
        return ApiResponse::JsonResult($this->gs::optionsDailyActiveMerchant($user,$req->startDate,$req->endDate));
    }

    public function getOptionsDistrict(Request $req){
        $user = UserService::getAuthUser();
        return ApiResponse::JsonResult($this->gs::optionsDistrict($user,$req->query('city_id')));
    }

    public function getOptionsFeedbackForm(Request $req){
        return ApiResponse::JsonResult($this->gs::optionsFeedbackForm($req->lang));
    }

    public function getOptionsDriverFeedbackForm(Request $req){
        return ApiResponse::JsonResult($this->gs::optionsFeedbackForm($req->lang,[1]));
    }

    public function getOptionsGender(){
        return ApiResponse::JsonResult($this->gs::optionsGender());
    }

    public function getOptionsOperator(Request $req){
        $user = UserService::getAuthUser();
        return ApiResponse::JsonResult($this->gs::optionsOperator($user,$req->branch_id));
    }

    public function getOptionsPayer(Request $req){
        return ApiResponse::JsonResult($this->gs::optionsPayer($req->lang));
    }

    public function getFormZone(){
        $user = UserService::getAuthUser();
        $obj = [
            'zone_types' => $this->gs::optionsZoneType(),
            'countries' => $this->gs::optionsCountry($user)
        ];
        return ApiResponse::JsonResult($obj);
    }

    public function getOptionsRole(Request $req){
        return ApiResponse::JsonResult($this->gs::optionsRole($req->type));
    }

    public function getDriverCommissionFormOptions(Request $req){
        $lang = $req->lang;
        return ApiResponse::JsonResult([
            'employment_types' => $this->gs::optionsEmployeeType(),
            'commission_types' => $this->gs::optionsCommissionType($lang),
        ]);
    }

    public function getAssignZoneFormOptions(Request $req){
        $exceptId = $req->exceptId;
        $pZone = Zone::where('is_deleted',0)
        ->select(['id','city','district','country_id'])
        ->find($exceptId);
        if(!$pZone){
            return ApiResponse::NotFound();
        }

        $qZ = Zone::where('is_deleted',0)->where('id','!=',$exceptId)
        ->where('country_id',$pZone->country_id)
        ->where('city',$pZone->city)
        ->where('district',$pZone->district)
        ->select(['id','zone_name','zone_code']);
        $qZ->whereNotIn('id', function ($query) {
            $query->select('parent_id')
                ->from('zones')
                ->whereNotNull('parent_id'); // Exclude zones that are parents
        });

        return ApiResponse::JsonResult($qZ->get());
    }

    public function getOptionsModule(){
        return ApiResponse::JsonResult($this->gs::optionsModule());
    }

    public function getMerchants(Request $req){
        $search = $req->search;
        $branchId = $req->branch_id;
        $mc = User::where('is_deleted',0)->where('account_type','merchant')->selectRaw('id,code,username,email,phone,pin_address,address');
        if($search){
            $mc->where(function($q) use($search){
                $q->where('code','ilike','%'.$search.'%')
                ->orWhere('username','ilike','%'.$search.'%')
                ->orWhere('phone','ilike','%'.$search.'%');
            });
        }
        if($branchId){
            $mc->where('branch_id',$branchId);
        }
        $merchants = $mc->get();
        return ApiResponse::JsonResult($merchants);
    }

    public function getOptionsPermission(){
        return ApiResponse::JsonResult($this->gs::optionsPermission());
    }

    public function getOptionsFleetPackageTrackingStatus(){
        $user = UserService::getAuthUser();
        return ApiResponse::JsonResult($this->gs::optionsTrackingStatus($user,[],[9,6,10,19]));
    }

    public function getFormOptionsFleetPackageTrackingStatus(Request $req){
        $user = UserService::getAuthUser();
        $tripId = $req->trip_id;
        $dates = DeliveryPackage::from('delivery_packages as dp')
        ->where('dp.delivery_id', $tripId)
        ->join('packages as p', 'dp.package_id', '=', 'p.id')
        ->where('p.status_id', 6)
        ->whereColumn('dp.driver_id', 'p.driver_id') // Ensure driver_id matches
        ->orderBy('p.assign_driver_datetime', 'asc') // Order by earliest first
        ->pluck('p.assign_driver_datetime');
        $firstAssignDate = $dates->first(); // Earliest assign_driver_datetime
        $lastAssignDate = $dates->last();   // Latest assign_driver_datetime

        return ApiResponse::JsonResult([
            'statuses' => $this->gs::optionsTrackingStatus($user,[],[9,6,10,19]),
            'start_time' => Helper::formatCustomDateTime($firstAssignDate,'H:i:s'),
            'end_time' => Helper::formatCustomDateTime($lastAssignDate,'H:i:s')
        ]);

    }

    public function getDriverFilterOptions(){
        $user = UserService::getAuthUser();
        $obj = [
            'statuses' => $this->gs::optionsUserStatus(),
            'employee_types' => $this->gs::optionsEmployeeType(),
            'branches' => $this->gs::optionsBranch()
        ];
        return ApiResponse::JsonResult($obj);
    }

    public function getOptionsFilterUser(){
        $obj = [
            'roles' => $this->gs::optionsRole(),
            'branches' => $this->gs::optionsBranch()
        ];
        return ApiResponse::JsonResult($obj);
    }

    public function getMerchantFilterOptions(){
        $obj = [
            'statuses' => $this->gs::optionsUserStatus(),
            'branches' => $this->gs::optionsBranch()
        ];
        return ApiResponse::JsonResult($obj);
    }

    public function getOptionsMerchantOrder(Request $req){
        return ApiResponse::JsonResult($this->gs::optionMerchantOrder($req->id,$req->startDate,$req->endDate,[5]));
    }


    public function getRewardFormOptions(Request $req){
        $lang = $req->lang;
        return ApiResponse::JsonResult([
            'reward_types' => $this->gs::optionsRewardType($lang),
            'claim_types' => $this->gs::optionsClaimType($lang),
            'units' => $this->gs::optionsUnit($lang),
            'usages' => $this->gs::optionsRewardUsage($lang)
        ]);
    }
    public function getOptionsChannel(){
        return ApiResponse::JsonResult($this->gs::optionChannels());
    }

    public function getOptionsDriverChannel(){
        return ApiResponse::JsonResult($this->gs::optionChannels(0));
    }

    public function getOptionsUnpaidMerchant(){
        // return ApiResponse::JsonResult($this->gs::optionsUnpaidMerchant());
    }

    public function getDriverFormQuestion(Request $req){
        $lang = $req->lang;
        return ApiResponse::JsonResult(
            $this->gs::optionsFeedbackForm($lang,[1])
        );
    }


    public function getFormBanner(){
        return ApiResponse::JsonResult($this->gs::optionChannels(1));
    }

    public function getFormReceivePayment(){
        $user = UserService::getAuthUser();
        $obj = [
            'banks' => $this->gs::optionsBank($user),
            'xrate' => $this->gs::getLatestXRate($user)
        ];
        return ApiResponse::JsonResult($obj);
    }

    public function getOptionsCommune(Request $req){
        $user = UserService::getAuthUser();
        return ApiResponse::JsonResult($this->gs::optionsCommune($user));
    }

    public function getOptionsCityByCountry(Request $req){
        $user = UserService::getAuthUser();
        return ApiResponse::JsonResult($this->gs::optionsCityByCountry($req->country_id,$user));
    }


    public function getOptionsLatestXRate(){
        $user = UserService::getAuthUser();
        return ApiResponse::JsonResult($this->gs::getLatestXRate($user));

    }

    public function getOptionsUserStatus(){
        // $user = UserService::getAuthUser();
        return ApiResponse::JsonResult($this->gs::optionsUserStatus());
    }
    public function getOptionsDistrictByCity(Request $req){
        $user = UserService::getAuthUser();
        return ApiResponse::JsonResult($this->gs::optionsDistrictByCity($req->city_id,$user));
    }

    public function getOptionsCommuneByDistrict(Request $req){
        $user = UserService::getAuthUser();
        return ApiResponse::JsonResult($this->gs::optionsCommuneByDistrict($req->district_id,$user));
    }

    public function getOptionsZone(Request $req){
        $user = UserService::getAuthUser();
        return ApiResponse::JsonResult($this->gs::optionsZone($user,null,null,$req));
    }

    public function getOptionsParentZone(){
        $user = UserService::getAuthUser();
        return ApiResponse::JsonResult($this->gs::optionsZone($user,'parent'));
    }

    public function getOptionsSubZone(Request $req){
        $user = UserService::getAuthUser();
        return ApiResponse::JsonResult($this->gs::optionsZone($user,'child',$req->id));
    }

    public function getOptionsPickupStatus(Request $req){
        $user = UserService::getAuthUser();
        return ApiResponse::JsonResult($this->gs::optionsTrackingStatus($user,[20,21],[],'pick',null,$req->lang));
    }

    public function getFormSetOrderStatus(Request $req){
        $user = UserService::getAuthUser();
        $obj = [
            'statuses' => $this->gs::optionsTrackingStatus($user,[20],[],'pick',null,$req->lang),
            'drivers' => $this->gs::optionsDriver($user,null,$req->warehouse_id)
        ];
        return ApiResponse::JsonResult($obj,'get form set order status');
    }

    public function getOptionsPriceListName(Request $req){
        $user = UserService::getAuthUser();
        return ApiResponse::JsonResult($this->gs::optionsPriceListName($user));
    }

    public function getFormPriceList(){
        $user = UserService::getAuthUser();
        $obj = (object)[
            'zones' => $this->gs::optionsZone($user),
            'delivery_types' => $this->gs::optionsDeliveryType()
        ];
        return ApiResponse::JsonResult($obj);
    }

    public function getFormPackage(Request $req){
        $user = UserService::getAuthUser();
        $lang = $req->lang;
        $obj = (object)[
            'zones' => $this->gs::optionsZone($user,'child',null,$req),
            'delivery_types' => $this->gs::optionsDeliveryType(),
            'cod' => $this->gs::optionsCOD($lang,'string'),
            'payers' => $this->gs::optionsPayer($req->lang),
        ];
        return ApiResponse::JsonResult($obj);
    }

    public function getPriceByZone(Request $req){
        $user = UserService::getAuthUser();
        $merchantId = $req->merchant_id ?? null;
        if(!$merchantId) return ApiResponse::ValidateFail('Merchant ID is required');
        $price = $this->gs::priceByZone($req->zone_id,$user,$merchantId,$req->delivery_type);
        if(!$price) return ApiResponse::NotFound('Price not found');
        return ApiResponse::JsonResult($price,__('get zone price'));
    }

    public function getFormOrder(Request $req){
        $user = UserService::getAuthUser();
        $obj = (object)[
            'delivery_type' => $this->gs::optionsDeliveryType(),
            'merchants' => $this->gs::optionsMerchant(user: $user),
            'statuses' => $this->gs::optionsTrackingStatus($user,[],[1,2,3,4],null,null,$req->lang),
            'warehouses' => $this->gs::optionsWarehouse($user),
            'zones' => $this->gs::optionsZone($user),
            'vehicle_types' => $this->gs::optionsVehicleType($user),
            'drivers' => $this->gs::optionsDriver($user),
            'product_types' => $this->gs::optionsProductType($user),
            'default_addresses' => $this->gs::optionsDefaultAddress(),
            'branches' => $this->gs::optionsBranch()
        ];
        return ApiResponse::JsonResult($obj);
    }

    public function getOptionsBranchType(){
        return ApiResponse::JsonResult($this->gs::optionsBranchType());
    }

    public function getFormWarehouse(){
        return ApiResponse::JsonResult([
            'warehouse_types' =>  $this->gs::optionsWarehouseType(),
            'warehouse_statuses' => $this->gs::optionsWarehouseStatus()
        ]);
    }

    public function getOptionsBranch(){
        return ApiResponse::JsonResult($this->gs::optionsBranch());
    }

    public function getOptionsWarehouseByBranch(Request $req){
        $user = auth()->user();
        return ApiResponse::JsonResult($this->gs::optionsWarehouse($user,$req->branch_id));
    }

    public function getOptionsZoneByPriceListNameId(Request $req){
        $user = UserService::getAuthUser();
        $id = $req->id ?? null;
        return ApiResponse::JsonResult($this->gs::optionsZoneByPriceListNameId($user,$id));
    }

    public function getMerchantLocation(Request $req){
        $mId = $req->id;
        return ApiResponse::JsonResult([
            'product_type_id' => UserShop::where('owner_id',$mId)->take(1)->orderByDesc('id')->value('product_type_id'),
            'locations' => $this->gs::getDefaultMerchantLocation($mId)
        ]);
    }

    public function getFormUser(Request $req){
        return ApiResponse::JsonResult([
            'roles' => $this->gs::optionsRole(),
            'branches' => $this->gs::optionsBranch($req->lang)
        ]);
    }

    public function getFormTransfer(){
        $user = auth()->user();
        return ApiResponse::JsonResult([
            'warehouses' => $this->gs::optionsWarehouse($user),
            'statuses' => $this->gs::optionsTransferStatus(),
            'drivers' => $this->gs::optionsDriverinfo($user),
        ]);
    }

    public function getFormLinkImage(Request $req){
        $user = UserService::getAuthUser();
        $orderId = $req->orderId;
        $obj = (object)[
            'packages' => Package::where('is_deleted',false)
                ->where('order_id',$orderId)
                ->select(['id','qr_code'])
                ->where('company_id',$user->company_id)
                ->orderByDesc('id')
                ->get(),
            'images' => OrderImage::where('is_deleted',0)
                ->where('order_id',$orderId)
                ->where('company_id',$user->company_id)
                ->select(['id','photo_file_name','created_at','package_id'])
                ->orderByDesc('id')
                ->get()->each(function($q){
                    $q->is_link = $q->package_id ? true : false;
                    $q->image = Helper::getImageUrl($q->photo_file_name,auth()->user()->company_id,ImageDirectory::ORDER_IMAGE->value,Helper::dateYMD($q->created_at));
                })
        ];
        return ApiResponse::JsonResult($obj);
    }

    public function optionsEnumVehicleType(){
        return ApiResponse::JsonResult($this->gs::optionsEnumVehicleType());
    }

    public function getFormReceive(){
        $user = auth()->user();
        return ApiResponse::JsonResult([
            'warehouses' => $this->gs::optionsWarehouse($user),
            'statuses' => $this->gs::optionsTransferStatus()
        ]);
    }

    public function getOptionsPackageById(Request $req){
        $pkg = Package::where('is_deleted',false)
        ->with(['merchant:id,username'])
        ->select([
            'id','qr_code','driver_id','receiver_address','receiver_phone','cod','merchant_id',
            'price','remarks','driver_total as total','zone_name','zone_code','delivery_type',
            'other_fee','delivery_fee','payer','additional_fee','taxi_fee','driver_total as total'
        ])
        ->find($req->packageId);
        $pkg->merchant_name = $pkg->merchant->username;
        $pkg->fees = $pkg->other_fee + $pkg->delivery_fee + $pkg->additional_fee;
        $pkg->makeHidden('merchant');
        return ApiResponse::JsonResult($pkg);
    }

    public function getOptionsPackage(Request $req){
        return ApiResponse::JsonResult($this->gs::optionsPackage($req->location_id,[5,10,12]));
    }

    public function getOptionsPackageTransfer(Request $req){
        return ApiResponse::JsonResult($this->gs::optionsPackage($req->location_id,[5,10]));
    }

    public function getOptionsTransferByLocations(Request $req){
        return ApiResponse::JsonResult($this->gs::optionsTransferByLocation($req->from_id,$req->to_id));
    }

    public function getFormPackageTrail(Request $req){
        $user = UserService::getAuthUser();
        $obj = (object)[
            'delivery_type' => $this->gs::optionsDeliveryType(),
            'merchants' => $this->gs::optionsMerchant($user),
            'statuses' => $this->gs::optionsTrackingStatus($user,[],[5,6,10,19],null,null,$req->lang),
            'warehouses' => $this->gs::optionsWarehouse($user),
            'drivers' => $this->gs::optionsDriver($user),
            'zones' => $this->gs::optionsZone($user),
            'branches' => $this->gs::optionsBranch()
        ];
        return ApiResponse::JsonResult($obj);
    }

    public function getMerchantTrxFilter(Request $req){
        $user = UserService::getAuthUser();
        $obj = (object)[
            'statuses' => $this->gs::paymentStatus(),
            'branches' => $this->gs::optionsBranch()
        ];
        return ApiResponse::JsonResult($obj);
    }

    public function getMerchantTransactionTabFilter(Request $req){
        $user = UserService::getAuthUser();
        $obj = (object)[
            'merchants' => $this->gs::optionsMerchant($user),
            'transaction_types' => $this->gs::optionsTransactionType(),
            'branches' => $this->gs::optionsBranch()
        ];
        return ApiResponse::JsonResult($obj);
    }

    public function getOptionsFilterFleet(Request $req){
        $user = UserService::getAuthUser();
        $obj = (object)[
            'statuses' => $this->gs::optionsTrackingStatus($user,[15,17],[],'fleet',null,$req->lang),
            'warehouses' => $this->gs::optionsWarehouse($user),
            'drivers' => $this->gs::optionsDriver($user),
            'zones' => $this->gs::optionsZone($user),
            'branches' => $this->gs::optionsBranch()
        ];
        return ApiResponse::JsonResult($obj);
    }

    public function getFormFleet(Request $req){
        $user = UserService::getAuthUser();
        $obj = (object)[
            'vehicle_types' => $this->gs::optionsVehicleType($user,$req->lang),
            'branches' => $this->gs::optionsBranch()
        ];
        return ApiResponse::JsonResult($obj);
    }

    public function getFormFleetStatus(Request $req){
        $user = UserService::getAuthUser();
        $statusId = $req->status_id;
        $statuses = $this->gs::optionsTrackingStatus($user,[],[9,10,19],null,null,$req->lang);
        return ApiResponse::JsonResult($statuses);
    }

    public function getFormMerchant(Request $req){
        $user = UserService::getAuthUser();
        $lang = $req->lang;
        $obj = (object)[
            'merchant_types' => $this->gs::optionsClientType($user),
            'business_types' => $this->gs::optionsBusinessType($user),
            'cods' => $this->gs::optionsCOD($lang,'string'),
            'genders' => $this->gs::optionsGender(),
            'price_list' => $this->gs::optionsPriceList($user),
            'referrers' => $this->gs::optionsMerchant($user),
            'banks' => $this->gs::optionsBank($user),
            'cities' => $this->gs::optionsCity($user),
            'product_types' => $this->gs::optionsProductType($user),
            'branches' => $this->gs::optionsBranch()
        ];
        return ApiResponse::JsonResult($obj);
    }

    public function getOptionsPriceList(){
        $user = UserService::getAuthUser();
        return ApiResponse::JsonResult($this->gs::optionsPriceList($user));
    }

    public function getFormDriver(){
        $user = UserService::getAuthUser();
        $obj = (object)[
            'employee_types' => $this->gs::optionsEmployeeType(),
            'shitf_types' => $this->gs::optionsShiftType(),
            'genders' => $this->gs::optionsGender(),
            'vehicle_types' => $this->gs::optionsVehicleType($user),
            'warehouses' => $this->gs::optionsWarehouse($user),
            'banks' => $this->gs::optionsBank($user),
            'apply_commissions' => $this->gs::optionsApplyCommission(),
            'branches' => $this->gs::optionsBranch()
        ];
        return ApiResponse::JsonResult($obj);
    }

    public function getOptionsVehicleType(Request $req){
        $user = UserService::getAuthUser();
        return ApiResponse::JsonResult($this->gs::optionsVehicleType($user));
    }
    // public function getOptionsDriverByVehicleType(Request $req){
    //     $user = UserService::getAuthUser();
    //     return ApiResponse::JsonResult($this->gs::optionsDriverByVehicleType($req->vehicle_type,$user));
    // }

    public function getFormPromotion(){
        return ApiResponse::JsonResult($this->gs::optionChannels(1));
    }

    public function getFormRemark(){
        $obj = (object)[
            'channels' => $this->gs::optionChannels(0),
            'categories' => $this->gs::optionsRemarkCategory()
        ];
        return ApiResponse::JsonResult($obj);
    }
    public function getOptionsDriver(Request $req){
        $user = UserService::getAuthUser();
        return ApiResponse::JsonResult($this->gs::optionsDriver($user,$req->vehicle_type));
    }
    public function getOptionsDriverByWarehouse(Request $req){
        $user = UserService::getAuthUser();
        return ApiResponse::JsonResult($this->gs::optionsDriver($user,$req->vehicle_type,$req->warehouseId));
    }

    public function getOptionsCurrencyPair(){
        return ApiResponse::JsonResult($this->gs::optionCurrencyPair());
    }




    public function getFormFinished(Request $req){
        $user = UserService::getAuthUser();
        $obj = (object)[
            'warehouses' => $this->gs::optionsWarehouse($user),
            'merchants' => $this->gs::optionsMerchant($user),
            'drivers' => $this->gs::optionsDriver($user),
            'delivery_types' => $this->gs::optionsDeliveryType(),
            'payment_statuses' => $this->gs::paymentStatus(),
            'branches' => $this->gs::optionsBranch(),
            'statuses' => $this->gs::optionsTrackingStatus($user,[],[9,11,19,23],'delivery',null,$req->lang)
        ];
        return ApiResponse::JsonResult($obj);
    }
    public function getFormUpdateFinishedPackage(Request $req){
        $lang = $req->lang;
        $user = UserService::getAuthUser();
        $obj = (object)[
            'delivery_types' => $this->gs::optionsDeliveryType(),
            'cod' => $this->gs::optionsCOD($lang,'string'),
            'payers' => $this->gs::optionsPayer($req->lang),
            'zones' => $this->gs::optionsZone($user),
        ];
        return ApiResponse::JsonResult($obj);
    }

    // public function getFormUser(){
    //     $user = UserService::getAuthUser();
    //     $obj = (object)[
    //         'roles' => $this->gs::getRoles($user),
    //         'branches' => $this->gs::getBranches($user),
    //     ];
    //     return ApiResponse::JsonResult($obj);
    // }
}
