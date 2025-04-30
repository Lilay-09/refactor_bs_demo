<?php

namespace App\Services;

use ApiResponse;
use App\Models\Bank;
use App\Models\Disbursement;
use App\Models\DisbursementDetails;
use App\Models\DisbursementPackage;
use App\Models\DriverCommission;
use App\Models\Order;
use App\Models\Package;
use App\Models\Payment;
use App\Models\PaymentDetail;
use App\Models\PaymentPackage;
use App\Models\User;
use App\Models\Zone;
use Carbon\Carbon;
use DataResponse;
use DB;
use Exception;
use Helper;
use Illuminate\Http\Request;
use Log;
use Str;
use function Laravel\Prompts\select;

class TransactionService
{

    public function getDeliveryPackages(Request $req,$type,$user){
        $selectKey = $type.'_payment_id,'.$type.'_disbursement_id';
        $driverId = $req->driver_id;
        $merchantId = $req->merchant_id;
        $pmtStatusId = $req->payment_status_id;
        $startDate = $req->startDate;
        $endDate = $req->endDate;
        $search = $req->search;
        // Log::info($req->all());
        $qP = Package::query()
            ->from('packages as p')
            ->with(['payment' => function ($query) use ($type) {
                // Apply the dynamic payer_type condition
                $query->where('payer_type', $type)
                ->where('is_deleted',0);
            }])
            ->with(['disbursement' => function ($query) use ($type) {
                // Apply the dynamic payer_type condition
                $query->where('payee_type', $type)
                ->where('is_deleted',0);
            }])
            ->where('p.is_deleted',0)
            ->join('users as d','d.id','p.driver_id')
            ->join('tracking_statuses as ts','ts.id','p.status_id')
            ->join('users as m','m.id','p.merchant_id')
            ->whereIn('p.status_id',[9,19])
            ->select(['p.extra_charge','p.additional_fee','p.remarks','p.cod','p.price','d.phone as driver_phone','p.taxi_fee','p.payer','p.delivery_fee','p.assign_driver_datetime','p.merchant_total','m.user_name as merchant_name','m.phone as merchant_phone','d.user_name as driver_name','p.status_id','p.id as package_id','d.id as driver_id','p.qr_code','ts.name as status_code','p.delivered_datetime','p.failed_datetime','p.zone_code','p.receiver_phone','p.delivery_type']);
            if($type == 'driver'){
                $qP->whereNotExists(function ($sub) use ($type) {
                    $sub->select(DB::raw(1))
                        ->from('payment_packages as pp')
                        ->whereColumn('pp.package_id', 'p.id')
                        ->where('pp.payer_type', 'driver')
                        ->where('pp.is_deleted', false);
                })
                ->whereNotExists(function ($sub) use ($type) {
                    $sub->select(DB::raw(1))
                        ->from('disbursement_packages as dp')
                        ->whereColumn('dp.package_id', 'p.id')
                        ->where('dp.payee_type', 'driver')
                        ->where('dp.is_deleted', false);
                });
            }
        if($driverId || $merchantId){
            if($type == 'driver') {
                $qP->where('p.driver_id',$driverId);
            }
            else {
                $qP->where('p.merchant_id',$merchantId);
            }
        }
        if($search){
            $qP->where('p.qr_code',$search);
        }
        if($startDate && $endDate){
            $startDate = Helper::dateYMD($startDate);
            $endDate = Helper::dateYMD($endDate);
            $qP->where(function ($q) use ($startDate, $endDate) {
                $startDateTime = $startDate . ' 00:00:00';
                $endDateTime = $endDate . ' 23:59:59';

                // Check for status_id = 9, delivered_datetime should be within the date range
                $q->where(function ($q) use ($startDateTime, $endDateTime) {
                    $q->where('p.status_id', 9)
                    ->whereRaw('p.delivered_datetime >= ? AND p.delivered_datetime <= ?', [$startDateTime, $endDateTime]);
                })
                // Check for status_id = 19, failed_datetime should be within the date range
                ->orWhere(function ($q) use ($startDateTime, $endDateTime) {
                    $q->where('p.status_id', 19)
                    ->whereRaw('p.failed_datetime >= ? AND p.failed_datetime <= ?', [$startDateTime, $endDateTime]);
                });
            });
        }
        // // Log::error(json_encode($req->all()));
        // if($type == 'merchant' && $pmtStatusId == 2){
        //     $qP->where(function ($q) {
        //         $q->whereNotNull('p.merchant_payment_id')->orWhereNotNull('p.merchant_disbursement_id');
        //     });
        // }else if($type == 'merchant' && $pmtStatusId == 1){
        //     $qP->where(function ($q) {
        //         $q->whereNull('p.merchant_payment_id')->whereNull('p.merchant_disbursement_id');
        //     });
        // }
        // // $packages = $qP->get();
        $statusKey = $type.'_payment_status';
        // // foreach($packages as $package){

        // // }
        // $clbMapper = null;
        $clbMapper = function($package) use($statusKey,$type){
            $cod = $package->cod;
            $package->cod = $cod ? 'Yes' : 'No';
            // $package->{$statusKey} = (!$package->{$type.'_payment_id'} && !$package->{$type.'_disbursement_id'}) ? 'Unpaid':'Paid';
            if($type == 'merchant') $package->{$statusKey} = ($package->payment || $package-> disbursement) ? 'Paid':'Unpaid';
            $package->datetime = ($package->status_id == 9 && ($package->delivered_datetime || $package->delivered_datetime)) ? Helper::formatCustomDateTime($package->delivered_datetime) : Helper::formatCustomDateTime($package->failed_datetime);
            $package->delivered_datetime = Helper::formatCustomDateTime($package->assign_driver_datetime);
            $package->{$type.'_total'} = self::getPackageTotal($type,$cod,$package->price,$package->taxi_fee,$package->extra_charge,$package->additional_fee,$package->delivery_fee,$package->payer);
            if($type == 'merchant') $package->total = -self::getPackageTotal($type,$cod,$package->price,$package->taxi_fee,$package->extra_charge,$package->additional_fee,$package->delivery_fee,$package->payer);
            if($package->status_id == 19){
                if($type == 'merchant'){
                    $package->{$type.'_total'} = $package->payer == 'sender' ? $package->delivery_fee+ $package->extra_charge : 0;
                    $package->total = $package->payer == 'sender' ? -self::getPackageTotal($type,$cod,0,0,$package->extra_charge,$package->additional_fee,$package->delivery_fee,$package->payer):0;
                }else $package->{$type.'_total'} = $package->payer == 'receiver' ? $package->delivery_fee + $package->extra_charge : 0;
            }
            $package->fee = Helper::getNumber($package->delivery_fee + $package->extra_charge + $package->additional_fee,2);
            return $package;
        };
        return DataResponse::PaginationV1($qP,$req,'',[],1000,$clbMapper);
    }


    // public function getDeliveryPackages(Request $req,$type,$user){
    //     $selectKey = $type.'_payment_id,'.$type.'_disbursement_id';
    //     $driverId = $req->driver_id;
    //     $merchantId = $req->merchant_id;
    //     $pmtStatusId = $req->payment_status_id;
    //     $startDate = $req->startDate;
    //     $endDate = $req->endDate;
    //     $search = $req->search;
    //     $pmtKey = $type.'_payment_id';
    //     $disKey = $type.'_disbursement_id';
    //     $qP = Package::query()->fromRaw('packages as p')->where('p.company_id',$user->company_id)
    //     ->where('p.is_deleted',0)
    //     ->join('users as d','d.id','p.driver_id')
    //     ->join('tracking_statuses as ts','ts.id','p.status_id')
    //     ->join('users as m','m.id','p.merchant_id')
    //     ->orderByRaw("p.{$pmtKey} IS NULL DESC, p.{$pmtKey}")
    //     ->orderByRaw("p.{$disKey} IS NULL DESC, p.{$disKey}")
    //     // ->whereNull($type.'_payment_id')
    //     // ->whereNull($type.'_payment_id')
    //     // ->leftJoin('payments as dpmt','dpmt.id','p.'.$fkKey) //** if driver paid or unpaid */
    //     // ->leftJoin('payments as mpmt','mpmt.id','p.merchant_payment_id') //** if driver paid or unpaid */
    //     ->whereIn('p.status_id',[9,19]) //* delivered and failed with fee
    //     ->selectRaw('p.extra_charge,p.additional_fee,p.remarks,p.cod,p.price,d.phone as driver_phone,p.taxi_fee,p.payer,p.delivery_fee,p.assign_driver_datetime,p.merchant_total,m.user_name as merchant_name,m.phone as merchant_phone,d.user_name as driver_name,p.status_id,p.id as package_id,d.id as driver_id,p.qr_code,ts.name as status_code,p.delivered_datetime,p.failed_datetime,p.zone_code,p.receiver_phone,p.delivery_type,'.$selectKey);
    //     if($type == 'driver'){
    //         $qP->where(function ($q) use($type){
    //             $q->whereNull($type.'_payment_id')->whereNull($type.'_disbursement_id');
    //         });
    //     }
    //     if($driverId || $merchantId){
    //         if($type == 'driver') $qP->where('p.driver_id',$driverId);
    //         else $qP->where('p.merchant_id',$merchantId);
    //     }
    //     if($search){
    //         $qP->where('p.qr_code',$search);
    //     }
    //     if($startDate && $endDate){
    //         $startDate = Helper::dateYMD($startDate);
    //         $endDate = Helper::dateYMD($endDate);
    //         $qP->where(function ($q) use ($startDate, $endDate) {
    //             $startDateTime = $startDate . ' 00:00:00';
    //             $endDateTime = $endDate . ' 23:59:59';

    //             // Check for status_id = 9, delivered_datetime should be within the date range
    //             $q->where(function ($q) use ($startDateTime, $endDateTime) {
    //                 $q->where('status_id', 9)
    //                 ->whereRaw('delivered_datetime >= ? AND delivered_datetime <= ?', [$startDateTime, $endDateTime]);
    //             })
    //             // Check for status_id = 19, failed_datetime should be within the date range
    //             ->orWhere(function ($q) use ($startDateTime, $endDateTime) {
    //                 $q->where('status_id', 19)
    //                 ->whereRaw('failed_datetime >= ? AND failed_datetime <= ?', [$startDateTime, $endDateTime]);
    //             });
    //         });

    //     }
    //     // Log::error(json_encode($req->all()));
    //     if($type == 'merchant' && $pmtStatusId == 2){
    //         $qP->where(function ($q) {
    //             $q->whereNotNull('p.merchant_payment_id')->orWhereNotNull('p.merchant_disbursement_id');
    //         });
    //     }else if($type == 'merchant' && $pmtStatusId == 1){
    //         $qP->where(function ($q) {
    //             $q->whereNull('p.merchant_payment_id')->whereNull('p.merchant_disbursement_id');
    //         });
    //     }
    //     // $packages = $qP->get();
    //     $statusKey = $type.'_payment_status';
    //     // foreach($packages as $package){

    //     // }
    //     $clbMapper = function($package) use($statusKey,$type){
    //         $cod = $package->cod;
    //         $package->cod = $cod ? 'Yes' : 'No';
    //         $package->{$statusKey} = (!$package->{$type.'_payment_id'} && !$package->{$type.'_disbursement_id'}) ? 'Unpaid':'Paid';
    //         $package->datetime = ($package->status_id == 9 && ($package->delivered_datetime || $package->delivered_datetime)) ? Helper::formatCustomDateTime($package->delivered_datetime) : Helper::formatCustomDateTime($package->failed_datetime);
    //         $package->delivered_datetime = Helper::formatCustomDateTime($package->assign_driver_datetime);
    //         $package->{$type.'_total'} = self::getPackageTotal($type,$cod,$package->price,$package->taxi_fee,$package->extra_charge,$package->additional_fee,$package->delivery_fee,$package->payer);
    //         if($type == 'merchant') $package->total = -self::getPackageTotal($type,$cod,$package->price,$package->taxi_fee,$package->extra_charge,$package->additional_fee,$package->delivery_fee,$package->payer);
    //         if($package->status_id == 19){
    //             if($type == 'merchant'){
    //                 $package->{$type.'_total'} = $package->payer == 'sender' ? $package->delivery_fee+ $package->extra_charge : 0;
    //                 $package->total = $package->payer == 'sender' ? -self::getPackageTotal($type,$cod,0,0,$package->extra_charge,$package->additional_fee,$package->delivery_fee,$package->payer):0;
    //             }else $package->{$type.'_total'} = $package->payer == 'receiver' ? $package->delivery_fee + $package->extra_charge : 0;
    //         }
    //         $package->fee = Helper::getNumber($package->delivery_fee + $package->extra_charge + $package->additional_fee,2);
    //         return $package;
    //     };
    //     return DataResponse::PaginationV1($qP,$req,null,[],1000,$clbMapper);
    // }

    public static function getPackageTotal($type,$cod,$price,$taxiFee,$extraCharge,$additionalFee,$baseFee,$payer){
        $total = 0;
        // $baseFee += $extraCharge;
        if($type == 'driver'){
            if($cod) $total += $price;
            if($payer == 'receiver') {
                $total += $baseFee + $extraCharge;
            }
            $total -= $taxiFee;
        }
        if($type == 'merchant'){
            if($cod) $total -= $price;
            if($payer == 'sender') {
                $total += $baseFee + $extraCharge;
            }
            $total += $taxiFee;
        }

        return Helper::getNumber($total,2);
    }
    public function receivePaymentValidation(Request $req,$type='driver'){
        return validator($req->all(),[
            $type.'_id' => 'required|int',
            'cash' => 'nullable|numeric',
            'cash_kh' => 'nullable|numeric',
            'bank_amount' => 'nullable|numeric',
            'bank_amount_kh' => 'nullable|numeric',
            'bank_id' => 'nullable|int',
            'remarks' => 'nullable|string|max:500',
            'packages' => 'required|array',
            'exchange_rate' => 'nullable|numeric'
        ]);
    }

    //* type must be one of driver or merchant
    public function receivePaymentService(Request $req,$user,$type){
        $validType = $this->validType($type);
        if($validType->error) return $validType;
        $validate = self::receivePaymentValidation($req,$type);
        if($validate->fails()) return DataResponse::ValidateFail($validate->errors()->first());
        $inputs = $validate->validated();
        $payerId = $inputs['driver_id'] ?? $inputs['merchant_id'];
        $packageIds = $inputs['packages'];
        $validPackages = $this->validPackages($packageIds,$payerId,$type);
        if($validPackages->error) return $validPackages;
        $exchangeRate = $inputs['exchange_rate'] ?? GeneralSettingService::getLatestXRate()->buy_rate;
        $cashKh = $inputs['cash_kh'] ?? 0;
        $cash = $inputs['cash'] ?? 0;
        $bankId = $inputs['bank_id'] ?? null;
        $bankAmount = $inputs['bank_amount'] ?? 0;
        $bankAmountKh = $inputs['bank_amount_kh'] ?? 0;
        $dueAmount = $validPackages->total_due_amount;
        if($dueAmount < 0) return DataResponse::ValidateFail(__('messages.info',[
            'info' => 'This case should be receive not disbursement'
        ]));
        $validPayment = $this->validPayment($cash,$cashKh,$bankAmount,$bankAmountKh,$bankId,$dueAmount,$exchangeRate);
        if($validPayment->error) return $validPayment;
        // return $validPayment;
        // if($validPayment->total_input_amount !== $dueAmount) return DataResponse::ValidateFail(__('messages.info',[
        //     'info' => 'Total input amount must be $'.$dueAmount
        // ]));
        $paymentType = 'payment';
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
            $pmtArr = [
                'payer_id' => $payerId,
                'payer_type' => $type,
                'taxi_fee' => $validPackages->total_taxi_fee,
                'delivery_fee' => $validPackages->total_delivery_fee,
                'payable_amount' => $dueAmount,
                'paid_amount' => $dueAmount,
                'create_uid' => $user->id,
                'receiver_uid' => $user->id,
                'amount' => $validPackages->total_amount,
                'exchange_rate' => $exchangeRate,
                'remarks' => $inputs['remarks'] ?? null,
                'package_count' => $validPackages->total_package,
                'delivered_package_count' => $validPackages->delivered_package_count,
                'update_uid' => $user->id,
                'cod_amount' => $validPackages->total_cod,
                'payment_datetime' => now(),
                'breakdown_notes' => $breakDownNotes,
                'company_id' => $user->company_id,
                'received_datetime' => now(),
                'branch_id' => $user->branch_id
            ];
            if($type == 'merchant'){
                $pmtArr['is_approved'] = 1;
                $pmtArr['is_settled'] = 1;
                $pmtArr['settled_uid'] = $user->id;
                $pmtArr['approved_uid'] = $user->id;
                $pmtArr['approved_datetime'] = now();
                $pmtArr['settled_datetime'] = now();
            }
            $createPayment = Payment::create($pmtArr);
            $paymentId = $createPayment->id;
            self::transactionCodeGenerator('transaction_sequences',$paymentType,'payments','trx_code',$user->branch_id,$user->company_id,$paymentId);
            if($cash && $dueAmount > 0){
                PaymentDetail::create([
                    'payment_id' => $paymentId,
                    'method' => 'cash',
                    'amount' => $cash,
                    'original_amount' => $cash,
                    'currency_code' => 'USD'
                ]);
            }
            if($cashKh && $dueAmount > 0){
                PaymentDetail::create([
                    'payment_id' => $paymentId,
                    'method' => 'cash',
                    'amount' => $cashKh,
                    'original_amount' => $validPayment->original_cash_amount_kh,
                    'currency_code' => 'KHR'
                ]);
            }

            if($bankId){
                if($bankAmount > 0 && $dueAmount > 0){
                    PaymentDetail::create([
                        'payment_id' => $paymentId,
                        'method' => $validPayment->bank_name,
                        'amount' => $bankAmount,
                        'original_amount' => $bankAmount,
                        'currency_code' => 'USD'
                    ]);
                }

                if($bankAmountKh > 0 && $dueAmount > 0){
                    PaymentDetail::create([
                        'payment_id' => $paymentId,
                        'method' => $validPayment->bank_name,
                        'amount' => $bankAmountKh,
                        'original_amount' => $validPayment->original_bank_amount_kh,
                        'currency_code' => 'KHR'
                    ]);
                }
            }


            $fkField = [
                    $type.'_payment_id' => $paymentId
                ];
            Package::whereIn('id',$packageIds)->update($fkField);
            $paymentPackageArr = collect($packageIds)->map(fn($id) => [
                'package_id' => $id,
                'payment_id' => $paymentId,
                'type' => $paymentType,
                'payer_type' => $type,
            ])->toArray();
            PaymentPackage::insert($paymentPackageArr);

            $notif = new CloudMessagingService();
            $topics = GeneralSettingService::getGeneralTopics($user->company_id,'driver',$payerId);
            // return $topics;
            $notifReq = new Request([
                'topic' => $topics->private,
                'type' => 'private',
                'target_uid' => $payerId,
                'title' => __('messages.info',[
                    'info' => 'Transaction',
                    'khInfo' => ''
                ]),
                'body' => 'A total of '.$validPackages->total_package.' packages have been processed for this payment.'
            ]);
            $notif->sendNotificationByTopic($notifReq,$user);
            DB::commit();
            // return Package::whereIn('id',$packageIds)->get();
            return DataResponse::JsonResult(null,false,__('messages.created',[
                'info' => 'Payment',
                'khInfo' => 'ទទួលការបង់ប្រាក់'
            ]));
        }catch(Exception $e){
            Log::error($e->getMessage());
            Log::error($e->getTraceAsString());
            DB::rollBack();
            return DataResponse::Error(__('messages.error',['info' => 'Fail to receive']));
        }
    }


    public function receiveOrDisburesement(Request $req,$user,$type){
        $paymentType = $req->payment_type;
        if(!in_array($paymentType,['disbursement','receive']) || !$paymentType){
            return DataResponse::ValidateFail(__('messages.info',[
                'info' => 'Payment type must be on of disbursement or receive'
            ]));
        }

        if($paymentType == 'disbursement'){
            return $this->disbursementPayment($req,$user,$type);
        }else if($paymentType == 'receive'){
            return $this->receivePaymentService($req,$user,$type);
        }
    }


    public function getDriverCommissions($user,$driverId){
        $driver = User::where('is_deleted',0)->where('company_id',$user->company_id)
        ->selectRaw('code,user_name,employment_date,shift_type,salary')
        ->where('account_type','driver')->find($driverId);
        if(!$driver) return DataResponse::NotFound(__('messages.not_found',['info' => 'Driver']));
        $dc = (object)[
            'normal_pickup_commission' => 0,
            'normal_delivery_commission' => 0,
            'fast_pickup_commission' => 0,
            'fast_delivery_commission' => 0
        ];
        $driverCommissions = DriverCommission::where('driver_id',$driverId)->where('is_deleted',0)->orderByDesc('id')->get();
        foreach($driverCommissions as $driverComm){
            if($driverComm->delivery_type == 'fast'){
                $dc->fast_pickup_commission = $driverComm->pickup_commission;
                $dc->fast_delivery_commission = $driverComm->delivery_commission;
            }
            if($driverComm->delivery_type == 'normal'){
                $dc->normal_pickup_commission = $driverComm->pickup_commission;
                $dc->normal_delivery_commission = $driverComm->delivery_commission;
            }
        }
        foreach($dc as $key=>$d){
            $driver->{$key} = $dc->{$key};
        }

        return DataResponse::JsonResult($driver);
    }

    public static function amountToOneCurrency($currencyCode,$cashUSD,$cashKHR,$bankUSD,$bankKHR,$exchangeRate){
        if($currencyCode == 'USD'){
            $cashKHRToUSD = $cashKHR / $exchangeRate;
            $bankKHRToUSD = $bankKHR / $exchangeRate;
            return [
                'total' => Helper::getNumber($cashUSD + $bankUSD + $cashKHRToUSD + $bankKHRToUSD),
                'cash' => Helper::getNumber($cashUSD + $cashKHRToUSD),
                'bank' => Helper::getNumber($bankUSD  + $bankKHRToUSD)
            ];
        }
        return null;
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
        if($totalAmountUSD && $totalAmountKHR){
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
            if(!($totalAmountKHR >= $roundSuggestionAmtDown && $totalAmountKHR <= $roundSuggestionAmtUp)) return DataResponse::ValidateFail(message: __('messages.info',[
                'info' => 'Amount KHR must be around (KHR '.$roundSuggestionAmtUp .' & KHR '.$roundSuggestionAmtDown.'), base '.$totalSuggestionAmt_KH
            ]));
            // Log::error($totalAllAmt.'---'.$dueAmount.'--------'.$totalAmountKHR_to_USD.'------'.$totalAmountKHR);
            if($totalAllAmt != $dueAmount) return DataResponse::ValidateFail(__('messages.info',[
                'info' => 'If USD amount($'.$totalAmountUSD.')'.' additional in KHR must be ('.$roundSuggestionAmtUp.' or '.$totalSuggestionAmt_KH.')',
                'khInfo' => 'If USD amount($'.$totalAmountUSD.')'.' additional in KHR must be ('.$roundSuggestionAmtUp.' or '.$totalSuggestionAmt_KH.')'
            ]));
        }

        if($totalAmountUSD && !$totalAmountKHR){
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

        if($totalAmountKHR && !$totalAmountUSD){
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

    public function validPackages($packageIds,$driverOrMerchantId,$type){
        $validType = $this->validType($type);
        if($validType->error) return $validType;
        $obj = (object)[
            'total_packages' => 0,
            'total_cod' => 0 ,
            'total_delivery_fee' => 0,
            'delivered_package_count' => 0,
            'failed_with_fee_count' => 0,
            'total_taxi_fee' => 0,
            'driver_total' => 0,
            'merchant_total' => 0,
            'total_due_amount' => 0,
            'total_package_price'=>0,
            'total_amount' => 0
        ];
        foreach($packageIds as $key=>$id){
            $package = Package::where('is_deleted',0)->whereIn('status_id',[9,19])->find($id);
            if(!$package){
                return DataResponse::ValidateFail(__('messages.info',['info' => 'Invalid package'.' on row ('.($key+1).')']));
            }
            if($package->{$type.'_payment_id'} > 0 || $package->{$type.'_disbursement_id'}) return DataResponse::ValidateFail(__('messages.error',[
                'info' => 'Check list might include package that has been paid',
            ]));

            if($package->status_id == 9) $obj->delivered_package_count += 1;
            if($package->status_id == 19) $obj->failed_with_fee_count +=1;
            // $obj->total_delivery_fee += ($package->cod ? $package->delivery_fee : 0) + $package->extra_charge + $package->additional_fee;
            if($type == 'merchant' && $package->payer == 'sender') $obj->total_delivery_fee += $package->extra_charge + $package->delivery_fee;
            else if($type == 'driver' && $package->payer == 'receiver') $obj->total_delivery_fee += $package->extra_charge + $package->delivery_fee;
            // $calPackage = GeneralSettingService::calculatePackageFee($package->zone_code,$package->price,$package->billed_kg,$package->actual_kg,$package->payer,$package->cod);
            // $totalPackages += 1;
            $obj->total_packages +=1;
            $obj->total_taxi_fee += $package->taxi_fee;
            $obj->driver_total += $package->driver_total;
            $obj->merchant_total += $package->merchant_total;
            $rowTotal = self::getPackageTotal($type,$package->cod,$package->price,$package->taxi_fee,$package->extra_charge,$package->additional_fee,$package->delivery_fee,$package->payer);
            if($package->status_id == 19){
                if($type == 'merchant'){
                    $rowTotal = self::getPackageTotal($type,$package->cod,0,0,$package->extra_charge,$package->additional_fee,$package->delivery_fee,$package->payer);
                    $rowTotal = $package->payer == 'sender' ? $package->delivery_fee+ $package->extra_charge : 0;
                }else $rowTotal = $package->payer == 'receiver' ? $package->delivery_fee+ $package->extra_charge : 0;
            }
            $obj->total_due_amount += $rowTotal;
            if($package->cod) $obj->total_cod += $package->price;
            $obj->total_amount += $package->price + $package->delivery_fee;
            $obj->total_package_price += $package->price;
        }
        // if($paymentType == 'disbursement') $obj->total_due_amount = abs($obj->total_due_amount);
        // Log::error(json_encode($obj));
        return DataResponse::JsonRaw([
            'error' => false,
            'pacakage_ids' => $packageIds,
            'total_delivery_fee' => round($obj->total_delivery_fee,2),
            'total_package' => $obj->total_packages,
            'driver_total' => $obj->driver_total,
            'total_taxi_fee' => $obj->total_taxi_fee,
            'merchant_total' => $obj->merchant_total,
            'total_package_price' => $obj->total_package_price,
            'failed_with_fee_count' => $obj->failed_with_fee_count,
            'total_cod' => $obj->total_cod,
            'total_due_amount' => $obj->total_due_amount,
            'total_amount' => $obj->total_amount,
            'delivered_package_count' => $obj->delivered_package_count,
        ]);
    }

    public function getPayments(Request $req,$user,$type='driver',$isApproved=false){
        $payeeOrPayerId = $req->{$type.'_id'};
        $startDate = $req->startDate;
        $endDate = $req->endDate;
        $allPayments = [];
        $payerOnApprove = '';
        if($isApproved){
            $payerOnApprove = ',ap.user_name as payer_name';
        }
        $qP = Payment::fromRaw('payments as p')
        ->join('users as d','d.id','p.payer_id')
        ->where('p.is_deleted',0)
        ->where('p.approved',$isApproved)
        ->join('users as ap','ap.id','p.receiver_uid')
        ->where('payer_type',$type)
        ->selectRaw('p.trx_code,p.is_settled,p.payment_datetime,p.package_count,ap.user_name as booked_user,p.payable_amount,p.id as payment_id,d.user_name as driver_name,p.exchange_rate,ap.user_name as receiver_name,p.taxi_fee,p.approved,p.breakdown_notes'.$payerOnApprove)
        ->orderByDesc('p.payment_datetime');
        // if(!$isApproved) $qP->join('users as d','d.id','p.payer_id');

        if($payeeOrPayerId) $qP->where('p.payer_id',$payeeOrPayerId);
        if($startDate && $endDate){
            $startDate = Helper::dateYMD($startDate);
            $endDate = Helper::dateYMD($endDate);
            $qP->where(function($q) use($startDate,$endDate){
                // $q->whereRaw('payment_datetime::DATE >= ? AND payment_datetime::DATE <= ?', [$startDate, $endDate]);
                $q->whereRaw('payment_datetime >= ? AND payment_datetime <= ?', [$startDate . ' 00:00:00', $endDate . ' 23:59:59']);

            });
        }

        $payments = $qP->get();
        $paymentDetails = PaymentDetail::get();
        foreach($payments as $pmt){
            $pmt_details = $this->preparePaymentPackageAmount($paymentDetails,$pmt->payment_id);
            $totalUSD = $pmt_details->total_usd;
            $totalKHR = $pmt_details->total_khr;
            $cashUSD = $pmt_details->cash_usd;
            $bankUSD = $pmt_details->bank_usd;
            $cashKHR = $pmt_details->cash_khr;
            $bankKHR = $pmt_details->bank_khr;
            if($isApproved){
                $pmt->status_code = $pmt->is_settled ? 'Settled' : 'Pending';
            }
            $pmt->payment_date = Helper::formatCustomDateTime($pmt->payment_datetime,'d-M-Y');
            $pmt->payment_time = Helper::formatCustomDateTime($pmt->payment_datetime,'h:i:s A');
            $pmt->total_usd = Helper::displayMoney($totalUSD,'USD');
            $pmt->total_khr = Helper::displayMoney($totalKHR,'KHR');
            $pmt->cash_usd = Helper::displayMoney($cashUSD,'USD');
            $pmt->cash_khr = Helper::displayMoney($cashKHR,'KHR');
            $pmt->bank_usd = Helper::displayMoney($bankUSD,'USD');
            $pmt->bank_khr = Helper::displayMoney($bankKHR,'KHR');
            $totalKHR_to_USD = $totalKHR/$pmt->exchange_rate;
            $totalKHR_to_USD = floor($totalKHR_to_USD * 100) / 100;
            $pmt->total = (float)Helper::getNumber($totalUSD + $totalKHR_to_USD);
            $pmt->payment_type = 'receive';
            $allPayments[] = $pmt;
        }

        $disbursementDetails = DisbursementDetails::get();
        $disbursements = Disbursement::fromRaw('disbursements as dis')
        ->where('dis.is_deleted',0)
        ->where('dis.type','payment')
        ->where('dis.approved',$isApproved)
        ->join('users as d','d.id','dis.payee_id')
        ->leftJoin('users as ap','ap.id','dis.receiptionist_uid')
        ->where('payee_type',$type)
        ->leftJoin('users as py','py.id','dis.approved_uid')
        ->selectRaw('dis.is_settled,dis.payment_datetime,dis.package_count,ap.user_name as booked_user,dis.payable_amount,dis.id as payment_id,d.user_name as driver_name,py.user_name as payer_name,dis.exchange_rate,dis.taxi_fee,dis.approved,dis.breakdown_notes')
        ->orderByDesc('dis.payment_datetime')
        ->get();
        foreach($disbursements as $d){
            $pmt_details = $this->preparePaymentPackageAmount($disbursementDetails,$d->payment_id,'disbursement');
            $totalUSD = $pmt_details->total_usd;
            $totalKHR = $pmt_details->total_khr;
            // if($isApproved){
            $d->status_code = $d->is_settled ? 'Settled' : 'Pending';
            // }
            $d->total_usd = Helper::displayMoney($totalUSD,'USD');
            $d->total_khr = Helper::displayMoney($totalKHR,'KHR');
            $totalKHR_to_USD = $totalKHR/$d->exchange_rate;
            $totalKHR_to_USD = floor($totalKHR_to_USD * 100) / 100;
            $d->total = $totalUSD + $totalKHR_to_USD;
            $d->payment_type = 'disbursement';
            $d->payment_date = Helper::dateDMY($d->payment_datetime);
            $allPayments[] = $d;
        }
        $allPayments = collect($allPayments);
        return DataResponse::Pagination($allPayments,$req);
    }


    public function getTransaction(Request $req,$type='merchant'){
        $payeeOrPayerId = $req->{$type.'_id'};
        $startDate = $req->startDate;
        $endDate = $req->endDate;
        $transactionType = $req->transaction_type ?? null;
        $allPayments = [];
        // Log::info($req->all());
        if(!$transactionType || $transactionType == 'receive'){
            $qP = Payment::fromRaw('payments as p')->join('users as d','d.id','p.payer_id')
            ->where('p.is_deleted',0)
            ->join('users as ap','ap.id','p.receiver_uid')
            ->where('payer_type',$type)
            ->selectRaw('p.trx_code,p.is_settled,p.payment_datetime,p.package_count,ap.user_name as booked_user,p.payable_amount,p.id as payment_id,p.delivery_fee,p.cod_amount,d.user_name as payer_name,d.user_name as merchant_name,p.exchange_rate,p.taxi_fee,p.approved,p.breakdown_notes,p.remarks')
            ->orderByDesc('p.payment_datetime');
            if($payeeOrPayerId) $qP->where('p.payer_id',$payeeOrPayerId);
            if($startDate && $endDate){
                $startDate = Helper::dateYMD($startDate);
                $endDate = Helper::dateYMD($endDate);
                $qP->where(function($q) use($startDate,$endDate){
                    $q->whereRaw('p.payment_datetime >= ? AND p.payment_datetime <= ?', [$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
                });
            }
            $payments = $qP->get();
            $paymentDetails = PaymentDetail::get();
            foreach($payments as $pmt){
                $pmt_details = $this->preparePaymentPackageAmount($paymentDetails,$pmt->payment_id);
                $totalUSD = $pmt_details->total_usd;
                $totalKHR = $pmt_details->total_khr;
                $pmt->payment_date = Helper::formatCustomDateTime($pmt->payment_datetime,'d-M-Y');
                $pmt->payment_time = Helper::formatCustomDateTime($pmt->payment_datetime,'h:i:s A');
                $pmt->total_usd = Helper::displayMoney($totalUSD,'USD');
                $pmt->total_khr = Helper::displayMoney($totalKHR,'KHR');
                $totalKHR_to_USD = $totalKHR/$pmt->exchange_rate;
                $totalKHR_to_USD = floor($totalKHR_to_USD * 100) / 100;
                $pmt->total = $totalUSD + $totalKHR_to_USD;
                $pmt->payment_type = 'receive';
                $allPayments[] = $pmt;
            }
        }

        if($transactionType == 'disbursement' || !$transactionType){
            $paymentDetails = DisbursementDetails::get();
            $qD = Disbursement::fromRaw('disbursements as dis')
            ->where('dis.is_deleted',0)
            ->where('dis.type','payment')
            ->join('users as d','d.id','dis.payee_id')
            ->join('users as ap','ap.id','dis.receiptionist_uid')
            ->where('payee_type',$type)
            ->selectRaw('dis.is_settled,dis.payment_datetime,dis.package_count,dis.cod_amount,dis.delivery_fee,ap.user_name as booked_user,dis.payable_amount,dis.id as payment_id,d.user_name as merchant_name,dis.exchange_rate,dis.taxi_fee,dis.approved,dis.breakdown_notes,dis.remarks')
            ->orderByDesc('dis.payment_datetime');
            if($startDate && $endDate){
                $startDate = Helper::dateYMD($startDate);
                $endDate = Helper::dateYMD($endDate);
                $qD->where(function($q) use($startDate,$endDate){
                    $q->whereRaw('dis.payment_datetime >= ? AND dis.payment_datetime <= ?', [$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
                });
            }
            $disbursements = $qD->get();
            foreach($disbursements as $d){
                $pmt_details = $this->preparePaymentPackageAmount($paymentDetails,$d->payment_id);
                $totalUSD = $pmt_details->total_usd;
                $totalKHR = $pmt_details->total_khr;
                $d->payment_date = Helper::formatCustomDateTime($d->payment_datetime,'d-M-Y');
                $d->payment_time = Helper::formatCustomDateTime($d->payment_datetime,'h:i:s A');
                $d->total_usd = Helper::displayMoney($totalUSD,'USD');
                $d->total_khr = Helper::displayMoney($totalKHR,'KHR');
                $totalKHR_to_USD = $totalKHR/$d->exchange_rate;
                $totalKHR_to_USD = floor($totalKHR_to_USD * 100) / 100;
                $d->total = $totalUSD + $totalKHR_to_USD;
                $d->payment_type = 'disbursement';
                // $d->delivery_fee = $d->delivery_fee + $d->extra_charge;
                $d->payment_date = Helper::dateDMY($d->payment_datetime);
                $allPayments[] = $d;
            }
        }

        $allPayments = collect($allPayments);
        return DataResponse::Pagination($allPayments,$req);
    }


    static function strReplaceCurrencySymbols(string $input): string {
        // Define the replacements
        $replacements = [
            'USD' => '$',
            'KHR' => '៛'
        ];

        // Replace occurrences using str_replace
        return str_replace(array_keys($replacements), array_values($replacements), $input);
    }
    public function getApprovedPayments(Request $req,$user){
        $qP = Payment::fromRaw('payments as p')->join('users as d','d.id','p.payer_id')
        ->where('p.is_deleted',0)
        ->where('p.approved',1)
        ->join('users as r','r.id','p.receiver_uid')
        ->leftJoin('users as st','st.id','p.settled_uid')
        ->orderBy('p.is_settled')
        ->selectRaw('p.is_deleted,st.user_name as settlement_username,p.is_settled,r.user_name as receiver_name,p.payment_datetime,p.id as payment_id,d.user_name as payer_name,p.exchange_rate,p.amount,p.taxi_fee,p.breakdown_notes as remarks');
        $payments = $qP->get();
        $paymentDetails = PaymentDetail::get();
        foreach($payments as $pmt){
            $pmt_details = $this->preparePaymentPackageAmount($paymentDetails,$pmt->payment_id);
            $totalUSD = $pmt_details->total_usd;
            $totalKHR = $pmt_details->total_khr;
            $pmt->payment_date = Helper::formatCustomDateTime($pmt->payment_datetime,'d-M-Y');
            $pmt->payment_time = Helper::formatCustomDateTime($pmt->payment_datetime,'h:i:s A');
            $pmt->total_usd = $totalUSD;
            $pmt->total_khr = $totalKHR;
            $totalKHR_to_USD = $totalKHR/$pmt->exchange_rate;
            $totalKHR_to_USD = floor($totalKHR_to_USD * 100) / 100;
            $pmt->total = (float)Helper::getNumber($totalUSD + $totalKHR_to_USD);
            $pmt->status_code = $pmt->is_settled ? 'Settled' : 'Pending';
            unset($pmt->payment_datetime);
        }
        return DataResponse::Pagination($payments,$req);
    }

    public static function getDriverCommissionInfo($driverCommissions,$driverId){
        $dc = (object)[
            'normal_pickup_commission' => 0,
            'normal_pickup_commission_start_date' => null,
            'normal_delivery_commission' => 0,
            'normal_delivery_commission_start_date' => null,
            'fast_pickup_commission' => 0,
            'fast_pickup_commission_start_date' => null,
            'fast_delivery_commission' => 0,
            'fast_delivery_commission_start_date' => null,

        ];
        foreach($driverCommissions as $driverComm){
            if($driverComm->driver_id == $driverId){
                    if($driverComm->delivery_type == 'fast'){
                    $dc->fast_pickup_commission = $driverComm->pickup_commission;
                    $dc->fast_pickup_commission_start_date = $driverComm->pickup_commission_start_date ?? $driverComm->updated_date;
                    $dc->fast_delivery_commission = $driverComm->delivery_commission;
                    $dc->fast_delivery_commission_start_date = $driverComm->delivery_commission_start_date ?? $driverComm->updated_date;
                }
                if($driverComm->delivery_type == 'normal'){
                    $dc->normal_pickup_commission = $driverComm->pickup_commission;
                    $dc->normal_pickup_commission_start_date = $driverComm->pickup_commission_start_date ?? $driverComm->updated_date;
                    $dc->normal_delivery_commission = $driverComm->delivery_commission;
                    $dc->normal_delivery_commission_start_date = $driverComm->delivery_commission_start_date ?? $driverComm->updated_date;
                }
            }
        }

        return $dc;
    }

    // Delete first stage of payment
    public function deletePayment($id,$trxType,$type,$user){
        $validType = $this->validType($type);
        if($validType->error) return $validType;
        if($trxType=='receive'){
            $payment = Payment::where('is_deleted',0)->orderByDesc('id')->find($id);
            if($payment->is_settled) return DataResponse::Duplicated(__('messages.info',[
                'info' => 'Payment has already been settled'
            ]));
            if(!$payment) return DataResponse::NotFound(__('messages.not_found',[
                'info' => 'Payment'
            ]));
            //** remove payment key from packages */
            $pmtKey = $type.'_payment_id';
            $payment->update([
                'is_deleted' => 1,
                'deleted_datetime' => now(),
                'deleted_uid' => $user->id
            ]);
            Package::where($pmtKey,$id)->update([
                // $type.'_disbursement_id' => null,
                $pmtKey => null,
            ]);

            PaymentPackage::where('payment_id',$id)->update([
                'is_deleted' => 1,
                'deleted_datetime' => now(),
                'deleted_uid' => $user->id,
                'deleted_reason' => 'rollback by '.$user->username
            ]);
        }else if($trxType == 'disbursement'){
            $payment = Disbursement::where('is_deleted',0)->where('type','payment')->orderByDesc('id')->find($id);
            if($payment->is_settled) return DataResponse::Duplicated(__('messages.info',[
                'info' => 'Payment has already been settled'
            ]));
            if(!$payment) return DataResponse::NotFound(__('messages.not_found',[
                'info' => 'Payment'
            ]));
            //** remove payment key from packages */
            $pmtKey = $type.'_disbursement_id';
            $payment->update([
                'is_deleted' => 1,
                'deleted_datetime' => now(),
                'deleted_uid' => $user->id
            ]);
            Package::where($pmtKey,$id)->update([
                $pmtKey => null,
                // $type.'_payment_id' => null,
            ]);

            DisbursementPackage::where('disbursement_id',$id)->update([
                'is_deleted' => 1,
                'deleted_datetime' => now(),
                'deleted_uid' => $user->id,
                'deleted_reason' => 'rollback by '.$user->username
            ]);
        }

        return DataResponse::JsonResult(null,false,__('messages.deleted',[
            'info' => 'Payment'
        ]));


    }


    // delete after approved
    public function deleteSettlePayment($id,$trxType,$type,$user){
        $validType = $this->validType($type);
        if($validType->error) return $validType;
        if(!in_array($trxType,['receive','disbursement'])) return DataResponse::ValidateFail(__('messages.info',[
            'info' => 'Please select payment type'
        ]));
        if($trxType =='receive'){
            $payment = Payment::where('is_deleted',0)->orderByDesc('id')->find($id);
            if(!$payment) return DataResponse::NotFound(__('messages.not_found',[
                'info' => 'Payment'
            ]));
            //** remove payment key from packages */
            $pmtKey = $type.'_payment_id';
            $payment->update([
                'is_deleted' => 1,
                'deleted_datetime' => now(),
                'deleted_uid' => $user->id
            ]);
            Package::where($pmtKey,$id)->update([
                // $type.'_disbursement_id' => null,
                $pmtKey => null,
            ]);

            PaymentPackage::where('payment_id',$id)->update([
                'is_deleted' => 1,
                'deleted_datetime' => now(),
                'deleted_uid' => $user->id,
                'deleted_reason' => 'rollback by '.$user->username
            ]);
        }else if($trxType == 'disbursement'){
            $payment = Disbursement::where('is_deleted',0)->where('type','payment')->orderByDesc('id')->find($id);
            if(!$payment) return DataResponse::NotFound(__('messages.not_found',[
                'info' => 'Payment'
            ]));
            //** remove payment key from packages */
            $pmtKey = $type.'_disbursement_id';
            $payment->update([
                'is_deleted' => 1,
                'deleted_datetime' => now(),
                'deleted_uid' => $user->id,
            ]);
            Package::where($pmtKey,operator: $id)->update([
                $pmtKey => null,
                // $type.'_payment_id' => null,
            ]);

            DisbursementPackage::where('disbursement_id',$id)->update([
                'is_deleted' => 1,
                'deleted_datetime' => now(),
                'deleted_uid' => $user->id,
                'deleted_reason' => 'rollback by '.$user->username
            ]);
        }

        return DataResponse::JsonResult(null,false,__('messages.deleted',[
            'info' => 'Payment'
        ]));
    }

    private function validType($type){
        $validType = ['driver','merchant'];
        if(!in_array($type,$validType)) return DataResponse::ValidateFail('Invalid type');
        return DataResponse::JsonResult(null);
    }

    public static function preparePaymentPackageAmount($paymentDetails,$paymentId,$type='receive'){
        $converter = (object)[
            'total_usd' => 0,
            'total_khr' => 0,
            'cash_usd' => 0,
            'cash_khr' => 0,
            'bank_usd' => 0,
            'bank_khr' => 0,
        ];
        foreach ($paymentDetails as $d) {
        // Determine if the record matches the given type and ID
            $isMatchingType = ($type === 'receive' && $d->payment_id == $paymentId) ||
                            ($type === 'disbursement' && $d->disbursement_id == $paymentId);

            if ($isMatchingType) {
                // Update totals based on currency
                if ($d->currency_code === 'USD') {
                    $converter->total_usd += $d->amount;
                } else {
                    $converter->total_khr += $d->amount;
                }

                // Update cash and bank details
                if ($d->method === 'cash') {
                    if ($d->currency_code === 'USD') {
                        $converter->cash_usd += $d->amount; // Use += to sum amounts
                    } else {
                        $converter->cash_khr += $d->amount; // Sum for KHR
                    }
                } else {
                    if ($d->currency_code === 'USD') {
                        $converter->bank_usd += $d->amount; // Sum for USD bank
                    } else {
                        $converter->bank_khr += $d->amount; // Sum for KHR bank
                    }
                }
            }
        }
        return $converter;
    }


    public function approvePayments(Request $req,$user){
        $payments = $req->payments ?? [];
        if(!isset($payments[0])) return DataResponse::ValidateFail(__('messages.info',[
            'info' => 'Please check payments you want to approve'
        ]));

        DB::beginTransaction();
        try{
            foreach($payments as $p){
                $id = $p['id'];
                if($p['payment_type'] == 'receive'){
                    $pmt = Payment::where('is_deleted',0)->find($id);
                    if(!$pmt) return DataResponse::ValidateFail(__('messages.info',[
                        'info' => 'check list includes invalid payment'
                    ]));
                    if($pmt->approved) return DataResponse::ValidateFail(__('messages.info',[
                        'info' => 'check list includes approved payment'
                    ]));
                    $pmt->update([
                        'approved' => 1,
                        'approved_datetime' => now(),
                        'approved_uid' => $user->id,
                    ]);
                }else if($p['payment_type'] == 'disbursement'){
                    $dis = Disbursement::where('is_deleted',0)->where('type','payment')->find($id);
                    if(!$dis) return DataResponse::ValidateFail(__('messages.info',[
                        'info' => 'check list includes invalid payment'
                    ]));
                    if($dis->approved) return DataResponse::ValidateFail(__('messages.info',[
                        'info' => 'check list includes approved payment'
                    ]));
                    $dis->update([
                        'receiptionist_uid' => $user->id,
                        'approved' => 1,
                        'approved_datetime' => now(),
                        'approved_uid' => $user->id,
                    ]);
                }
            }
            DB::commit();
            return DataResponse::JsonResult(null,false,__('messages.info',[
                'info' => 'Approved',
                'khInfo' => 'ឯកភាព'
            ]));
        }catch(Exception $e){
            DB::rollBack();
            Log::error($e->getTraceAsString());
            Log::error($e->getMessage());
            return DataResponse::Error(__('messages.error'));
        }
    }

    public function settlePayments(Request $req,$user){
        $payments = $req->payments ?? [];
        if(!isset($payments[0])) return DataResponse::ValidateFail(__('messages.info',[
            'info' => 'Please select payments you want to settle'
        ]));

        DB::beginTransaction();
        try{
            foreach($payments as $p){
                $id = $p['id'];
                if($p['payment_type'] == 'receive'){
                    $pmt = Payment::where('is_deleted',0)->find($id);
                    if(!$pmt) return DataResponse::ValidateFail(__('messages.info',[
                        'info' => 'check list includes invalid payment'
                    ]));
                    if($pmt->is_settled) return DataResponse::ValidateFail('check list includes settled payment');
                    $pmt->update([
                        'is_settled' => 1,
                        'settled_datetime' => now(),
                        'settled_uid' => $user->id,
                    ]);
                }else if($p['payment_type'] == 'disbursement'){
                    $dis = Disbursement::where('is_deleted',0)->where('type','payment')->find($id);
                    if(!$dis) return DataResponse::ValidateFail(__('messages.info',[
                        'info' => 'check list includes invalid payment'
                    ]));
                    if($dis->is_settled) return DataResponse::ValidateFail('check list includes settled payment');
                    $dis->update([
                        'is_settled' => $user->id,
                        'setteled_datetime' => now(),
                        'settled_uid' => $user->id,
                    ]);
                }
            }
            DB::commit();
            return DataResponse::JsonResult(null,false,__('messages.info',[
                'info' => 'Settled',
                'khInfo' => 'បញ្ជាក់ការទូរទាត់'
            ]));
        }catch(Exception $e){
            DB::rollBack();
            Log::error($e->getMessage());
            Log::error($e->getTraceAsString());
            return DataResponse::Error('Failed to settle');
        }
    }

    public function getBalance(Request $req,$user,$type='driver'){
        $driverId = $req->driver_id ?? null;
        $merchantId = $req->merchant_id ?? null;
        $userId = $driverId ?? $merchantId;
        $startDate = $req->startDate ? Helper::dateYMD($req->startDate) : null;
        $endDate = $req->endDate ? Helper::dateYMD($req->endDate) : null;
        $pmtKey = $type.'_payment_id';
        $disKey = $type.'_disbursement_id';
        $qP = User::from('users as d')
            ->where('d.account_type',$type)
            ->where('d.is_deleted', 0)
            ->where('d.company_id', $user->company_id) // Uncomment if needed
            ->join('packages as p', 'p.'.$type.'_id', '=', 'd.id')
            ->where('p.is_deleted',0)
            ->whereIn('p.status_id',[9,19])
            // ->where(function ($q) use($pmtKey,$disKey) {
            //     $q->whereNull($pmtKey)
            //     ->whereNull($disKey);
            // })
            ->whereNotExists(function ($sub) use ($type) {
                $sub->select(DB::raw(1))
                    ->from('payment_packages as pp')
                    ->whereColumn('pp.package_id', 'p.id')
                    ->where('pp.payer_type', $type)
                    ->where('pp.is_deleted', false);
            })
            ->whereNotExists(function ($sub) use ($type) {
                $sub->select(DB::raw(1))
                    ->from('disbursement_packages as dp')
                    ->whereColumn('dp.package_id', 'p.id')
                    ->where('dp.payee_type', $type)
                    ->where('dp.is_deleted', false);
            })

            ->orderByRaw('COALESCE(p.failed_datetime, p.delivered_datetime) DESC NULLS LAST')
            // ->join('payments as pmt','p.driver_payment_id','pmt.id')
            // ->where('pmt.is_settled',0)
            ->select(['p.additional_fee','p.extra_charge','p.payer','p.cod','p.delivery_fee','p.price','p.taxi_fee','p.extra_charge','p.delivered_datetime','p.failed_datetime','d.id as driver_id','d.id','d.user_name as driver_name','d.code','p.status_id','p.updated_at']);
            // ->groupBy(['d.id','pmt.payable_amount',DB::raw('DATE(p.delivered_datetime)'),DB::raw('DATE(p.failed_datetime)')]);
        if($userId){
            $qP->where('d.id',$userId);
        }
        if ($startDate && $endDate) {
            $qP->where(function ($q) use ($startDate, $endDate) {
                // Check for status_id = 9, delivered_datetime should be within the date range
                $q->where(function ($q) use ($startDate, $endDate) {
                    $q->where('status_id', 9)
                    ->whereRaw('delivered_datetime >= ? AND delivered_datetime <= ?', ["$startDate 00:00:00", "$endDate 23:59:59.999"]);
                })
                // Check for status_id = 19, failed_datetime should be within the date range
                ->orWhere(function ($q) use ($startDate, $endDate) {
                    $q->where('status_id', 19)
                    ->whereRaw('failed_datetime >= ? AND failed_datetime <= ?', ["$startDate 00:00:00", "$endDate 23:59:59.999"]);
                });
            });

            // $qP->where(function ($q) use ($startDatetime, $endDatetime) {
            //     $q->whereRaw(
            //         "(p.failed_datetime >= ? AND p.failed_datetime <= ?)
            //         OR
            //         (p.delivered_datetime >= ? AND p.delivered_datetime <= ?)",
            //         [$startDatetime, $endDatetime, $startDatetime, $endDatetime]
            //     );
            // });
        }


        $drivers = $qP->get();
        $totalPackages = 0;
        $totalAmount = 0;
        $totalCod = 0;

        $groupData = collect($drivers)->map(function ($item) {
            // Set groupDate based on status
            if ($item->status_id == 9) {
                $date = $item->delivered_datetime ? $item->delivered_datetime : $item->updated_at;
                $item->groupDate = date('d-M-Y', strtotime($date));
            } elseif ($item->status_id == 19) {
                $date = $item->failed_datetime ? $item->failed_datetime : $item->updated_at;
                $item->groupDate = date('d-M-Y', strtotime($date));
            }

            return $item;
        })->filter(function ($item) {
            return $item->groupDate !== null; // Exclude items with no groupDate
        })->groupBy(function ($item) {
            // Group by both groupDate and driver_id
            return $item->groupDate . '|' . $item->driver_id;
        })->map(function ($group, $key) use(&$totalPackages,&$totalAmount,&$totalCod) {
            // Extract date and driver_id from the key
            [$date, $driver_id] = explode('|', $key);

            // Sum the package counts for this group

            $packageTotal = $group->count(); // Count items in the group (equivalent to summing 1 per item)
            $totalPrice = $group->where('cod',1)->where('status_id','=',9)->sum('price');
            $representative = $group->first();
            $taxiFee = $group->where('status_id','=',9)->sum('taxi_fee');
            $fee = $group->where('payer','receiver')->sum('delivery_fee') + $group->where('payer','receiver')->sum('extra_charge') + $group->sum('additional_fee') - $taxiFee;
            $amount = Helper::getNumber($totalPrice + $fee,2);
            $totalPackages += $packageTotal;
            $totalAmount += $amount;
            $totalCod += $totalPrice;
            // $representative->package_count = $packageTotal; // Add the summed total_package
            unset($representative->groupDate);
            return [
                'finished_date' => $date,
                'driver_id' => $driver_id,
                'driver_name' => $representative->driver_name,
                'amount' => $amount,
                'code' => $representative->code,
                'status_id' => $representative->status_id,
                'package_count' => $packageTotal,
            ];
        })->values();

        return DataResponse::Pagination(collect($groupData),$req,__('messages.Get List'),[
            'total_packages' => $totalPackages,
            'total_cod' => (float)Helper::getNumber($totalCod),
            'total_amount' => (float)Helper::getNumber($totalAmount,2)
        ]);
    }

    public function getDriverCommissionBalance($user,$driver_id,$type='pick_up',$filter=null){
        $commissions = $this->getDriverCommissions($user,$driver_id);
        $totalPickUpCommission = 0;
        $totalFastDeliveryCommission = 0;
        $totalNormalDeliveryCommission = 0;
        $normalPickUpCommission = $commissions->data->normal_pickup_commission;
        $normalDeliveryCommission = $commissions->data->normal_delivery_commission;
        $fastPickUpCommission = $commissions->data->fast_pickup_commission;
        $fastDeliveryCommission = $commissions->data->fast_delivery_commission;
        if($type == 'pick_up' || $type == 'all'){
            $orders = Order::where('driver_id',$driver_id)->whereIn('status_id',[2,4,5,21])->selectRaw('SUM(qty) as total_qty')->get();
            foreach($orders as $order){
                $totalPickUpCommission += $order->total_qty * $normalPickUpCommission;
            }
            if($type != 'all') return (object)['total' => Helper::getNumber($totalPickUpCommission,2)];
        }else if($type == 'delivery' || $type == 'all'){
            $packages = Package::where('driver_id',$driver_id)
            ->where('is_deleted',0)
            ->whereIn('status_id',[9,19])
            ->selectRaw('delivery_type')
            ->get();
            foreach($packages as $pkg){
                if($pkg->delivery_type == 'fast'){
                    $totalFastDeliveryCommission += $fastDeliveryCommission;
                }else if($pkg->delivery_type == 'normal'){
                    $totalNormalDeliveryCommission += $normalDeliveryCommission;
                }
            }
            if($type != 'all') return (object)[
                'total' => Helper::getNumber($totalFastDeliveryCommission + $totalNormalDeliveryCommission,2),
                'total_fast_delivery' => Helper::getNumber($totalFastDeliveryCommission,2),
                'total_normal_delivery' => Helper::getNumber($totalNormalDeliveryCommission,2),
            ];
        }
        //
        return (object)[
            'total_pickup' => Helper::getNumber($totalPickUpCommission,2),
            'total_fast_delivery' => Helper::getNumber($totalFastDeliveryCommission,2),
            'total_normal_delivery' => Helper::getNumber($totalNormalDeliveryCommission,2)
        ];
    }

    public function updateDeliveryPackage(Request $req,$type,$user){
        $id = $req->id;
        $validate = validator($req->all(),[
            'remarks' => 'nullable|string|max:250',
            'cod' => 'required|in:1,0',
            'price' => 'nullable|numeric',
            'payer' => 'required|in:receiver,sender',
            'taxi_fee' => 'nullable|numeric|min:0',
            'zone_code' => 'required',
            'extra_charge' => 'nullable|numeric|min:0'
        ]);
        if($validate->fails()) return DataResponse::ValidateFail($validate->errors()->first());
        $inputs = $validate->validated();
        // $package = Package::fromRaw('packages as p')->where('p.company_id',$user->company_id)
        // // ->leftJoin('payments as dpmt',$joinCallback)
        // ->selectRaw('p.status_id,p.p.extra_charge,p.id,p.taxi_fee,p.cod,p.payer,p.zone_code,p.price,p.billed_kg,p.actual_kg,p.driver_payment_id,p.merchant_payment_id,p.merchant_disbursement_id,p.driver_disbursement_id')
        // ->where('p.id',$id)->first();
        $package = Package::where('is_deleted',0)->find($id);
        if(!$package) return DataResponse::NotFound(__('messages.not_found',[
            'info' => 'Package'
        ]));
        if($package->driver_payment_id || $package->driver_disbursement_id) return DataResponse::Duplicated(__('messages.info',[
            'info' => 'It seems like you try to update package which is on payment pending or paid with driver',
            'khInfo' => 'មិនអាចកែកញ្ចប់បានទេ, កញ្ចប់បានទូរទាត់ជាមួយអ្នកដឹករួចហើយ'
        ]));
        if($package->merchant_payment_id || $package->merchant_disbursement_id) return DataResponse::Duplicated(__('messages.info',[
            'info' => 'It seems like you try to update package which is on payment pending or paid with merchant',
            'khInfo' => 'មិនអាចកែកញ្ចប់បានទេ, កញ្ចប់បានទូរទាត់ជាមួយអ្នកផ្ញើរួចហើយ (Merchant)'
        ]));
        $cod = $inputs['cod'] ?? $package->cod;
        $payer = $inputs['payer'] ?? $package->payer;
        $extraCharge = $inputs['extra_charge'] ?? $package->extra_charge;
        $taxi_fee = $inputs['taxi_fee'] ?? $package->taxi_fee;
        $price = $inputs['price'] ?? $package->price;
        if($package->status_id == 19){
            $price = 0;
            $taxi_fee = 0;
        }
        $zoneCode = $inputs['zone_code'] ?? $package->zone_code;
        $zone = Zone::where('is_deleted',0)->selectRaw('id,zone_name')->where('zone_code',$zoneCode)->first();
        if(!$zone) return DataResponse::NotFound(__('messages.not_found',[
            'info' => 'Zone',
            'khInfo' => 'ទីតាំង'
        ]));
        $inputs['zone_name'] = $zone->zone_name;
        $calFee = GeneralSettingService::calculatePackageFee($zoneCode,$price,$package->billed_kg,$package->actual_kg,$payer,$cod,$extraCharge,$user,$taxi_fee,$package->merchant_id);
        if($calFee->error) return $calFee;
        $inputs['delivery_fee'] = $calFee->delivery_fee;
        $inputs['driver_total'] = $calFee->driver_total; //($package->status_id == 19 && $package->cod) ? abs($price - $calFee->driver_total):
        $inputs['merchant_total'] = $calFee->merchant_total;
        $package->update($inputs);
        return DataResponse::JsonResult(null,false ,__('messages.updated',[
            'info' => 'Package'
        ]));
    }

    public function disbursementPaymentValidation(Request $req,$type='driver'){
        return validator($req->all(),[
            $type.'_id' => 'required|int',
            'cash' => 'nullable|numeric',
            'cash_kh' => 'nullable|numeric',
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
    public function disbursementPayment(Request $req,$user,$type){
        $validType = $this->validType($type);
        if($validType->error) return $validType;
        $validate = self::disbursementPaymentValidation($req,$type);
        if($validate->fails()) return DataResponse::ValidateFail($validate->errors()->first());
        $inputs = $validate->validated();
        $payeeId = $inputs[$type.'_id'];
        $packageIds = $inputs['packages'] ?? null;
        if(empty($packageIds)) return DataResponse::ValidateFail(__('messages.info',[
            'info' => 'Please select package'
        ]));
        $payeeInfo = User::where('is_deleted',0)->whereIn('account_type',['driver','merchant'])->find($payeeId);
        if(!$payeeInfo) return DataResponse::NotFound('Could not find payee information');
        $validPackages = $this->validPackages($packageIds,$payeeId,$type);
        if($validPackages->error) return $validPackages;
        $exchangeRate = $inputs['exchange_rate'] ?? GeneralSettingService::getLatestXRate()->buy_rate;
        $cashKh = $inputs['cash_kh'] ?? 0;
        $cash = $inputs['cash'] ?? 0;
        $bankId = $inputs['bank_id'] ?? null;
        $bankAmount = $inputs['bank_amount'] ?? 0;
        $bankAmountKh = $inputs['bank_amount_kh'] ?? 0;
        $dueAmount = abs($validPackages->total_due_amount);
        if($validPackages->total_due_amount >=0) return DataResponse::ValidateFail(__('messages.info',[
            'info' => 'This case should be receive payment not disbursement'
        ]));

        // return $validPackages;
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
        $paymentType = 'payment';
        DB::beginTransaction();
        try{
            $disArr = [
                'payee_id' => $payeeId,
                'payee_type' => $type,
                'taxi_fee' => $validPackages->total_taxi_fee,
                'delivery_fee' => $validPackages->total_delivery_fee,
                'payable_amount' => $dueAmount,
                'paid_amount' => $dueAmount,
                'create_uid' => $user->id,
                'receiver_uid' => $user->id,
                'amount' => $dueAmount,
                'receiptionist_uid' => $user->id,
                'failed_with_fee_count' => $validPackages->failed_with_fee_count,
                'cod_amount' => $validPackages->total_cod,
                'exchange_rate' => $exchangeRate,
                'remarks' => $inputs['remarks'] ?? null,
                'package_count' => $validPackages->total_package,
                'delivered_package_count' => $validPackages->delivered_package_count,
                'update_uid' => $user->id,
                'payment_datetime' => now(),
                'breakdown_notes' => $breakDownNotes,
                'company_id' => $user->company_id,
                'branch_id' => $user->branch_id,
                'type' => $paymentType
            ];

            if($type == 'merchant'){
                $disArr['is_settled'] = 1;
                $disArr['settled_uid'] = $user->id;
                $disArr['approved_uid'] = $user->id;
                $disArr['approved'] = 1;
                $disArr['receiptionis_uid'] = $user->id;
                $disArr['approved_datetime'] = now();
                $disArr['settled_datetime'] = now();
            }
            $createPayment = Disbursement::create($disArr);
            $disbursementId = $createPayment->id;
            self::transactionCodeGenerator('transaction_sequences',$paymentType,'disbursements','trx_code',$user->branch_id,$user->company_id,$disbursementId);
            if($cash > 0 && $dueAmount > 0){
                DisbursementDetails::create([
                    'disbursement_id' => $disbursementId,
                    'method' => 'cash',
                    'amount' => $cash,
                    'original_amount' => $cash,
                    'currency_code' => 'USD'
                ]);
            }
            if($cashKh > 0 && $dueAmount > 0){
                DisbursementDetails::create([
                    'disbursement_id' => $disbursementId,
                    'method' => 'cash',
                    'amount' => $cashKh,
                    'original_amount' => $validPayment->original_cash_amount_kh,
                    'currency_code' => 'KHR'
                ]);
            }
            if($bankId){
                if($bankAmount > 0 && $dueAmount > 0){
                    DisbursementDetails::create([
                        'disbursement_id' => $disbursementId,
                        'method' => $validPayment->bank_name,
                        'amount' => $bankAmount,
                        'original_amount' => $bankAmount,
                        'currency_code' => 'USD'
                    ]);
                }

                if($bankAmountKh > 0 && $dueAmount > 0){
                    DisbursementDetails::create([
                        'disbursement_id' => $disbursementId,
                        'method' => $validPayment->bank_name,
                        'amount' => $bankAmountKh,
                        'original_amount' => $validPayment->original_bank_amount_kh,
                        'currency_code' => 'KHR'
                    ]);
                }
            }
            Package::whereIn('id',$packageIds)->update([
                $type.'_disbursement_id' => $disbursementId
            ]);

            // DisbursementPackage::insert($packageIds);
            $disbursementPackagesArr = collect($packageIds)->map(fn($id) => [
                'package_id' => $id,
                'disbursement_id' => $disbursementId,
                'type' => $paymentType,
                'payee_type' => $type,
            ])->toArray();
            DisbursementPackage::insert($disbursementPackagesArr);


            $notif = new CloudMessagingService();
            $topics = GeneralSettingService::getGeneralTopics($user->company_id,'driver',$payeeId);
            // return $topics;
            $notifReq = new Request([
                'topic' => $topics->private,
                'type' => 'private',
                'target_uid' => $payeeId,
                'title' => __('messages.info',[
                    'info' => 'Payment Received',
                    'khInfo' => ''
                ]),
                'body' => 'A total of '.$validPackages->total_package.' packages have been processed for this payment.'
            ]);
            $notif->sendNotificationByTopic($notifReq,$user);
            // return $packageIds;
            // return Package::whereIn('id',$packageIds)->get();
            DB::commit();

            // return Package::whereIn('id',$packageIds)->get();
            return DataResponse::JsonResult(null,false,__('messages.created',[
                'info' => 'Payment'
            ]));
        }catch(Exception $e){
            Log::error($e->getMessage());
            Log::error($e->getTraceAsString());
            DB::rollBack();
            return DataResponse::Error(__('messages.error',['info' => 'Fail to receive']));
        }
    }

    public function disbursementCommission(Request $req,$user,$type){
        $validType = $this->validType($type);
        if($validType->error) return $validType;
        $startDate = $req->startDate;
        $endDate = $req->endDate;
        $validate = self::disbursementPaymentValidation($req,$type);
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
        $cashKh = $inputs['cash_kh'] ?? 0;
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
            $createPayment = Disbursement::create([
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
                'fast_pickup_rate' => $validPackages->fast_pickup_rate,
                'delivery_rate' => $validPackages->fast_delivery_rate,
                'fast_delivery_rate' => $validPackages->fast_delivery_rate,
                'settled_uid' => $user->id,
                'approved_uid' => $user->id,
                'receiptionist_uid' => $user->id,
                'remarks' => $inputs['remarks'] ?? null,
                'package_count' => $validPackages->total_package,
                'delivered_package_count' => $validPackages->total_delivered_package,
                'pickup_package_count' => $validPackages->total_pickup_package,
                'failed_with_fee_count' => $validPackages->failed_with_fee_count,
                'update_uid' => $user->id,
                'approved_datetime' => now(),
                'settled_datetime' => now(),
                'payment_datetime' => now(),
                'breakdown_notes' => $breakDownNotes,
                'company_id' => $user->company_id,
                'branch_id' => $user->branch_id
            ]);
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
            Package::whereIn('id',$packageIds)->update([
                $type.'_commission_id' => $paymentId
            ]);
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
            Log::error($e->getMessage());
            Log::error($e->getTraceAsString());
            DB::rollBack();
            return DataResponse::Error(__('messages.error',['info' => 'Fail to receive']));
        }
    }

    public function deleteDisbursementCommission($id,$user,$type){
        $dis = Disbursement::where('type','commission')->where('is_deleted',0)->find($id);
        if(!$dis) return DataResponse::NotFound('');
        $dis->update([
            'is_deleted' => 1,
            'deleted_datetime' => now(),
            'deleted_uid' => $user->id,
        ]);
        $pmtKey = $type.'_commission_id';
        Package::where('is_deleted',0)->where($pmtKey,$id)->update([
            $pmtKey => null
        ]);

        Order::where('is_deleted',0)->where($pmtKey,$id)->update([
            $pmtKey => null
        ]);

        return DataResponse::JsonResult(null,false,__('messages.deleted',[
            'info' => 'Commission',
            'khInfo' => ''
        ]));

    }



    private function validCommissionPackage($payeeId,$type,$startDate,$endDate){
        $validType = $this->validType($type);
        if($validType->error) return $validType;
        // $deliveredCount = 0;
        $pickUpCount = 0;
        // $failedWithFeeCount = 0;
        // Log::info($startDate.'--'.$endDate);
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
        $dc = (object)[
            'normal_pickup_commission' => 0,
            'normal_delivery_commission' => 0,
            'fast_pickup_commission' => 0,
            'fast_delivery_commission' => 0
        ];
        $payeeKey = $type.'_id';
        $qP = Package::selectRaw('id,status_id,driver_id')
        ->whereIn('status_id',[9,19])
        ->where('is_deleted',0)
        ->whereNull('driver_commission_id');
        if($payeeId) $qP->where($payeeKey,$payeeId);

        $qO = Order::where('is_deleted',0)->whereNull('driver_commission_id')
        ->where('status_id',5)
        ->selectRaw('id,status_id,qty');
        if($payeeId) $qO->where($payeeKey,$payeeId);
        // if($startDate && $endDate){
        //     $startDate = Helper::dateYMD($startDate);
        //     $endDate = Helper::dateYMD($endDate);
        //     $qO->whereRaw('order_datetime::DATE >= ? AND order_datetime::DATE <= ?', [$startDate, $endDate]);
        // }

        if($startDate && $endDate){
            $startDate = Helper::dateYMD($startDate).' 00:00:00';
            $endDate = Helper::dateYMD($endDate). ' 23:59:59';
            $qP->whereRaw("
                (
                    (status_id = 19 AND failed_datetime >= ? AND failed_datetime <= ?)
                    OR
                    (status_id = 9 AND delivered_datetime >= ? AND delivered_datetime <= ?)
                )
            ", [$startDate, $endDate, $startDate, $endDate]);

            $qO->whereRaw('order_datetime >= ? AND order_datetime <= ?', [$startDate, $endDate]);
        }
        $packages = $qP->get();
        $orders = $qO->get();
        $qDc = DriverCommission::where('driver_id',$payeeId)->where('is_deleted',0)->selectRaw('delivery_type,pickup_commission,delivery_commission,use_percentage');
        $driverCommissions = $qDc->get();
        foreach($driverCommissions as $driverComm){
            if($driverComm->delivery_type == 'fast'){
                $dc->fast_pickup_commission = $driverComm->pickup_commission;
                $dc->fast_delivery_commission = $driverComm->delivery_commission;
            }
            if($driverComm->delivery_type == 'normal'){
                $dc->normal_pickup_commission = $driverComm->pickup_commission;
                $dc->normal_delivery_commission = $driverComm->delivery_commission;
            }
        }
        foreach($orders as $order){
            $pickUpCount += $order->qty;
            $obj->order_ids[] = $order->id;
        }

        $totalCommissionPkg = 0;
        $normalDeliveredCount = 0;
        $fastDeliveredCount = 0;

        $normalFailedWithFeeCount = 0;
        $fastFailedWithFeeCount = 0;
        foreach($packages as $package){
            // if($package->status_id == 9) {
            //     $deliveredCount += 1;
            //     $obj->package_ids[] = $package->id;
            // }
            // if($package->status_id == 19) $failedWithFeeCount +=1;
            // if($package->cod) $obj->total_taxi_fee += $package->delivery_fee;
            // if($package->taxi_fee) $obj->total_taxi_fee += $package->taxi_fee;
            if($package->status_id == 9) {
                if($package->delivery_type == 'normal'){
                    $normalDeliveredCount +=1;
                }else if($package->delivery_type == 'fast'){
                    $fastDeliveredCount +=1;
                }
                $totalCommissionPkg +=1;
            }
            if($package->status_id == 19) {
                if($package->delivery_type == 'normal'){
                    $normalFailedWithFeeCount +=1;
                }
                else if($package->delivery_type == 'fast'){
                    $fastFailedWithFeeCount +=1;
                }
                $totalCommissionPkg +=1;
            }
        }

        $obj->total_pickup = $pickUpCount * $dc->normal_pickup_commission;
        $obj->total_delivered = $normalDeliveredCount * $dc->normal_delivery_commission + $fastDeliveredCount * $dc->fast_delivery_commission;
        $obj->total_package = $pickUpCount + $normalDeliveredCount + $fastDeliveredCount + $normalFailedWithFeeCount + $fastFailedWithFeeCount;
        //** Deliverd pkgs */
        $obj->normal_delivered_count= $normalDeliveredCount;
        $obj->fast_delivered_count = $fastDeliveredCount;
        //-----
        $obj->total_delivered_package = $normalDeliveredCount + $fastDeliveredCount;
        $obj->total_pickup_package = $pickUpCount;
        $obj->delivery_rate = $dc->normal_delivery_commission;
        $obj->pickup_rate = $dc->normal_pickup_commission;
        //** FailedWithFee pkgs */
        $obj->normal_failed_with_fee_count = $normalFailedWithFeeCount;
        $obj->fast_failed_with_fee_count = $fastFailedWithFeeCount;
        //----

        $obj->total_pickup_count = $pickUpCount;

        $obj->grand_total = Helper::getNumber($obj->total_pickup + $obj->total_delivered,2);
        // Log::info($deliveredCount);
        if(empty($obj->package_ids) && empty($obj->order_ids)){
            return DataResponse::NotFound('No package found');
        }
        return $obj;
    }

    public static function getPickUpDetails($orders,$driverId){
        $totalPkg = 0;
        foreach($orders as $order){
            if($order->driver_id == $driverId){
                $totalPkg += $order->qty;
            }
        }
        return (object)[
            'total_package' => $totalPkg,
        ];
    }

    public static function getDeliveredDetails($packages,$driverId){
        $totalPkg = 0;
        $failedWithFeeCount = 0;
        $deliveredCount = 0;
        foreach($packages as $pkg){
            if($pkg->driver_id == $driverId){
                $totalPkg += 1;
                if($pkg->status_id == 9) $deliveredCount +=1;
                else if($pkg->status_id == 19) $failedWithFeeCount +=1;
            }
        }

        return (object)[
            'total_package' => $totalPkg,
            'delivered_count' => $deliveredCount,
            'failed_with_fee_count' => $deliveredCount,
        ];
    }

    public static function getMobileUserBalance($req,$user,$targetUser){
        $count = 0;
        $total = 0;
        $operator = [
            'merchant' => -1,
            'driver' => 1,
        ];

        $targeUId = $targetUser.'_id';
        $pUid = $targetUser.'_payment_id';
        $dUid = $targetUser.'_disbursement_id';

        $qP = Package::where('is_deleted',0)
        ->where('created_at', '>=', Carbon::now()->subMonths(3))
        ->whereIn('status_id',[9,19])
        // ->selectRaw('*')
        ->where($targeUId,$user->id)
        ->orderBy($pUid,'desc')
        ->orderBy($dUid,'desc')
        ->where(function ($q) use($pUid,$dUid) {
            $q->whereNull($pUid)
            ->whereNull($dUid);
        });
        if($targetUser == 'merchant'){
            $qP->where(function($query) {
            $query->where('status_id', 9)
                    ->whereDate('delivered_datetime', Carbon::today());
            })
            ->orWhere(function($query) {
                $query->where('status_id', 19)
                    ->whereDate('failed_datetime', Carbon::today());
            });
        }
        $packages = $qP->get();
        $opt = $operator[$targetUser] ?? null;
        foreach($packages as $p){
            $price = $p->price;
            $taxiFee = $p->taxi_fee;
            if($p->status_id == 19){
                $price = 0;
                $taxiFee = 0;
            }
            $packageTotal = TransactionService::getPackageTotal($targetUser,$p->cod,$price,$taxiFee,$p->extra_charge,$p->additional_fee,$p->delivery_fee,$p->payer);
            $total += $opt * $packageTotal;
        }
        return [
            'count' => $count,
            'total' => Helper::getNumber($total),
        ];
    }


    static function getTrxDetails($rows, $pmtId, $pmtBillings = null)
    {
        foreach ($rows as $row) {
            if ($row->id == $pmtId) {
                $row->breakdown_notes = str_replace(['|', 'USD '], [' & ', '$'], $row->breakdown_notes);
                $row->breakdown_notes = preg_replace('/KHR (\d+)/', '$1៛', $row->breakdown_notes);
                // 🛠 Fix: Collect unique methods
                $methods = [];
                if (isset($pmtBillings[$row->id])) {
                    foreach ($pmtBillings[$row->id] as $billing) {
                        if (!in_array($billing->method, $methods)) {
                            if($billing->method == 'cash') $billing->method = 'Cash';
                            $methods[] = $billing->method;
                        }
                    }
                }

                // 🛠 Concat methods into a string
                $pmtMethod = implode(', ', $methods);

                $row->payment_method = $pmtMethod;
                $row->payment_date = Helper::dateDMY($row->payment_datetime, 'd M Y');
                $row->payment_time = Helper::formatCustomDateTime($row->payment_datetime, 'h:i A');

                return $row;
            }
        }
        return null;
    }


    // static function getTrxDetails($rows,$pmtId,$pmtBillings=null,&$pmtMethod = ''){
    //     foreach($rows as $row){
    //         if($row->id == $pmtId){
    //             $row->breakdown_notes = str_replace(
    //                 ['|', 'USD '],
    //                 [' & ', '$'],
    //                 $row->breakdown_notes
    //             );
    //             $row->breakdown_notes = preg_replace('/KHR (\d+)/', '$1៛', $row->breakdown_notes);
    //             $method = isset($pmtBillings[$row->id]) ? $pmtBillings[$row->id]->method : null;

    //             Log::info($pmtBillings);
    //             // Append to the reference string (if not already included)
    //             if ($method && !str_contains($pmtMethod, $method)) {
    //                 $pmtMethod .= ($pmtMethod ? ', ' : '') . $method;
    //             }

    //             $row->payment_method = $pmtMethod;
    //             // Log::info($pmtBillings[$row->id]);
    //             $row->payment_date = Helper::dateDMY($row->payment_datetime,'d M Y');
    //             $row->payment_time = Helper::formatCustomDateTime($row->payment_datetime,'h:i A');
    //             // Log::info($pmtMethod);
    //             return $row;
    //         }
    //     }
    //     return null;
    // }

    static function transactionCodeGenerator($tbl_code_control,$type,$target_tbl,$target_col,$branch_id,$company_id,$newID,$prefix='TRX', $len = 5){
        if (!$len) $len = 5;
        if (!$newID) return DataResponse::ValidateFail('Identity should be input');
        $year = date('Y');
        $qRow = DB::table($tbl_code_control . " as c")->where('c.branch_id', $branch_id)
        ->where('prefix',$prefix)
        ->where('c.company_id',$company_id)
        ->where('payment_type',$type)
        ->selectRaw("c.last_idx,c.prefix,c.issue_year");
        $qRow->where('c.issue_year',$year);
        $row = $qRow->first();

        $next_num = 0;
        if ($row){
            $next_num = $row->last_idx;
            if($row->issue_year == $year) $year = $row->issue_year;
        }
        $next_num++;
        //example ref number => 20240212-B001-00003-random
        $new_code = date('Ymd') .'-B'. Helper::formatNumber($branch_id,3).'-'. Helper::formatNumber($next_num, $len).'-'.substr(Str::uuid()->toString(), 0, 5);
        if($prefix) $new_code = $prefix.'-'.$new_code;
        $x = DB::table($target_tbl)->where('id', $newID)->update([$target_col => $new_code]);
        if ($x || $x === 1) {
            $Qupdated = DB::table($tbl_code_control)->where('branch_id', $branch_id)
            ->where('prefix',$prefix)
            ->where('issue_year', $year)
            ->where('company_id',$company_id);
            $updated = $Qupdated->update(['last_idx' => $next_num]);
            $insert_arr = [
                'branch_id' => $branch_id,
                'issue_year' => $year,
                'last_idx' => $next_num,
                'company_id' => $company_id,
                'prefix'=>$prefix,
                'payment_type'=>$type,
            ];
            if (!$updated) DB::table($tbl_code_control)->insert($insert_arr);
            // if ($onSuccess) $onSuccess();
            return (object)['status_code' => 200, 'status' => 'OK', 'code' => $new_code];
        }
    }
}
