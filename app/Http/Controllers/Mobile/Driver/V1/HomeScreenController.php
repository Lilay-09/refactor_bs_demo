<?php

namespace App\Http\Controllers\Mobile\Driver\V1;

use ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Mobile\V1\GeneralSettingController;
use App\Http\Controllers\V1\DriverTransactionController;
use App\Models\Delivery;
use App\Models\DeliveryPackage;
use App\Models\Disbursement;
use App\Models\DriverCommission;
use App\Models\Notification;
use App\Models\Order;
use App\Models\OrderImage;
use App\Models\Package;
use App\Models\Payment;
use App\Services\CloudMessagingService;
use App\Services\GeneralSettingService;
use App\Services\PickupCenterService;
use App\Services\UserService;
use Helper;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class HomeScreenController extends Controller
{
    //
    protected $user;
    public function __construct(){
        $this->user = UserService::getAuthUser('driver');
    }
    public function getAvailableOrders(Request $req){
        $user = UserService::getAuthUser('driver');
        $orders = Order::where('is_deleted',0)
        ->with(['merchant','warehouse'])
        ->where('status_id',1)
        ->where('company_id',$user->company_id)
        ->orderByDesc('id')
        ->selectRaw('id,order_datetime,merchant_id,warehouse_id,qty,code,pickup_address,pickup_address_google_map,vehicle_type,delivery_type')
        ->get();
        foreach($orders as $order){
            $order->merchant_name = $order->merchant->user_name;
            $order->merchant_code = $order->merchant->code;
            $order->merchant_phone = $order->merchant->phone;
            $order->warehouse_address = $order->warehouse->address;
            unset($order->merchant,$order->warehouse);
        }
        return ApiResponse::Pagination($orders,$req);
    }

    public function getAcceptedPickup(Request $req){
        $user = $this->user;
        if($user->error) return ApiResponse::flex($user);
        $orders = Order::where('is_deleted',0)
        ->with(['merchant','tracking_status','warehouse'])
        ->whereIn('status_id',[2,3,4])
        ->where('company_id',$user->company_id)
        ->where('driver_id',$user->id)
        ->orderByRaw('status_id = ? desc',[3])
        ->orderByDesc('id')
        ->selectRaw('id,warehouse_id,driver_id,pickup_address_google_map,order_datetime,merchant_id,status_id,qty,code,pickup_address,pickup_address_google_map,vehicle_type,delivery_type,loc_lat,loc_lng,product_type')
        ->get();
        foreach($orders as $order){
            $order->warehouse_address = $order->warehouse->address;
            $order->status_code = $order->tracking_status->name;
            $order->merchant_name = $order->merchant->user_name;
            $order->merchant_phone = $order->merchant->phone;
            // $latLng = Helper::getLatLongFromGoogleMapsUrl($order->pickup_address_google_map);
            $order->latitude = $order->loc_lat ;//? $order->loc_lat : 11.552692;//;
            $order->longitude = $order->loc_lng ;// ? $order->loc_lng : 104.901413;//$order->loc_lng;

            unset($order->merchant,$order->tracking_status,$order->warehouse);
        }
        return ApiResponse::Pagination($orders,$req);
    }

    public function getDriverBalance(Request $req){
        $user = UserService::getAuthUser('driver');
        // $totalEarning = (float)Disbursement::where('payee_id',$user->id)->where('type','commission')->where('is_deleted',0)->sum('payable_amount');
        $qP = Package::selectRaw('status_id,driver_id')
        ->whereIn('status_id',[9,19])
        ->where('is_deleted',0)
        ->whereNull('driver_commission_id')
        ->where('driver_id',$user->id);
        $packages = $qP->get();
        $qO = Order::where('is_deleted',0)->whereNull('driver_commission_id')->where('status_id',5)
        ->where('driver_id',$user->id);
        $orders = $qO->get();
        // $totalSettledPayment = Payment::where('payer_id',$user->id)->where('is_settled',1)->where('is_deleted',0)->sum('payable_amount');
        $driverCommissions = DriverCommission::where('driver_id',$user->id)->where('is_deleted',0)
        ->selectRaw('id,driver_id,delivery_type,pickup_commission,delivery_commission')
        ->get();

        $commissionInfo = DriverTransactionController::getDriverCommissionInfo($driverCommissions,$user->id);
        $dtc = new DriverTransactionController();
        $totalPickUpPackage = $dtc->getPickUpDetails($orders,$user->id)->total_package;
        $totalDeliveredPackage = $dtc->getDeliveredDetails($packages,$user->id)->delivered_count;
        $pickup_rate = $commissionInfo->normal_pickup_commission;
        $delivery_rate = $commissionInfo->normal_delivery_commission;
        $totalEarning = (float)number_format($pickup_rate * $totalPickUpPackage + $delivery_rate * $totalDeliveredPackage,2);
        $balanceDue = Package::where('packages.driver_id', $user->id)
        ->where('packages.is_deleted', 0)
        ->whereIn('packages.status_id', [9, 19])
        ->whereNull('packages.driver_disbursement_id')
        ->leftJoin('payments', 'packages.driver_payment_id', '=', 'payments.id')
        ->where(function ($query) {
            $query->whereNull('payments.id') // Include rows without matching payments
                ->orWhere('payments.approved', 0); // Include rows where payments.approved = 0
        })
        ->sum('packages.driver_total');
        // $totalSettledDisburment = Disbursement::where('payee_id',$user->id)->where('type','payment')->where('is_deleted',0)->where('is_settled',1)->sum('payable_amount');
        $obj = [
            'earning' => $totalEarning,
            // 'settlement' => (float)$totalSettledPayment,
            'settlement' => (float)$balanceDue
        ];
        return ApiResponse::JsonResult($obj);
    }

    public function getOneAcceptedPickup(Request $req){
        $user = $this->user;
        $orderId = $req->order_id;
        if($user->error) return ApiResponse::flex($user);
        $orders = Order::where('is_deleted',0)
        // ->with(['merchant','tracking_status','warehouse'])
        ->whereIn('status_id',[2,3,4])
        ->where('company_id',$user->company_id)
        ->where('driver_id',$user->id)
        ->selectRaw('id,warehouse_id,driver_id,pickup_address_google_map,order_datetime,merchant_id,status_id,qty,code,pickup_address,pickup_address_google_map,vehicle_type,delivery_type')
        ->find($orderId);
        return ApiResponse::JsonResult($orders);
    }

    public function getDelivery(Request $req){
        $user = $this->user;
        if($user->error) return ApiResponse::flex($user);
        $driverId = $user->id;
        $packages = $this->tripPackageInfo();
        $fleets = Delivery::where('driver_id', $driverId)->where('is_deleted',0)
        ->with(['status'])
        ->where(function ($q){
            $q->where('finished',0)->orWhereDate('depart_datetime',Carbon::today());
        })
        ->selectRaw('id,status_id,package_count,delivered_count,fleet_tracking_number,depart_datetime,driver_id')->orderByDesc('id')->get();
        foreach($fleets as $fleet){
            $fleet->status_code = $fleet->status->name;
            $fleet->total = $this->getTripTotal($packages,$fleet->id);
            unset($fleet->status);
        }
        // $orders = Order::fromRaw('orders as o')->join('packages as p','p.order_id','o.id')->where('o.is_deleted',0)
        // ->join('users as m','m.id','o.merchant_id')
        // // ->join('tracking_statuses as ts','ts.id','o.status_id')
        // // ->where('is_completed',0)
        // // ->with(['packages:id,cod,price,delivery_fee,payer,zone_code,zone_name,receiver_phone,delivery_type,status_id,order_id,arrive_warehouse_datetime','packages.status'])
        // ->selectRaw('o.qty,o.order_datetime,o.id as order_id,o.id,o.code,m.user_name,m.phone')
        // ->groupByRaw('m.phone,o.id,o.code,m.user_name')
        // ->orderByDesc('o.id')
        // ->where('p.driver_id',$driverId)->get();
        return ApiResponse::Pagination($fleets,$req);
    }

    private function getTripTotal($packages,$tripId){
        $total = 0;
        foreach ($packages as $key => $p) {
            if($p->delivery_id == $tripId) $total += $p->driver_total;
        }
        return '$'.$total;
    }

    private function tripPackageInfo($tripId=null,$driverId = null){
        $qP = Package::fromRaw('packages as p')->join('delivery_packages as dp','p.id','dp.package_id')
        ->join('users as d','d.id','p.driver_id')
        ->leftJoin('users as m','m.id','p.merchant_id')
        ->where(function($q){
            $q->where('dp.is_deleted',0)->orWhere('dp.delay_count',0);
        })
        ->where('p.created_at', '>=', Carbon::now()->subDays(15))
        ->join('tracking_statuses as ts','ts.id','dp.status_id')
        ->selectRaw('p.receiver_address,p.extra_charge,p.id,p.delivered_datetime,p.failed_datetime,p.assign_driver_datetime,p.merchant_id,p.qr_code,p.price,p.cod,p.receiver_name,p.receiver_phone,p.zone_code,p.zone_name,ts.name as status_code,d.user_name as driver_name,d.phone as driver_phone,m.user_name as merchant_name,m.phone as merchant_phone,p.id as package_id,dp.delivery_id,p.zone_code,p.zone_name,p.delivery_fee as base_fee,p.driver_total,p.taxi_fee,p.product_type,dp.status_id')
        ->orderByRaw('(dp.status_id = ?) DESC', [6]);
        if($driverId){
            $qP->where('p.driver_id',$driverId);
        }
        if($tripId){
            $qP->where('dp.delivery_id',$tripId);
        }
        $packages = $qP->get();
        foreach($packages as $p){
            $p->date = $p->assign_driver_datetime;
            $p->delivery_fee = "0";
            // $p->extra_charge = (float)$p->extra_charge;
            // $p->price = (float)$p->price;
            if($p->payer == 'receiver'){
                $p->delivery_fee = (string)$p->base_fee + $p->extra_charge;
            }
            if($p->status_id == 9) $p->date = $p->delivered_datetime;
            if($p->status_id == 10 || $p->status_id == 19) $p->date = $p->failed_datetime;
            unset($p->assign_driver_datetime,$p->delivered_datetime,$p->failed_datetime);
        }
        return $packages;
    }

    public function getDeliveryItems(Request $req){
        $user = $this->user;
        $tripId = $req->trip_id;
        $driverId = $user->id;
        $packages = $this->tripPackageInfo($tripId,$driverId);
        foreach($packages as $package){
            $package->status_code = $package->status->name;
            unset($package->status);
        }
        return ApiResponse::JsonResult($packages);
    }

    public function acceptOrder(Request $req){
        $user = $this->user;
        $orderId = $req->order_id;
        $user = UserService::getAuthUser('driver');
        if($user->error) return ApiResponse::flex($user);
        $order = Order::where('is_deleted',0)->find($orderId);
        if(!$order) return ApiResponse::NotFound(__('messages.not_found',[
            'info' => 'Order'
        ]));

        if($order->status_id != 1){
            if($user->id != $order->driver_id) return ApiResponse::Duplicated(__('messages.info',[
                'info' => 'This order is not available'
            ]));
            else return ApiResponse::Duplicated(__('messages.info',[
                'info' => 'You have already accepted order ('.$order->code.')'
            ]));
        }

        $trackingNotes = $order->tracking_notes.'|Driver has accepted order ('.$order->code.') '.date('d-M-Y h:i:s A');

        $order->update([
            'tracking_notes' => $trackingNotes,
            'status_id' => 3,
            'driver_id' => $user->id
        ]);

        return ApiResponse::JsonResult(null,__('messages.info',[
            'info' => 'Order accepted'
        ]));
    }


    //** Pick or Pick & Book */
    public function updateAcceptedOrder(Request $req){
        $user = $this->user;
        $orderId = $req->order_id;
        $statusId = $req->status_id;
        // return $req;
        $order = Order::where('is_deleted',0)
        ->with(['merchant','tracking_status'])
        ->where('company_id',$user->company_id)
        ->where('driver_id',$user->id)
        ->selectRaw('id,status_id,merchant_id')
        ->find($orderId);
        if(!$order) return ApiResponse::NotFound(__('messages.info',[
            'info' => 'No order was found'
        ]));

        if($order->status_id == 2) return ApiResponse::Duplicated(__('messages.info',[
            'info' => 'This order has already been picked'
        ]));
        if($order->status_id !== 3) return ApiResponse::Duplicated(__('messages.info',[
            'info' => 'Only accepted orders can be updated',
            'khInfo' => 'ទាល់តែកញ្ចប់ដែលបានទទួលទើបអាចបន្ថែមព័ត៌មាន'
        ]));
        $qty = $req->qty;
        $images = $req->file('images') ?? [];
        $details = $req->details ?? [];
        if(!in_array($statusId,[2,4])) return ApiResponse::ValidateFail(__('messages.info',[
            'info' =>'Please choose the correct status'
        ]));
        if($statusId == 4 && empty($details)) return ApiResponse::ValidateFail(__('messages.info',[
            'info' => 'Please add details when you choose pick & book'
        ]));
        $qty = $req->qty ?? null;
        $user = UserService::getAuthUser('driver');

        $qty = $qty ?? $order->qty;
        if($qty <=0) return ApiResponse::ValidateFail(__('messages.info',[
            'info' => 'Order quantity must be atleast 1'
        ]));
        $acceptArr = [
            'status_id' => $statusId,
            'pickup_datetime' => now(),
            'qty' => $qty,
            'driver_id' => $user->id // the requester is driver
        ];
        if($statusId == 4){
            $acceptArr['booking_channel'] = 'driver';
            $pickMsg = 'Pick & Book';
        }

        if(isset($details[0])){
            $detailsCount = count($details);
            if($qty < $detailsCount) return ApiResponse::ValidateFail(__('messages.info',[
                'info' => 'Your details is greater than quantity',
                'khInfo' => 'ចំនួនកញ្ចប់និងទិន្នន័យកញ្ចប់មិនត្រូវគ្នា, ទិន្នន័យបញ្ចូលលើសចំនួនសរុប'
            ]));
            foreach($details as $d){
                $d['merchant_id'] = $order->merchant_id;
                $rD = new Request($d);
                $pkgSvc = new PickupCenterService();
                $savePkg = $pkgSvc->createOrUpdatePackage($rD,$user,null,$orderId);
                if($savePkg->error) return ApiResponse::flex($savePkg);
            }
        }
        if($statusId == 2) {
            $pickMsg = 'Pickup';
            // $imgCount = count($images);
            // if($imgCount != $qty) return ApiResponse::ValidateFail(__('messages.info',[
            //     'info' => 'Your package quantity is not matching the number of photos.',
            //     'khInfo' => 'ចំនួនកញ្ចប់និងចំនួនរូបភាពមិនត្រូវគ្នា'
            // ]));
            foreach($images as $image){
                $photoFileName = Helper::saveImageFile($image,$user->company_id,'order_image')->filename;
                if($photoFileName){
                    OrderImage::create([
                        'order_id' => $orderId,
                        'original_name' => $image->getClientOriginalName(),
                        'photo_file_name' => $photoFileName,
                        'create_uid' => $user->id,
                        'update_uid' => $user->id,
                        'company_id' => $user->company_id,
                        'branch_id' => $user->branch_id
                    ]);
                }
            }
        }
        $order->update($acceptArr);
        return ApiResponse::JsonResult(null,__('messages.info',[
            'info' => 'You have accepted for '.$pickMsg,
            'khInfo' => 'បានបញ្ចូលទិន្នន័យកញ្ចប់'
        ]));



    }


    private function validatePackageDetails(Request $req){
        return validator($req->all(),[
            'price' => 'nullable|numeric',
            'payer' => 'required|in:receiver,sender',
            'cod' => 'required|in:0,1',
            'receiver_address' => 'nullable|string|max:250',
            'receiver_phone' => 'required|string|max:20|min:8',
            'receiver_name' => 'nullable|string|max:50',
            'actual_kg' => 'nullable|numeric',
            'zone_code' => 'required',
            'merchant_id' => 'nullable',
            'pickup_notes' => 'nullable|string|max:250'
        ],[
            'receiver_phone.min' => __('messages.info',[
                'info' => 'Please enter a valid phone number',
                'khInfo' => 'សូមបញ្ចូលលេខទូរស័ព្ទដែរត្រឹមត្រូវ'
            ])
        ]);
    }

    public function submitDeliveryPackage(Request $req){
        $user = UserService::getAuthUser('driver');
        $id = $req->package_id;
        $validate = validator($req->all(),[
            'status_id' => 'required|in:9,10,19',
            'delivery_remarks' => 'nullable|string',
            'image' => 'nullable',
            'amount' => 'nullable|numeric',
            'payer' => 'nullable|in:sender,receiver'
        ]);
        if($validate->fails()) return ApiResponse::ValidateFail($validate->errors()->first());
        $inputs = $validate->validated();
        $status_id = $inputs['status_id'];
        $amount = $inputs['amount'] ?? 0;
        $inputs['price'] = $amount;
        $inputs['cod'] = $amount > 0 ? true:false;
        $codChange = $amount > 0 ? true:false;
        $inputs['cod_changed'] = $codChange;
        $photo = $inputs['image'] ?? null;
        $deliveryRemarks = $inputs['delivery_remarks'] ?? null;
        $payer = $inputs['payer'] ?? null;
        if($codChange){
            $inputs['driver_total'] = $amount;
        }

        $package = Package::where('is_deleted',0)->find($id);
        if(!$package) return ApiResponse::NotFound(__('messages.not_found',[
            'info' => 'Package'
        ]));
        if($photo) {
            $inputs['photo_file_name'] = Helper::saveImageFileOrBase64($photo,$user->company_id,'submit_package')->filename;
            Helper::deleteImageFile($package->photo_file_name,$user->company_id,'submit_package');
        }
        if($package->status_id == 9) return ApiResponse::Duplicated('This package has already been delivered!');
        if($package->status_id == 11) return ApiResponse::Duplicated('This package has already been returned!');
        if($package->driver_id !== $user->id) return ApiResponse::Duplicated(__('messages.info',[
            'info' => 'Please submit package that belongs to you'
        ]));
        $todayDt = Helper::getDateTime();
        $driverName = $user->user_name;
        $statusCode = $status_id == 9 ? 'Delivered' : ($status_id == 10 ? 'Failed':($status_id == 19 ? 'Failed with fee':''));
        $inputs['tracking_notes'] = $package->tracking_notes."|[$user->id]Driver ($driverName) submit $statusCode ($todayDt)[Remark: $deliveryRemarks]";
        if($codChange && $package->price != $amount){
            $inputs['tracking_notes'] .= "|[$user->id]Driver ($driverName) change cod $package->price to $amount ($todayDt)";
        }
        if($status_id == 9) {
            $inputs['delivered_datetime'] = now();
            $inputs['delivery_remarks'] = $deliveryRemarks;
        }
        if($status_id == 10) {
            if(!$deliveryRemarks) return ApiResponse::ValidateFail(__('messages.info',[
                'Please input remarks'
            ]));
            $inputs['failed_datetime'] = now();
            $inputs['failure_notes'] = $deliveryRemarks;
        }
        if($status_id == 19) {
            $inputs['failed_datetime'] = now();
            $inputs['failure_notes'] = $deliveryRemarks;
        }

        if($payer){
            $calucalteFee = GeneralSettingService::calculatePackageFee($package->zone_code,$package->price,$package->billed_kg,$package->actual_kg,$payer,$package->cod,$package->extra_charge,$user,$package->taxi_fee,$package->merchant_id);
            $inputs['merchant_total'] = $calucalteFee->merchant_total;
            $inputs['driver_total'] = $calucalteFee->driver_total;
        }

        $package->update($inputs);
        $dp = DeliveryPackage::where('package_id',$id)->where('driver_id',$user->id)->where('delay_count',0)->first();
        $dp->update([
            'notes' => $inputs['tracking_notes'],
            'status_id' => $status_id
        ]);
        GeneralSettingService::updateTripStatus($dp->delivery_id,$user);
        return ApiResponse::JsonResult(null,__('messages.submitted'));
    }

    public function cancelOrder(Request $req){
        $user = UserService::getAuthUser('driver');
        $orderId = $req->order_id;
        $reason = $req->reason;
        if(!$reason) return ApiResponse::ValidateFail(__('messages.info',[
            'info' => 'Please enter a reason'
        ]));
        $order = Order::where('is_deleted',0)->where('driver_id',$user->id)->find($orderId);
        if(!$order) return ApiResponse::NotFound(__('messages.not_found',[
            'info' => 'Order'
        ]));
        if($order->status_id == 20) return ApiResponse::Duplicated(__('messages.info',[
            'info' => 'Package has already been canceled'
        ]));
        $order->update([
            'cancel_notes' => $reason,
            'status_id' => 20 // canceled
        ]);
        return ApiResponse::JsonResult(null,__('messages.canceled'));
    }

    public function dropOrderAtWarehouse(Request $req){
        $user = UserService::getAuthUser('driver');
        $orderId = $req->order_id;
        $order = Order::where('is_deleted',0)->where('driver_id',$user->id)->find($orderId);
        if(!$order) return ApiResponse::NotFound(__('messages.not_found',[
            'info' => 'Order'
        ]));
        if($order->status_id == 21) return ApiResponse::Duplicated(__('messages.info',[
            'info' => 'Order has already dropped'
        ]));
        if(!in_array($order->status_id,[2,4])) return ApiResponse::ValidateFail(__('messages.info',[
            'info' => 'You can not mark as dropped'
        ]));
        $driverName = $user->name;
        $todayDt = Helper::getDateTime();
        $tracking_notes = $order->tracking_notes."|[$user->id]Driver ($driverName) dropped order ($todayDt)";
        $order->update([
            'status_id' => 21,
            'tracking_notes' => $tracking_notes
        ]);

        return ApiResponse::JsonResult(null,__('messages.info',[
            'info' => 'Dropped',
        ]));
    }

    public function markPackageContact(Request $req){
        $user = UserService::getAuthUser('driver');
        $orderId = $req->order_id;
        $packageRef = $req->package_ref;
        $order = Order::where('is_deleted',0)->find($orderId);
        if(!$order) return ApiResponse::NotFound(__('messages.not_found'));
        $package = Package::where('is_deleted',0)->where('order_id',$orderId)->where('driver_id',$user->id)->find($packageRef);
        if(!$package) Package::where('is_deleted',0)->where('order_id',$orderId)->where('driver_id',$user->id)->where('qr_code',$packageRef);
        if(!$package) return ApiResponse::NotFound(__('messages.not_found',[
            'info' => 'Package'
        ]));
        if($package->is_contact) return ApiResponse::Duplicated(__('messages.info',[
            'info' => 'This package has already contacted'
        ]));
        if(!in_array($package->status_id,[6])) return ApiResponse::ValidateFail(__('messages.info',[
            'info' => 'You can not mark as dropped'
        ]));
        $driverName = $user->user_name;
        $todayDt = Helper::getDateTime();
        $tracking_notes = $package->tracking_notes."|[$user->id]Driver Marked contact $todayDt";
        $package->update([
            'is_contact' => 1,
            'contact_datetime' => now(),
            'tracking_notes' => $tracking_notes
        ]);

        //** Send Notif */
        $notif = new CloudMessagingService();
        $topics = GeneralSettingService::getGeneralTopics($user->company_id,'merchant',$order->merchant_id);
        $notifReq = new Request([
            'topic' => $topics->private,
            'type' => 'private',
            'target_uid' => $package->merchant_id,
            'title' => 'Contact receiver',
            'body' => 'Driver contacted receiver '.$package->receiver_phone
        ]);
        $notif->sendNotificationByTopic($notifReq,$user);
        return ApiResponse::JsonResult(null,__('messages.info',[
            'info' => 'Marked as contact',
        ]));

    }

    public function getOptionsStatus(Request $req){
        $user = UserService::getAuthUser('driver');
        $orderId = $req->order_id;
        $statuses = GeneralSettingService::optionsTrackingStatus($user,[1,3,20],'pick');
        return ApiResponse::JsonResult($statuses);
    }

    public function setArriveWarehouse(Request $req){
        $user = UserService::getAuthUser('driver');
        $statuses = GeneralSettingService::optionsTrackingStatus($user,[1,3,20],'pick');
        return ApiResponse::JsonResult($statuses);
    }

    public function getTermConditions(Request $req){
        $user = UserService::getAuthUser('driver');
        return ApiResponse::JsonResult(GeneralSettingService::termAndConditions($user));
    }

    public function pinPackage(Request $req){
        $user = UserService::getAuthUser('driver');
        $sortList = $req->sort_list;
        if(empty($sortList)) return ApiResponse::ValidateFail(__('messages.info',[
            'info' => 'Sort list is required'
        ]));
        foreach($sortList as $sl){
            if(!isset($sl['package_id'])) return ApiResponse::ValidateFail(__('messages.info',[
                'info' => 'Package Identity is required'
            ]));
            $id = $sl['package_id'];
            $package = Package::where('is_deleted',0)->where('driver_id',$user->id)->find($id);
            if(!$package) return ApiResponse::ValidateFail(__('messages.not_found',[
                'info' => 'Package'
            ]));
        }
            // $package = PackageService::getPackage($sl['package_id']);
    }

    public function booking(Request $req){
        $user = UserService::getAuthUser('driver');
        $pckService = new PickupCenterService();
        $createOrder = $pckService->createOrder($req,$user);
        return ApiResponse::flex($createOrder);
    }


    public function getNotifications(){
        $user = UserService::getAuthUser('driver');
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
        $user = UserService::getAuthUser('driver');
        $mr = GeneralSettingController::markReadNotification($req,$user);
        return ApiResponse::flex($mr);
    }

}
