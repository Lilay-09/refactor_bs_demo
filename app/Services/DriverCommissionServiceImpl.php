<?php

namespace App\Services;

use App\Models\Bank;
use App\Models\Disbursement;
use App\Models\DisbursementDetails;
use App\Models\DisbursementPackage;
use App\Models\DriverCommission;
use App\Models\Order;
use App\Models\Package;
use App\Models\User;
use DataResponse;
use Exception;
use Helper;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DriverCommissionServiceImpl implements DriverCommissionService
{
    // Your service methods go here
    public function getDriverCommissionPackage(array $data): mixed{
        $driverId = $data['driver_id'] ?? null;
        $qD = User::query()->selectRaw('code,id,user_name as driver_name,phone as driver_phone')->where('account_type','driver');
        if($driverId) $qD->where('id',$driverId);
        $driverIds = $qD->pluck('id');
        $qP = Package::query()->from('packages as p')
        ->where('p.delivery_fee', '>' ,0)
        ->whereIn('p.driver_id',$driverIds)
        ->select('p.status_id','p.qr_code','p.prev_status_id','p.delivery_fee','p.driver_id','p.delivery_type')
        ->where(function ($query) {
            $query->whereIn('p.status_id', [9, 19])
                ->orWhere('p.prev_status_id', 19);
        })
        ->where('p.is_deleted',0)
        // ->whereNull('driver_commission_id');
        ->whereNotExists(function ($sub) {
            $sub->select(DB::raw(1))
                ->from('disbursement_packages as dp')
                ->whereColumn('dp.package_id', 'p.id')
                ->where('dp.payee_type', 'driver')
                ->where('dp.type','commission')
                ->where('dp.is_deleted', false);
        });
        $qDc = DriverCommission::query()->where('is_deleted',0)->selectRaw('id,driver_id,delivery_type,pickup_commission,pickup_commission_type,delivery_commission,delivery_commission_type,pickup_commission_start_date,delivery_commission_start_date');
        $startDateFromQuery = $data['startDate'] ?? null;
        $endDate = $data['endDate'] ?? null;
        $normalDeliveryStartDate = Helper::dateYMD($startDateFromQuery);
        $normalPickUpStartDate = $normalDeliveryStartDate;
        $endDate = $endDate ? Helper::dateYMD($endDate). ' 23:59:59' : null;
        // return $endDate;
        $driverCommissions = [];
        if($driverId){
            $driverCommissionInfo = TransactionService::getDriverCommissionInfo($driverCommissions,$driverId);
            $commissionStartDate = $driverCommissionInfo->normal_pickup_commission_start_date;
            $deliveryStartDate = $driverCommissionInfo->normal_delivery_commission_start_date;
            $driverCommissions = $qDc->where('driver_id',$driverId)->get();
            if ($commissionStartDate) {
                    $commissionStartDate = date('Y-m-d', strtotime($commissionStartDate));
                if ($startDateFromQuery < $commissionStartDate) {
                    $normalPickUpStartDate = $commissionStartDate;
                }
            }
            if ($deliveryStartDate) {
                $deliveryStartDate = date('Y-m-d', strtotime($deliveryStartDate));
                if ($startDateFromQuery < $deliveryStartDate) {
                    $normalDeliveryStartDate = $deliveryStartDate;
                }
            }
        }

        $packageSubQuery = Package::query()
        ->select(
            'order_id',  // <-- aggregate per order
            DB::raw('COUNT(*) as qty'),
            DB::raw('SUM(delivery_fee) as total_delivery_fee')
        )
        ->where('is_deleted', 0)
        ->where('delivery_fee', '>', 0)
        ->where(function($q) use ($normalPickUpStartDate, $endDate){
            $q->where(function($q2) use ($normalPickUpStartDate, $endDate){
                $q2->where('status_id', 9)
                ->whereBetween('delivered_datetime', [$normalPickUpStartDate, $endDate]);
            })
            ->orWhere(function($q2) use ($normalPickUpStartDate, $endDate){
                $q2->where(function($q3){
                    $q3->where('status_id', 19)
                    ->orWhere('prev_status_id', 19);
                })
                ->whereBetween('failed_datetime', [$normalPickUpStartDate, $endDate]);
            });
        })
        // ->when($driverId, function($q) use ($driverId){
        //     $q->where('driver_id', $driverId);
        // })
        ->groupBy('order_id');  // <-- group by order_id

        $qO = Order::query()
            ->select('orders.id', 'orders.driver_id', 'pSub.qty', 'pSub.total_delivery_fee')
            ->where('orders.is_deleted', 0)
            ->whereNull('driver_commission_id')
            ->where('status_id', 5)
            ->whereIn('orders.driver_id', $driverIds)
            ->joinSub($packageSubQuery, 'pSub', function($join){
                // $join->on('orders.driver_id', '=', 'pSub.driver_id');
                $join->on('orders.id', '=', 'pSub.order_id');
            })
            ->groupBy('orders.id', 'orders.driver_id', 'pSub.qty', 'pSub.total_delivery_fee')
            ->having('pSub.qty', '>', 0);

        if($normalDeliveryStartDate && $endDate){
            $qP->where(function ($q) use ($normalDeliveryStartDate, $endDate) {
                $q->where(function ($q) use ($normalDeliveryStartDate, $endDate) {
                    // Status 9: delivered packages within date range
                    $q->where('p.status_id', 9)
                    ->whereBetween('p.delivered_datetime', [$normalDeliveryStartDate, $endDate]);
                })
                ->orWhere(function ($q) use ($normalDeliveryStartDate, $endDate) {
                    // Failed packages (status 19 or prev_status_id 19) within failed_datetime
                    $q->where(function ($q) {
                        $q->where('p.status_id', 19)
                        ->orWhere('p.prev_status_id', 19);
                    })
                    ->whereBetween('p.failed_datetime', [$normalDeliveryStartDate, $endDate]);
                });
            });
        }
        if($normalPickUpStartDate && $endDate){
            $qO->whereHas('packages', function ($q) use ($normalPickUpStartDate, $endDate) {
                // Delivered packages within date range
                $q->where(function ($query) use ($normalPickUpStartDate, $endDate) {
                    $query->where('status_id', 9)
                        ->whereBetween('delivered_datetime', [$normalPickUpStartDate, $endDate]);
                })

                // OR Failed packages within date range
                ->orWhere(function ($query) use ($normalPickUpStartDate, $endDate) {
                    $query->where(function ($sub) {
                            $sub->where('status_id', 19)
                                ->orWhere('prev_status_id', 19);
                        })
                        ->whereBetween('failed_datetime', [$normalPickUpStartDate, $endDate]);
                })
                ->where('is_deleted', 0);
            });
        }
        if($driverId) {
            $qP->where('driver_id',$driverId);
            $qO->where('driver_id',$driverId);
            $qDc->where('driver_id',$driverId);
        }
        $packages = $qP->get();
        $orders = $qO->get();
        // logger('Packages:');
        // logger(json_encode($packages,JSON_PRETTY_PRINT));
        // logger('Orders:');
        // logger(json_encode($orders,JSON_PRETTY_PRINT));
        if(empty($driverCommissions)){
            $driverCommissions = $qDc->get();
        }
        // Log::info(json_encode($orders,JSON_PRETTY_PRINT));
        //** Callback func */
        // Log::info(json_encode($driverCommissionInfo));
        $clbMapper = function ($driver) use ($driverCommissions,$orders, $packages) {
            // $commissionInfo = TransactionService::getDriverCommissionInfo($driverCommissionInfo, $driver->id);
            $driverCommissionInfo = TransactionService::getDriverCommissionInfo($driverCommissions,$driver->id);
            $pickup_rate = $driverCommissionInfo->normal_pickup_commission;
            $delivery_rate = $driverCommissionInfo->normal_delivery_commission;
            $normalPickupCommissionType = $driverCommissionInfo->normal_pickup_commission_type;
            $normalDeliveryCommissionType = $driverCommissionInfo->normal_delivery_commission_type;

            $driver->pickup_rate = Helper::formatWithType($pickup_rate,$normalPickupCommissionType);
            $driver->delivery_rate = Helper::formatWithType($delivery_rate,$normalDeliveryCommissionType);
            $driver->delivery_fast_rate = $driverCommissionInfo->fast_delivery_commission;

            $pickUpInfo = $this->getPickUpDetails($orders, $driver->id);
            $deliverdInfo = $this->getDeliveredDetails($packages, $driver->id);
            // Log::info(json_encode($deliverdInfo));

            $totalPickUp = $pickUpInfo->total_package ?? 0;
            $totalNormalPkg = ($deliverdInfo->normal_delivered_count ?? 0) + ($deliverdInfo->normal_failed_with_fee_count ?? 0);//($deliverdInfo->normal_delivered_count ?? 0) + ($deliverdInfo->normal_failed_with_fee_count ?? 0);
            $totalFastPkg = ($deliverdInfo->fast_delivered_count ?? 0) + ($deliverdInfo->fast_failed_with_fee_count ?? 0);

            $driver->total_pickup = $totalPickUp;
            $driver->total_delivered = ($deliverdInfo->normal_delivered_count ?? 0) + ($deliverdInfo->fast_delivered_count ?? 0);
            $driver->normal_delivered_count = $deliverdInfo->normal_delivered_count;
            $driver->fast_delivered_count = $deliverdInfo->fast_delivered_count;

            $driver->total_failed_with_fee = ($deliverdInfo->normal_failed_with_fee_count ?? 0) + ($deliverdInfo->fast_failed_with_fee_count ?? 0);
            $driver->total_commission_packages = $deliverdInfo->total_commission_pkg ?? 0;
            $driver->normal_failed_with_fee_count = $deliverdInfo->normal_failed_with_fee_count;
            $driver->fast_failed_with_fee_count = $deliverdInfo->fast_failed_with_fee_count;

            $totalNormalBaseFee = $deliverdInfo->total_normal_base_fee;
            $totalPickupBaseFee = $pickUpInfo->total_base_fee;
            $totalPickupRate = TransactionService::calculateCommission($pickup_rate,$normalPickupCommissionType,$totalPickUp,$totalPickupBaseFee);
            $totalDeliveryNormal = TransactionService::calculateCommission($delivery_rate,$normalDeliveryCommissionType,$totalNormalPkg,$totalNormalBaseFee);
            $driver->total = Helper::getNumber(
                $totalPickupRate + $totalDeliveryNormal,
                2
            );
            $calculator = [
                'pickup' => null,
                'delivery' => null
            ];
            if($normalPickupCommissionType == 'percentage'){
                if(!isset($calculator['pickup_base_fee'])){
                    $calculator['pickup_base_fee'] = ": $$totalPickupBaseFee";
                }
                $calculator['pickup'] = ": $driver->pickup_rate * $totalPickupBaseFee = $totalPickupRate";
            }else{
                if(!isset($calculator['pickup_count'])){
                    $calculator['pickup_count'] = $totalPickUp;
                }
                $calculator['pickup'] = ": $driver->pickup_rate * $totalPickUp = $totalPickupRate";
            }

            if($normalDeliveryCommissionType == 'percentage'){
                if(!isset($calculator['delivery_base_fee'])){
                    $calculator['delivery_base_fee'] = ": $$totalNormalBaseFee";
                }
                $calculator['delivery'] = ": $driver->delivery_rate * $totalNormalBaseFee = $totalDeliveryNormal";
            }else{
                if(!isset($calculator['delivery_count'])){
                    $calculator['delivery_count'] = $totalNormalPkg;
                }
                $calculator['delivery'] = ": $driver->delivery_rate * $totalNormalPkg = $totalDeliveryNormal";
            }

            $driver->calculator = $calculator;

            // Bank account info
            $driver->bank_account = null;
            foreach ($driver->bank_accounts as $b) {
                $driver->bank_account = GeneralSettingService::concatBankInfo($b->bank_name, $b->bank_number, $b->account_name);
                if ($b->is_primary) {
                    break; // Prefer primary bank account
                }
            }

            $driver->status_code = 'Pending';
            unset($driver->bank_accounts);

            return $driver;
        };

        return DataResponse::PaginationV1($qD,$data,'',[],1000,$clbMapper);
        // return ApiResponse::Pagination($driverInfo,$req);
    }


    // public function getPickUpDetails($orders,$driverId){
    //     $totalPkg = 0;
    //     $totalBaseFee = 0;
    //     foreach($orders as $order){
    //         if($order->driver_id == $driverId){
    //             $totalPkg += $order->qty;
    //             $totalBaseFee += $order->total_delivery_fee;
    //         }
    //     }
    //     return (object)[
    //         'total_package' => $totalPkg,
    //         'total_base_fee' => $totalBaseFee
    //     ];
    // }
    public static function getPickUpDetails($orders,$driverId): object{
        $totalPkg = 0;
        $orderIds = [];
        $totalBaseFee = 0;
        foreach($orders as $order){
            if($order->driver_id == $driverId){
                $totalPkg += $order->qty;
                $orderIds[] = $order->id;
                $totalBaseFee += $order->total_delivery_fee;
            }
        }
        return (object)[
            'total_package' => $totalPkg,
            'order_ids' => $orderIds,
            'total_base_fee' => $totalBaseFee,
        ];
    }

    public function getDeliveredDetails($packages,$driverId): object{
        $totalPkg = 0;
        $normalFailedWithFeeCount = 0;
        $fastDeliveredCount = 0;
        $fastFailedWithFeeCount = 0;
        $normalDeliveredCount = 0;
        $totalCommissionPkg = 0;
        $totalNormalBaseFee = 0;
        foreach($packages as $pkg){
            if($pkg->driver_id == $driverId){
                $totalPkg += 1;
                if($pkg->status_id == 9) {
                    if($pkg->delivery_type == 'normal'){
                        $normalDeliveredCount +=1;
                        $totalNormalBaseFee += $pkg->delivery_fee;
                    }else if($pkg->delivery_type == 'fast'){
                        $fastDeliveredCount +=1;
                    }
                    $totalCommissionPkg +=1;
                    
                }
                if($pkg->status_id == 19 || $pkg->prev_status_id == 19) {
                    if($pkg->delivery_type == 'normal'){
                        $normalFailedWithFeeCount +=1;
                        $totalNormalBaseFee += $pkg->delivery_fee;
                    }
                    else if($pkg->delivery_type == 'fast'){
                        $fastFailedWithFeeCount +=1;
                    }
                    $totalCommissionPkg +=1;
                }

            }
        }

        return (object)[
            'total_package' => $totalPkg,
            'normal_delivered_count' => $normalDeliveredCount,
            'normal_failed_with_fee_count' => $normalFailedWithFeeCount,
            'fast_delivered_count' => $fastDeliveredCount,
            'fast_failed_with_fee_count' => $fastFailedWithFeeCount,
            'total_commission_pkg' => $totalCommissionPkg,
            'total_normal_base_fee' => $totalNormalBaseFee,
        ];
    }

    private function disbursementPaymentValidation(array $data,$type='driver'){
        return validator($data,[
            $type.'_id' => 'required|int',
            'cash' => 'nullable|numeric',
            'cash_kh' => 'nullable|numeric',
            'cash_khr' => 'nullable|numeric',
            'bank_amount' => 'nullable|numeric',
            'bank_amount_kh' => 'nullable|numeric',
            'bank_id' => 'nullable|int',
            'remarks' => 'nullable|string|max:500',
            'start_date' => 'nullable',
            'end_date' => 'nullable',
            'exchange_rate' => 'required|numeric',
            'packages' => 'nullable'
        ]);
    }

    public function disbursementCommission(array $data,$user,$type): mixed{
        $validType = $this->validType($type);
        if($validType->error) return $validType;
        $startDate = $data['startDate'] ?? null;
        $endDate = $data['endDate'] ?? null;
        $validate = self::disbursementPaymentValidation($data,$type);
        if($validate->fails()) return DataResponse::ValidateFail($validate->errors()->first());
        $inputs = $validate->validated();
        $payeeId = $inputs[$type.'_id'];
        $payeeInfo = User::where('is_deleted',0)->whereIn('account_type',['driver','merchant'])->find($payeeId);
        if(!$payeeInfo) return DataResponse::NotFound('Could not find payee information');
        $validPackages = $this->validCommissionPackage($payeeId,$type,$startDate,$endDate);
        // return DataResponse::JsonResult($validPackages);
        if($validPackages->error) return $validPackages;
        $packageIds = $validPackages->package_ids;
        $orderIds = $validPackages->order_ids;
        $exchangeRate = $inputs['exchange_rate'] ?? GeneralSettingService::getLatestXRate()->buy_rate;
        $cashKh = $inputs['cash_kh'] ?? $inputs['cash_khr'] ?? 0;
        $cash = $inputs['cash'] ?? 0;
        $bankId = $inputs['bank_id'] ?? null;
        $bankAmount = $inputs['bank_amount'] ?? 0;
        $bankAmountKh = $inputs['bank_amount_kh'] ?? 0;
        $dueAmount = $validPackages->grand_total;
        $validPayment = $this->validPayment($cash,$cashKh,$bankAmount,$bankAmountKh,$bankId,$dueAmount,$exchangeRate);
        if($validPayment->error) return $validPayment;
        $breakDownNotes = null;
        if($dueAmount > 0){
            if($cash > 0) $breakDownNotes .= 'Cash: USD '.$cash.'|';
            if($cashKh > 0) $breakDownNotes .= 'Cash: KHR '.$cashKh.'|';
            if($bankAmount > 0) $breakDownNotes .= $validPayment->bank_name.': USD '.$bankAmount.'|';
            if($bankAmountKh > 0) $breakDownNotes .= $validPayment->bank_name.': KHR '.$bankAmountKh.'|';
        }
        $breakDownNotes = trim($breakDownNotes, '| ');
        DB::beginTransaction();
        try{
            $inserData = [
                'payee_id' => $payeeId,
                'type' => 'commission',
                'payee_type' => $type,
                'taxi_fee' => $validPackages->total_taxi_fee,
                'delivery_fee' => $validPackages->total_delivery_fee,
                'payable_amount' => $dueAmount,
                'create_uid' => $user->id,
                'receiver_uid' => $user->id,
                'amount' => $dueAmount,
                'exchange_rate' => $exchangeRate,
                'approved' => true,
                'is_settled' => true,
                'pickup_rate' => $validPackages->pickup_rate,
                'pickup_rate_type' => $validPackages->pickup_rate_type,
                'fast_pickup_rate' => $validPackages->fast_pickup_rate,
                'delivery_rate' => $validPackages->delivery_rate,
                'delivery_rate_type' => $validPackages->delivery_rate_type,
                'fast_delivery_rate' => $validPackages->fast_delivery_rate,
                'settled_uid' => $user->id,
                'approved_uid' => $user->id,
                'receiptionist_uid' => $user->id,
                'remarks' => $inputs['remarks'] ?? null,
                'package_count' => $validPackages->total_package,
                'delivered_package_count' => $validPackages->total_delivered_package,
                'pickup_package_count' => $validPackages->total_pickup_package,
                'failed_with_fee_count' => $validPackages->total_failed_with_fee_package,
                'update_uid' => $user->id,
                'approved_datetime' => now(),
                'settled_datetime' => now(),
                'payment_datetime' => now(),
                'breakdown_notes' => $breakDownNotes,
                'company_id' => $user->company_id,
                'branch_id' => $user->branch_id
            ];
            
            $createPayment = Disbursement::create($inserData);
            $paymentId = $createPayment->id;
            if($cash && $dueAmount > 0){
                DisbursementDetails::create([
                    'disbursement_id' => $paymentId,
                    'method' => 'cash',
                    'amount' => $cash,
                    'original_amount' => $cash,
                    'currency_code' => 'USD'
                ]);
            }
            if($cashKh && $dueAmount > 0){
                DisbursementDetails::create([
                    'disbursement_id' => $paymentId,
                    'method' => 'cash',
                    'amount' => $cashKh,
                    'original_amount' => $validPayment->original_cash_amount_kh,
                    'currency_code' => 'KHR'
                ]);
            }
            if($bankId){
                if($bankAmount && $dueAmount > 0){
                    DisbursementDetails::create([
                        'disbursement_id' => $paymentId,
                        'method' => $validPayment->bank_name,
                        'amount' => $bankAmount,
                        'original_amount' => $bankAmount,
                        'currency_code' => 'USD'
                    ]);
                }

                if($bankAmountKh && $dueAmount > 0){
                    DisbursementDetails::create([
                        'disbursement_id' => $paymentId,
                        'method' => $validPayment->bank_name,
                        'amount' => $bankAmountKh,
                        'original_amount' => $validPayment->original_bank_amount_kh,
                        'currency_code' => 'KHR'
                    ]);
                }
            }
            // Package::whereIn('id',$packageIds)->update([
            //     $type.'_commission_id' => $paymentId
            // ]);
            DisbursementPackage::insert(collect($packageIds)->map(fn($id) => [
                'package_id' => $id,
                'disbursement_id' => $paymentId,
                'type' => 'commission',
                'payee_type' => $type
            ])->toArray());

            // Log::info($orderIds);
            Order::whereIn('id',$orderIds)->update([
                $type.'_commission_id' => $paymentId
            ]);
            // return $packageIds;
            // return Package::whereIn('id',$packageIds)->get();
            DB::commit();

            // return Package::whereIn('id',$packageIds)->get();
            return DataResponse::JsonResult(null,false,__('messages.created',[
                'info' => 'Payment'
            ]));
        }catch(Exception $e){
            // Log::error($e->getMessage());
            Log::error($e->getTraceAsString());
            DB::rollBack();
            return DataResponse::Error(__('messages.error',['info' => 'Fail to receive']));
        }
    }

    private function validCommissionPackage($payeeId,$type,$startDate,$endDate){
        $validType = $this->validType($type);
        if($validType->error) return $validType;
        // $deliveredCount = 0;
        $pickUpCount = 0;
        // $failedWithFeeCount = 0;
        $obj = (object)[
            'error' => false,
            'total_pickup' => 0,
            'total_delivered' => 0,
            'total_delivery_fee' => 0,
            'total_taxi_fee' => 0,
            'grand_total' => 0,
            'total_package' => 0,
            'total_delivered_package' => 0,
            'total_pickup_count' => 0,
            'package_ids' => [],
            'order_ids' => []
        ];
        $qP = Package::from('packages as p')
            ->where('p.delivery_fee','>',0)
            ->selectRaw('
                p.qr_code,p.id, p.status_id,p.delivery_fee,p.prev_status_id,p.driver_id,p.delivery_type,
                p.failed_datetime, p.delivered_datetime
            ')
            // ->whereIn('p.status_id', [9, 19])
            ->where(function ($query) {
                $query->whereIn('p.status_id', [9, 19])
                    ->orWhere('p.prev_status_id', 19);
            })
            ->where('p.is_deleted', 0)
        ->whereNotExists(function ($sub) {
            $sub->select(DB::raw(1))
                ->from('disbursement_packages as dp')
                ->whereColumn('dp.package_id', 'p.id')
                ->where('dp.payee_type', 'driver')
                ->where('dp.type','commission')
                ->where('dp.is_deleted', false);
        });

        $qO = Order::query()
        ->select('id','driver_id') // select only needed columns
        ->where('is_deleted', 0)
        ->whereNull('driver_commission_id')
        ->where('status_id', 5)
        ->groupBy('id');
        

        if($payeeId) {
            $qO->where('driver_id',$payeeId);
            $qP->where('p.driver_id',$payeeId);
            logger("Filtering for driver_id: $payeeId");
        }
        // if($startDate && $endDate){
        //     $startDate = Helper::dateYMD($startDate);
        //     $endDate = Helper::dateYMD($endDate);
        //     $qO->whereRaw('order_datetime::DATE >= ? AND order_datetime::DATE <= ?', [$startDate, $endDate]);
        // }

        $qDc = DriverCommission::query()
            ->where('is_deleted',0)
            ->where('driver_id', $payeeId)
            ->selectRaw('id,driver_id,delivery_type,pickup_commission,pickup_commission_type,delivery_commission,delivery_commission_type,pickup_commission_start_date,delivery_commission_start_date');

        // if($driverId)
        $driverCommissions = $qDc->get();
        $dc = TransactionService::getDriverCommissionInfo($driverCommissions,$payeeId);

        $startDateFromQuery = $startDate ?? null;
        $defaultNormalDeliveryDate = $dc->normal_delivery_commission_start_date;
        // $defaultFastDeliveryDate = $dc->fast_delivery_commission_start_date;
        $defaultNormalPickUpDate = $dc->normal_pickup_commission_start_date;

        $normalDeliveryStartDate = $startDateFromQuery
            ? max(Helper::dateYMD($startDateFromQuery).' 00:00:00', $defaultNormalDeliveryDate)
            : null;
        // $fastDeliveryStartDate = $startDateFromQuery
        //     ? max(Helper::dateYMD($startDateFromQuery).' 00:00:00', $defaultFastDeliveryDate)
        //     : null;
        $normalPickUpStartDate = $startDateFromQuery
            ? max(Helper::dateYMD($startDateFromQuery).' 00:00:00', $defaultNormalPickUpDate)
            : null;


        $endDate = $endDate ? Helper::dateYMD($endDate). ' 23:59:59' : null;

        if($normalDeliveryStartDate && $endDate){
            // $endDate = Helper::dateYMD($endDate);
            // $qP->whereRaw("
            //     p.status_id = 9 AND p.delivered_datetime >= ? AND p.delivered_datetime <= ?
            // ", [$normalDeliveryStartDate, $endDate]);
            $qP->where(function($q) use ($normalDeliveryStartDate, $endDate) {
                $q->where(function ($q) use ($normalDeliveryStartDate, $endDate) {
                    // Status 9: delivered packages within date range
                    $q->where('p.status_id', 9)
                    ->whereBetween('p.delivered_datetime', [$normalDeliveryStartDate, $endDate]);
                })
                ->orWhere(function ($q) use ($normalDeliveryStartDate, $endDate) {
                    // Failed packages (status 19 or prev_status_id 19) within failed_datetime
                    $q->where(function ($q) {
                        $q->where('p.status_id', 19)
                        ->orWhere('p.prev_status_id', 19);
                    })
                    ->whereBetween('p.failed_datetime', [$normalDeliveryStartDate, $endDate]);
                });
            });

            // $qP->whereRaw("
            //     (
            //         (p.status_id = 19 AND p.failed_datetime >= ? AND p.failed_datetime <= ?)
            //         OR
            //         (p.status_id = 9 AND p.delivered_datetime >= ? AND p.delivered_datetime <= ?)
            //     )
            // ", [$startDate, $endDate, $startDate, $endDate]);

            // $qO->whereRaw('pickup_datetime >= ? AND pickup_datetime <= ?', [$normalDeliveryStartDate, $endDate]);
            // $qO->whereBetween('pickup_datetime',[$normalPickUpStartDate,$endDate]);
        }
        if($normalPickUpStartDate && $endDate){
            $qO->whereHas('packages', function ($q) use ($normalPickUpStartDate, $endDate) {

                // Delivered packages within date range
                $q->where(function ($q) use ($normalPickUpStartDate, $endDate) {
                    $q->where(function ($query) use ($normalPickUpStartDate, $endDate) {
                        $query->where('status_id', 9)
                            ->whereBetween('delivered_datetime', [$normalPickUpStartDate, $endDate]);
                    })

                    // OR Failed packages within date range
                    ->orWhere(function ($query) use ($normalPickUpStartDate, $endDate) {
                        $query->where(function ($sub) {
                                $sub->where('status_id', 19)
                                    ->orWhere('prev_status_id', 19);
                            })
                            ->whereBetween('failed_datetime', [$normalPickUpStartDate, $endDate]);
                    });
                })

                ->where('is_deleted', 0);
            });
        }
        $test = Order::query()
        ->where('is_deleted', 0)
        ->whereNull('driver_commission_id')
        ->where('status_id', 5)
        ->where('driver_id',$payeeId)
        ->whereHas('packages', function ($q) use ($normalPickUpStartDate, $endDate) {
            $q->where(function ($q) use ($normalPickUpStartDate, $endDate) {
                $q->where(function ($query) use ($normalPickUpStartDate, $endDate) {
                    $query->where('status_id', 9)
                        ->whereBetween('delivered_datetime', [$normalPickUpStartDate, $endDate]);
                })
                ->orWhere(function ($query) use ($normalPickUpStartDate, $endDate) {
                    $query->where(function ($sub) {
                            $sub->where('status_id', 19)
                                ->orWhere('prev_status_id', 19);
                        })
                        ->whereBetween('failed_datetime', [$normalPickUpStartDate, $endDate]);
                });
            })
            ->where('is_deleted', 0);
        })
        ->get();
        logger("Test Query Result:");
        logger(json_encode($test,JSON_PRETTY_PRINT));
        $packages = $qP->get();
        $orders = $qO->withCount([
            'packages as qty' => fn($q) => $q
                ->where(function ($query) use ($normalPickUpStartDate, $endDate) {
                    $query->where(function($q2) use ($normalPickUpStartDate, $endDate){
                        $q2->where('status_id', 9)
                        ->whereBetween('delivered_datetime', [$normalPickUpStartDate, $endDate]);
                    })
                    ->orWhere(function($q2) use ($normalPickUpStartDate, $endDate){
                        $q2->where(function($q3){
                            $q3->where('status_id', 19)
                            ->orWhere('prev_status_id', 19);
                        })
                        ->whereBetween('failed_datetime', [$normalPickUpStartDate, $endDate]);
                    });
                })
                ->where('is_deleted', 0)
        ])
        ->withSum([
            'packages as total_delivery_fee' => fn($q) => $q
                ->where(function ($query) use ($normalPickUpStartDate, $endDate) {
                    $query->where(function($q2) use ($normalPickUpStartDate, $endDate){
                        $q2->where('status_id', 9)
                        ->whereBetween('delivered_datetime', [$normalPickUpStartDate, $endDate]);
                    })
                    ->orWhere(function($q2) use ($normalPickUpStartDate, $endDate){
                        $q2->where(function($q3){
                            $q3->where('status_id', 19)
                            ->orWhere('prev_status_id', 19);
                        })
                        ->whereBetween('failed_datetime', [$normalPickUpStartDate, $endDate]);
                    });
                })
                ->where('is_deleted', 0)
        ], 'delivery_fee')
        ->having('qty', '>', 0)
        ->get();
        // logger("packages:");
        // logger(json_encode($packages,JSON_PRETTY_PRINT));
        // logger("orders:");
        // logger(json_encode($orders,JSON_PRETTY_PRINT));
        // $qDc = DriverCommission::where('driver_id',$payeeId)->where('is_deleted',0)->selectRaw('id,driver_id,delivery_type,pickup_commission,pickup_commission_type,delivery_commission_type,delivery_commission,pickup_commission_start_date,delivery_commission_start_date');
        // $driverCommissions = $qDc->get();
        // $dc = TransactionService::getDriverCommissionInfo($driverCommissions,$payeeId);
        // foreach($orders as $order){
            // $pickUpCount += $order->qty;
            // $obj->order_ids[] = $order->id;
        // }
        $pickUpInfo = self::getPickUpDetails($orders, $payeeId);
        $obj->order_ids = $pickUpInfo->order_ids;
        $pickUpCount = $pickUpInfo->total_package;
        // Log::info(json_encode($pickUpInfo));
        $totalCommissionPkg = 0;
        $normalDeliveredCount = 0;
        $fastDeliveredCount = 0;

        $normalFailedWithFeeCount = 0;
        $fastFailedWithFeeCount = 0;
        $totalNormalBaseFee = 0;
        foreach($packages as $package){
            if($package->driver_id == $payeeId){
                    // if($package->status_id == 9) {
                //     $deliveredCount += 1;
                    $obj->package_ids[] = $package->id;
                // }
                // if($package->status_id == 19) $failedWithFeeCount +=1;
                // if($package->cod) $obj->total_taxi_fee += $package->delivery_fee;
                // if($package->taxi_fee) $obj->total_taxi_fee += $package->taxi_fee;
                if($package->status_id == 9) {
                    if($package->delivery_type == 'normal'){
                        $totalNormalBaseFee += $package->delivery_fee;
                        $normalDeliveredCount +=1;
                    }else if($package->delivery_type == 'fast'){
                        $fastDeliveredCount +=1;
                    }
                    $totalCommissionPkg +=1;
                }
                if($package->status_id == 19 || $package->prev_status_id == 19) {
                    // Log::info("{$package->status_id} ---- {$package->prev_status_id}");
                    if($package->delivery_type == 'normal'){
                        $normalFailedWithFeeCount +=1;
                        $totalNormalBaseFee += $package->delivery_fee;
                    }
                    else if($package->delivery_type == 'fast'){
                        $fastFailedWithFeeCount +=1;
                    }
                    $totalCommissionPkg +=1;
                }
            }
        }
        // Log::info($totalNormalBaseFee);
        $pickup_rate = $dc->normal_pickup_commission;
        $delivery_rate = $dc->normal_delivery_commission;
        $normalPickupCommissionType = $dc->normal_pickup_commission_type;
        $normalDeliveryCommissionType = $dc->normal_delivery_commission_type;
        $totalPickup = TransactionService::calculateCommission($pickup_rate,$normalPickupCommissionType,$pickUpCount,$pickUpInfo->total_base_fee);
        $totalPkg = TransactionService::calculateCommission($delivery_rate,$normalDeliveryCommissionType,($normalDeliveredCount + $normalFailedWithFeeCount),$totalNormalBaseFee);
        $obj->total_pickup = $totalPickup;
        $obj->total_delivered = $normalDeliveredCount * $dc->normal_delivery_commission + $fastDeliveredCount * $dc->fast_delivery_commission;
        $obj->total_package = $pickUpCount + $normalDeliveredCount + $fastDeliveredCount + $normalFailedWithFeeCount + $fastFailedWithFeeCount;
        //** Deliverd pkgs */
        $obj->normal_delivered_count= $normalDeliveredCount;
        $obj->fast_delivered_count = $fastDeliveredCount;
        //-----
        $obj->total_delivered_package = $normalDeliveredCount + $fastDeliveredCount;
        $obj->total_pickup_package = $pickUpCount;
        $obj->delivery_rate = $delivery_rate;
        $obj->delivery_rate_type = $normalDeliveryCommissionType;
        $obj->pickup_rate = $dc->normal_pickup_commission;
        $obj->pickup_rate_type = $normalPickupCommissionType;
        $obj->fast_delivery_rate = $dc->fast_delivery_commission;
        $obj->fast_pickup_rate = $dc->fast_pickup_commission;
        //** FailedWithFee pkgs */
        $obj->normal_failed_with_fee_count = $normalFailedWithFeeCount;
        $obj->fast_failed_with_fee_count = $fastFailedWithFeeCount;
        $obj->total_failed_with_fee_package = $normalFailedWithFeeCount + $fastFailedWithFeeCount;
        //----
        // $normalRate = $dc->normal_delivery_commission;
        // $totalPkg = ($normalDeliveredCount * $normalRate) + ($normalFailedWithFeeCount * $normalRate);
        $obj->total_pickup_count = $pickUpCount;

        $obj->grand_total = Helper::getNumber($totalPickup + $totalPkg,2);
        if(empty($obj->package_ids) && empty($obj->order_ids)){
            return DataResponse::NotFound('No package found');
        }
        logger((array)$obj);
        return $obj;
    }
    private function validType($type){
        $validType = ['driver','merchant'];
        if(!in_array($type,$validType)) return DataResponse::ValidateFail('Invalid type');
        return DataResponse::JsonResult(null);
    }

    private function validPayment($cash,$cashKh,$bankAmount,$bankAmountKh,$bankId,$dueAmount,$exhangeRate){
        $bankName = null;
        if($bankAmount > 0 && !$bankId) return DataResponse::ValidateFail(__('messages.error',['info' => 'Please enter bank']));
        if($bankAmountKh > 0 && !$bankId) return DataResponse::ValidateFail(__('messages.error',['info' => 'Please enter bank']));
        if($bankId && (!$bankAmount && !$bankAmountKh)) return DataResponse::ValidateFail(__('messages.error',['info' => 'Please enter bank amount in USD or KHR']));
        if($bankId) {
            $existsBank = Bank::where('is_deleted',0)->find($bankId);
            if(!$existsBank) return DataResponse::NotFound(__('messages.not_found',['info' =>'Bank']));
            $bankName = $existsBank->name;
        }
        $cashKhToUS = $cashKh / $exhangeRate;
        $bankAmountKhToUS = $bankAmountKh / $exhangeRate;
        $totalInputAmount = $cash + $cashKhToUS + $bankAmountKhToUS + $bankAmount;
        $totalInputAmount = floor($totalInputAmount * 100) / 100;
        // if($cash && $cashKh) return
        $originalCashKh = 0;
        $originalBankAmtKh = 0;
        if($dueAmount > 0){
            if($totalInputAmount <=0) return DataResponse::ValidateFail('Invalid payment amount');
            $paymentSuggestion = $this->paymentSuggestion($cash,$cashKh,$bankAmount,$bankAmountKh,$dueAmount,$exhangeRate);
            if($paymentSuggestion->error) return $paymentSuggestion;
            $originalCashKh = $paymentSuggestion->original_cash_amount_kh;
            $originalBankAmtKh = $paymentSuggestion->original_bank_amount_kh;
        }
        return DataResponse::JsonRaw([
            'error' => false,
            'total_input_amount' => $totalInputAmount,
            'original_cash_amount_kh' => $originalCashKh,
            'original_bank_amount_kh' => $originalBankAmtKh,
            'bank_name' => $bankName
        ]);
    }

    public function paymentSuggestion($cash,$cashKh,$bankAmount,$bankAmountKh,$dueAmount,$exchangeRate){
        $dueAmount = Helper::getNumber($dueAmount,2);
        $totalAmountUSD = Helper::getNumber($cash + $bankAmount);
        $totalAmountKHR = Helper::getNumber($cashKh + $bankAmountKh);
        $hasMoreThanTwoDecimals = function ($amount) {
            $amountStr = (string)$amount;
            if (strpos($amountStr, '.') !== false) {
                $decimalPart = explode('.', $amountStr)[1]; // Get the decimal part
                return strlen($decimalPart) > 2; // Check if there are more than 2 digits after the decimal point
            }
            return false; // No decimal part, so no need to check
        };
        if ($hasMoreThanTwoDecimals($cash) || $hasMoreThanTwoDecimals($cashKh) || $hasMoreThanTwoDecimals($bankAmount) || $hasMoreThanTwoDecimals($bankAmountKh) || $hasMoreThanTwoDecimals($dueAmount)) {
            return DataResponse::ValidateFail(__('messages.info',[
                'info' =>  'Your input amount includes more than two decimal digits',
                'khInfo' => 'សូមបញ្ចូលទឹកប្រាក់ដែល​មានទសភាគមិនលើសពីពីរខ្ទង់'
            ]));
        }
        $originalCashKh = 0;
        $originalBankAmtKh = 0;
        if($totalAmountUSD > 0 && $totalAmountKHR > 0){
            $totalAmountKHR_to_USD = $totalAmountKHR/$exchangeRate;
            $totalAllAmt = Helper::getNumber($totalAmountUSD + $totalAmountKHR_to_USD);
            if($totalAllAmt > $dueAmount) return DataResponse::ValidateFail('Your amount is exceeding the expected, amount is only $'.$dueAmount.' in total');
            $remainingAmt = abs($totalAmountUSD - $dueAmount);
            $totalSuggestionAmt_KH = $remainingAmt * $exchangeRate;
            $suggestionAmtBankKh = abs($totalSuggestionAmt_KH  - $cashKh);
            $suggestionAmtCashKh = abs($totalSuggestionAmt_KH - $bankAmountKh);
            if($bankAmountKh) $originalBankAmtKh += $suggestionAmtBankKh; //** keep original amount */
            if($cashKh) $originalCashKh += $suggestionAmtCashKh; //** keep original amount */
            $roundSuggestionAmtUp = ceil($totalSuggestionAmt_KH / 100) * 100;
            $roundSuggestionAmtDown = floor($totalSuggestionAmt_KH / 100) * 100;
            if(!($totalAmountKHR >= $roundSuggestionAmtDown && $totalAmountKHR <= $roundSuggestionAmtUp)) {
                return DataResponse::ValidateFail(message: __('messages.info',[
                    'info' => 'Amount KHR must be around (KHR '.$roundSuggestionAmtUp .' & KHR '.$roundSuggestionAmtDown.'), base '.$totalSuggestionAmt_KH
                ]));
            }
            // Log::error($totalAllAmt.'---'.$dueAmount.'--------'.$totalAmountKHR_to_USD.'------'.$totalAmountKHR);
            if($totalAllAmt != $dueAmount) return DataResponse::ValidateFail(__('messages.info',[
                'info' => 'If USD amount($'.$totalAmountUSD.')'.' additional in KHR must be ('.$roundSuggestionAmtUp.' or '.$totalSuggestionAmt_KH.')',
                'khInfo' => 'If USD amount($'.$totalAmountUSD.')'.' additional in KHR must be ('.$roundSuggestionAmtUp.' or '.$totalSuggestionAmt_KH.')'
            ]));
        }

        if($totalAmountUSD > 0 && !$totalAmountKHR){
            if($cash && $bankAmount){
                $additionalSuggestion = Helper::getNumber(abs($dueAmount - $cash));
                $misMatch = $additionalSuggestion !== $bankAmount;
                if ($misMatch) {
                    return DataResponse::ValidateFail(__('messages.info', [
                        'info' => 'If Cash Amount USD ' . Helper::getNumber($cash, 2) . ', so bank amount must be USD ' . Helper::getNumber($additionalSuggestion, 2),
                        'khInfo' => 'លុយដុល្លា ' . Helper::getNumber($cash, 2) . ', ដូច្នេះលុយទូរទាត់តាមធនាគារត្រូវតែ ' . Helper::getNumber($additionalSuggestion, 2),
                    ]));
                }
            }
            if($totalAmountUSD < $dueAmount) return DataResponse::ValidateFail(__('messages.info',[
                'info' => 'Payment amount must be $'.$dueAmount.' remaining amount ($'.$dueAmount - $totalAmountUSD.')'
            ]));
            $totalAmountUSD = (float) $totalAmountUSD; // Ensures it's a float
            $dueAmount = (float) $dueAmount; // Casts dueAmount to float
            $misMatchTotal = abs($totalAmountUSD - $dueAmount) > 0;
            if($misMatchTotal) return DataResponse::ValidateFail('Your amount is exceeding the expected, amount is only $'.$dueAmount.' in total');
        }

        if($totalAmountKHR > 0 && !$totalAmountUSD){
            $totalAmountKHR_to_USD = $totalAmountKHR / $exchangeRate;
            $totalAmountKHR_to_USD = floor($totalAmountKHR_to_USD * 100) / 100;
            $remainingAmt = abs($totalAmountKHR_to_USD - $dueAmount);
            $suggestionAmt = $dueAmount * $exchangeRate;
            $suggestionAmtBankKh = abs($suggestionAmt - $cashKh);
            $suggestionAmtCashKh = abs($suggestionAmt - $bankAmountKh);
            $roundSuggestionAmtUp = ceil($suggestionAmt / 100) * 100;
            $roundSuggestionAmtDown = ceil($suggestionAmt / 100) * 100;
            if($bankAmountKh) $originalBankAmtKh += $suggestionAmtBankKh; //** keep original amount */
            if($cashKh) $originalCashKh += $suggestionAmtCashKh; //** keep original amount */
            if($cashKh && $bankAmountKh){
                $minSuggestionAmt = abs($cashKh - $suggestionAmt);
                $minSuggestionAmtDown = floor($minSuggestionAmt / 100) * 100;
                $maxSuggestionAmt = ceil($minSuggestionAmt / 100) * 100;
                if(!($bankAmountKh >= $minSuggestionAmtDown && $bankAmountKh <= $maxSuggestionAmt)){
                    return DataResponse::ValidateFail(__('messages.info',[
                        'info' => 'If Cash Amount KHR '.Helper::getNumber($cashKh,2).' bank amount must be around KHR '.Helper::getNumber($minSuggestionAmtDown,2).' or KHR '.Helper::getNumber($maxSuggestionAmt,2)
                    ]));
                }
            }else{
                if(!($totalAmountKHR >= $suggestionAmt && $totalAmountKHR <= $roundSuggestionAmtUp)){
                    return DataResponse::ValidateFail(__('messages.info',[
                        'info' => 'Amount KHR must around (KHR '.$roundSuggestionAmtUp .' & KHR '.$suggestionAmt.') base on exchange_rate ('.$exchangeRate.')'
                    ]));
                }

            }
        }

        return DataResponse::JsonRaw([
            'error'=>false,
            'original_cash_amount_kh' => $originalCashKh,
            'original_bank_amount_kh' => $originalBankAmtKh
        ]);
    }
}
