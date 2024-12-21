<?php

namespace App\Services;

use ApiResponse;
use App\Models\Bank;
use App\Models\Disbursement;
use App\Models\DisbursementDetails;
use App\Models\DriverCommission;
use App\Models\Order;
use App\Models\Package;
use App\Models\Payment;
use App\Models\PaymentDetail;
use App\Models\User;
use DataResponse;
use DB;
use Exception;
use Helper;
use Illuminate\Http\Request;
use Log;

class TransactionService
{
    public function getDeliveryPackages(Request $req,$type,$user){
        $fkKey = $type.'_payment_id,'.$type.'_disbursement_id';
        $driverId = $req->driver_id;
        $merchantId = $req->merchant_id;
        $startDate = $req->startDate;
        $endDate = $req->endDate;
        $search = $req->search;
        $qP = Package::fromRaw('packages as p')->where('p.company_id',$user->company_id)
        ->join('users as d','d.id','p.driver_id')
        ->join('tracking_statuses as ts','ts.id','p.status_id')
        ->join('users as m','m.id','p.merchant_id')
        // ->whereNull($type.'_payment_id')
        // ->whereNull($type.'_payment_id')
        // ->leftJoin('payments as dpmt','dpmt.id','p.'.$fkKey) //** if driver paid or unpaid */
        // ->leftJoin('payments as mpmt','mpmt.id','p.merchant_payment_id') //** if driver paid or unpaid */
        ->whereIn('p.status_id',[9,19]) //* delivered and failed with fee
        ->selectRaw('p.additional_fee,p.remarks,p.cod,p.price,d.phone as driver_phone,p.taxi_fee,p.payer,p.delivery_fee,p.merchant_total,m.user_name as merchant_name,m.phone as merchant_phone,d.user_name as driver_name,p.status_id,p.id as package_id,d.id as driver_id,p.qr_code,ts.name as status_code,p.delivered_datetime,p.failed_datetime,p.zone_code,p.receiver_phone,p.delivery_type,'.$fkKey);
        if($type == 'driver'){
            $qP->where(function ($q) use($type){
                $q->whereNull($type.'_payment_id')->orWhereNull($type.'_disbursement_id');
            });
        }
        if($driverId || $merchantId){
            if($type == 'driver') $qP->where('p.driver_id',$driverId);
            else $qP->where('p.merchant_id',$merchantId);
        }
        if($search){
            $qP->where('p.qr_code',$search);
        }
        if($startDate && $endDate){
            $startDate = Helper::dateYMD($startDate);
            $endDate = Helper::dateYMD($endDate);
            $qP->where(function ($q) use ($startDate,$endDate){
                $q->whereBetween('delivered_datetime',[$startDate,$endDate])->orWhereBetween('failed_datetime',[$startDate,$endDate])
                ->orWhereDate('delivered_datetime','<=',$endDate)->orWhereDate('failed_datetime','<=',$endDate);
            });
        }
        $packages = $qP->get();
        $statusKey = $type.'_payment_status';
        foreach($packages as $package){
            $cod = $package->cod;
            $package->cod = $cod ? 'Yes' : 'No';
            $package->{$statusKey} = (!$package->{$type.'_payment_id'}) ? 'Unpaid':'Paid';
            if(!$package->{$statusKey}){
                $package->{$statusKey} = (!$package->{$type.'_disbursement_id'}) ? 'Unpaid':'Paid';
            }
            $package->datetime = ($package->status_id == 9 && ($package->delivered_datetime || $package->delivered_datetime)) ? Helper::formatCustomDateTime($package->delivered_datetime) : Helper::formatCustomDateTime($package->failed_datetime);
            // $merchantTotal = $package->merchant_total;
            // $package->merchant_total = -$merchantTotal;
            // if($package->cod && $package->price > 0 && $package->payer == 'sender'){
            //     $package->merchant_total = $package->driver_total - $merchantTotal;
            // }
            // if($package->cod) $package->remarks = $package->price;
            // $package->driver_total = self::getPackageTotal($type,$cod,$package->price,$package->taxi_fee,$package->extra_charge,$package->additional_fee,$package->delivery_fee,$package->payer);
            // $package->{$type.'_total'} = self::getPackageTotal($type,$cod,$package->price,$package->taxi_fee,$package->extra_charge,$package->additional_fee,$package->delivery_fee,$package->payer);
            $package->{$type.'_total'} = self::getPackageTotal($type,$cod,$package->price,$package->taxi_fee,$package->extra_charge,$package->additional_fee,$package->delivery_fee,$package->payer);
            if($type == 'merchant') $package->total = -self::getPackageTotal($type,$cod,$package->price,$package->taxi_fee,$package->extra_charge,$package->additional_fee,$package->delivery_fee,$package->payer);
            $package->fee = number_format($package->delivery_fee + $package->extra_charge + $package->additional_fee,2);
        }
        return DataResponse::Pagination($packages,$req);
    }

    public static function getPackageTotal($type,$cod,$price,$taxiFee,$extraCharge,$additionalFee,$baseFee,$payer){
        $total = 0;
        $baseFee += $extraCharge;
        if($type == 'driver'){
            if($cod) $total += $price;
            if($payer == 'receiver') $total += $baseFee;
            if($taxiFee) $total -= $taxiFee;
        }
        if($type == 'merchant'){
            if($cod) $total -= $price;
            if($payer == 'sender') $total += $baseFee;
            if($taxiFee) $total -= $taxiFee;
        }

        return number_format($total,2);
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
        $breakDownNotes = null;
        if($dueAmount > 0){
            if($cash) $breakDownNotes .= 'Cash: USD '.$cash.'|';
            if($cashKh) $breakDownNotes .= 'Cash: KHR '.$cashKh.'|';
            if($bankAmount) $breakDownNotes .= $validPayment->bank_name.': USD '.$bankAmount.'|';
            if($bankAmountKh) $breakDownNotes .= $validPayment->bank_name.': KHR '.$bankAmountKh.'|';
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
                $pmtArr['is_settled'] = 1;
                $pmtArr['settled_uid'] = $user->id;
                $pmtArr['approved_uid'] = $user->id;
                $pmtArr['approved_datetime'] = now();
                $pmtArr['settled_datetime'] = now();
            }
            $createPayment = Payment::create($pmtArr);
            $paymentId = $createPayment->id;
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
                if($bankAmount && $dueAmount > 0){
                    PaymentDetail::create([
                        'payment_id' => $paymentId,
                        'method' => $validPayment->bank_name,
                        'amount' => $bankAmount,
                        'original_amount' => $bankAmount,
                        'currency_code' => 'USD'
                    ]);
                }

                if($bankAmountKh && $dueAmount > 0){
                    PaymentDetail::create([
                        'payment_id' => $paymentId,
                        'method' => $validPayment->bank_name,
                        'amount' => $bankAmountKh,
                        'original_amount' => $validPayment->original_bank_amount_kh,
                        'currency_code' => 'KHR'
                    ]);
                }
            }
            foreach($packageIds as $id){
                $fkField = [
                    $type.'_payment_id' => $paymentId
                ];
                Package::find($id)->update($fkField);
            }
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

    private function validPayment($cash,$cashKh,$bankAmount,$bankAmountKh,$bankId,$dueAmount,$exhangeRate){
        $bankName = null;
        if($bankAmount && !$bankId) return DataResponse::ValidateFail(__('messages.error',['info' => 'Please enter bank']));
        if($bankAmountKh && !$bankId) return DataResponse::ValidateFail(__('messages.error',['info' => 'Please enter bank']));
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
        $dueAmount = number_format($dueAmount,2);
        $totalAmountUSD = $cash + $bankAmount;
        $totalAmountKHR = $cashKh + $bankAmountKh;
        $originalCashKh = 0;
        $originalBankAmtKh = 0;
        if($totalAmountUSD && $totalAmountKHR){
            $totalAmountKHR_to_USD = $totalAmountKHR/$exchangeRate;
            $totalAllAmt = $totalAmountUSD + $totalAmountKHR_to_USD;
            if($totalAllAmt > $dueAmount) return DataResponse::ValidateFail('You amount is exceeding the expected, amount is only $'.$dueAmount.' in total');
            $remainingAmt = abs($totalAmountUSD - $dueAmount);
            $totalSuggestionAmt_KH = $remainingAmt * $exchangeRate;
            $suggestionAmtBankKh = abs($totalSuggestionAmt_KH  - $cashKh);
            $suggestionAmtCashKh = abs($totalSuggestionAmt_KH - $bankAmountKh);
            if($bankAmountKh) $originalBankAmtKh += $suggestionAmtBankKh; //** keep original amount */
            if($cashKh) $originalCashKh += $suggestionAmtCashKh; //** keep original amount */
            $roundSuggestionAmtUp = ceil($totalSuggestionAmt_KH / 100) * 100;
            $roundSuggestionAmtDown = floor($totalSuggestionAmt_KH / 100) * 100;
            if(!($totalAmountKHR >= $roundSuggestionAmtDown && $totalAmountKHR <=$roundSuggestionAmtUp)) return DataResponse::ValidateFail(message: __('messages.info',[
                'info' => 'Amount KHR must be around (KHR '.$roundSuggestionAmtUp .' & KHR '.$roundSuggestionAmtDown.'), base '.$totalSuggestionAmt_KH
            ]));

            if(($totalAmountUSD + $totalAmountKHR_to_USD) < $dueAmount) return DataResponse::ValidateFail(__('messages.info',[
                'info' => 'If USD amount($'.$totalAmountUSD.')'.' additional in KHR must be ('.$roundSuggestionAmtUp.' or '.$totalSuggestionAmt_KH.')'
            ]));
        }

        if($totalAmountUSD && !$totalAmountKHR){
            if($cash && $bankAmount){
                $additionalSuggestion = abs($dueAmount - $cash);
                if($additionalSuggestion != $bankAmount)
                return DataResponse::ValidateFail(__('messages.info',[
                        'info' => 'If Cash Amount USD '.$cash.',so bank amount must be USD '.$additionalSuggestion
                    ]));
            }
            if($totalAmountUSD < $dueAmount) return DataResponse::ValidateFail(__('messages.info',[
                'info' => 'Payment amount must be $'.$dueAmount.' remaining amount ($'.$dueAmount - $totalAmountUSD.')'
            ]));

            if($totalAmountUSD > $dueAmount) return DataResponse::ValidateFail('You amount is exceeding the expected, amount is only $'.$dueAmount.' in total');
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
                        'info' => 'If Cash Amount KHR '.$cashKh.' bank amount must be around KHR '.$minSuggestionAmtDown.' or KHR '.$maxSuggestionAmt
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
            if($package->{$type.'_payment_id'} > 0) return DataResponse::ValidateFail(__('messages.error',[
                'info' => 'Check list might include package that has been paid',
            ]));

            if($package->status_id == 9) $obj->delivered_package_count += 1;
            if($package->status_id == 19) $obj->failed_with_fee_count +=1;
            $obj->total_delivery_fee += ($package->cod ? $package->delivery_fee : 0) + $package->extra_charge + $package->additional_fee;
            // $calPackage = GeneralSettingService::calculatePackageFee($package->zone_code,$package->price,$package->billed_kg,$package->actual_kg,$package->payer,$package->cod);
            // $totalPackages += 1;
            $obj->total_packages +=1;
            $obj->total_taxi_fee += $package->taxi_fee;
            $obj->driver_total += $package->driver_total;
            $obj->merchant_total += $package->merchant_total;
            $rowTotal = self::getPackageTotal($type,$package->cod,$package->price,$package->taxi_fee,$package->extra_charge,$package->additional_fee,$package->delivery_fee,$package->payer);
            $obj->total_due_amount += $rowTotal;
            // Log::info($rowTotal);
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
        $qP = Payment::fromRaw('payments as p')->join('users as d','d.id','p.payer_id')
        ->where('p.is_deleted',0)
        ->where('p.approved',$isApproved)
        ->join('users as ap','ap.id','p.receiver_uid')
        ->where('payer_type',$type)
        ->selectRaw('p.is_settled,p.payment_datetime,p.package_count,ap.user_name as booked_user,p.payable_amount,p.id as payment_id,d.user_name as payer_name,p.exchange_rate,p.taxi_fee,p.approved,p.breakdown_notes')
        ->orderByDesc('p.payment_datetime');

        if($payeeOrPayerId) $qP->where('p.payer_id',$payeeOrPayerId);
        if($startDate && $endDate){
            $startDate = Helper::dateYMD($startDate);
            $endDate = Helper::dateYMD($endDate);
            $qP->where(function($q) use($startDate,$endDate){
                $q->whereBetween('payment_datetime',[$startDate,$endDate])->orWhereDate('payment_datetime','<=',$endDate);
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

        $disbursements = Disbursement::fromRaw('disbursements as dis')
        ->where('dis.is_deleted',0)
        ->where('dis.type','payment')
        ->where('dis.approved',$isApproved)
        ->join('users as d','d.id','dis.payee_id')
        ->join('users as ap','ap.id','dis.receiptionist_uid')
        ->where('payee_type',$type)
        ->selectRaw('dis.is_settled,dis.payment_datetime,dis.package_count,ap.user_name as booked_user,dis.payable_amount,dis.id as payment_id,d.user_name as payer_name,dis.exchange_rate,dis.taxi_fee,dis.approved,dis.breakdown_notes')
        ->orderByDesc('dis.payment_datetime')
        ->get();
        foreach($disbursements as $d){
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
        if(!$transactionType || $transactionType == 'receive'){
            $qP = Payment::fromRaw('payments as p')->join('users as d','d.id','p.payer_id')
            ->where('p.is_deleted',0)
            ->join('users as ap','ap.id','p.receiver_uid')
            ->where('payer_type',$type)
            ->selectRaw('p.is_settled,p.payment_datetime,p.package_count,ap.user_name as booked_user,p.payable_amount,p.id as payment_id,p.delivery_fee,p.cod_amount,d.user_name as payer_name,d.user_name as merchant_name,p.exchange_rate,p.taxi_fee,p.approved,p.breakdown_notes,p.remarks')
            ->orderByDesc('p.payment_datetime');
            if($payeeOrPayerId) $qP->where('p.payer_id',$payeeOrPayerId);
            if($startDate && $endDate){
                $startDate = Helper::dateYMD($startDate);
                $endDate = Helper::dateYMD($endDate);
                $qP->where(function($q) use($startDate,$endDate){
                    $q->whereBetween('payment_datetime',[$startDate,$endDate])->orWhereDate('payment_datetime','<=',$endDate);
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
            $disbursements = Disbursement::fromRaw('disbursements as dis')
            ->where('dis.is_deleted',0)
            ->where('dis.type','payment')
            ->join('users as d','d.id','dis.payee_id')
            ->join('users as ap','ap.id','dis.receiptionist_uid')
            ->where('payee_type',$type)
            ->selectRaw('dis.is_settled,dis.payment_datetime,dis.package_count,dis.cod_amount,dis.delivery_fee,ap.user_name as booked_user,dis.payable_amount,dis.id as payment_id,d.user_name as merchant_name,dis.exchange_rate,dis.taxi_fee,dis.approved,dis.breakdown_notes,dis.remarks')
            ->orderByDesc('dis.payment_datetime')
            ->get();
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
            $pmt->total = $totalUSD + $totalKHR_to_USD;
            $pmt->status_code = $pmt->is_settled ? 'Settled' : 'Pending';
            unset($pmt->payment_datetime);
        }
        return DataResponse::Pagination($payments,$req);
    }

    public static function getDriverCommissionInfo($driverCommissions,$driverId){
        $dc = (object)[
            'normal_pickup_commission' => 0,
            'normal_delivery_commission' => 0,
            'fast_pickup_commission' => 0,
            'fast_delivery_commission' => 0
        ];
        foreach($driverCommissions as $driverComm){
            if($driverComm->driver_id == $driverId){
                    if($driverComm->delivery_type == 'fast'){
                    $dc->fast_pickup_commission = $driverComm->pickup_commission;
                    $dc->fast_delivery_commission = $driverComm->delivery_commission;
                }
                if($driverComm->delivery_type == 'normal'){
                    $dc->normal_pickup_commission = $driverComm->pickup_commission;
                    $dc->normal_delivery_commission = $driverComm->delivery_commission;
                }
            }
        }

        return $dc;
    }

    public function deletePayment($id,$trxType,$type,$user){
        $validType = $this->validType($type);
        if($validType->error) return $validType;
        if($trxType=='receive'){
            $payment = Payment::where('is_deleted',0)->where('company_id',$user->company_id)->orderByDesc('id')->find($id);
            if(!$payment) return DataResponse::NotFound('Payment not found');
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
        }else if($trxType == 'disbursement'){
            $payment = Disbursement::where('is_deleted',0)->where('company_id',$user->company_id)->where('type','payment')->orderByDesc('id')->find($id);
            if(!$payment) return DataResponse::NotFound('Payment not found');
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
            Package::where('disbursement_id',$id)->update([
                $pmtKey => null,
                // $type.'_payment_id' => null,
            ]);
        }

        return DataResponse::JsonResult(null,false,__('messages.deleted',[
            'info' => 'Payment'
        ]));


    }

    public function deleteSettlePayment($id,$trxType,$type,$user){
        $validType = $this->validType($type);
        if($validType->error) return $validType;
        if(!in_array($trxType,['receive','disbursement'])) return DataResponse::ValidateFail(__('messages.info',[
            'info' => 'Please select payment type'
        ]));
        if($trxType =='receive'){
            $payment = Payment::where('is_deleted',0)->where('company_id',$user->company_id)->orderByDesc('id')->find($id);
            if(!$payment) return DataResponse::NotFound('Payment not found');
            // if($payment->is_settled) return DataResponse::Duplicated(__('messages.info',[
            //     'info' => 'Payment has already been settled'
            // ]));
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
        }else if($trxType == 'disbursement'){
            $payment = Disbursement::where('is_deleted',0)->where('company_id',$user->company_id)->where('type','payment')->orderByDesc('id')->find($id);
            if(!$payment) return DataResponse::NotFound('Payment not found');
            // if($payment->is_settled) return DataResponse::Duplicated(__('messages.info',[
            //     'info' => 'Payment has already been settled'
            // ]));
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
            Package::where('disbursement_id',$id)->update([
                $pmtKey => null,
                // $type.'_payment_id' => null,
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

    public static function preparePaymentPackageAmount($paymentDetails,$paymentId){
        $converter = (object)[
            'total_usd' => 0,
            'total_khr' => 0
        ];
        foreach($paymentDetails as $d){
            if($d->payment_id == $paymentId){
                if($d->currency_code == 'USD'){
                    $converter->total_usd += $d->amount;
                }else{
                    $converter->total_khr += $d->amount;
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
                    $dis = Disbursement::where('is_deleted',0)->where('type','payment')->find($p->id);
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
                'info' => 'Approved'
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
        if(!isset($paymentIds[0])) return DataResponse::ValidateFail(__('messages.info',[
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
                    if($pmt->approved) return DataResponse::ValidateFail(__('messages.info',[
                        'info' => 'check list includes approved payment'
                    ]));
                    $pmt->update([
                        'is_settled' => 1,
                        'settled_datetime' => now(),
                        'settled_uid' => $user->id,
                    ]);
                }else if($p['payment_type'] == 'disbursement'){
                    $dis = Disbursement::where('is_deleted',0)->where('type','payment')->find($p->id);
                    if(!$dis) return DataResponse::ValidateFail(__('messages.info',[
                        'info' => 'check list includes invalid payment'
                    ]));
                    if($dis->approved) return DataResponse::ValidateFail(__('messages.info',[
                        'info' => 'check list includes approved payment'
                    ]));
                    $dis->update([
                        'is_settled' => $user->id,
                        'setteled_datetime' => now(),
                        'settled_uid' => $user->id,
                    ]);
                }
            }
            DB::commit();
            return DataResponse::JsonResult(null,false,__('messages.info',[
                'info' => 'Settled'
            ]));
        }catch(Exception $e){
            DB::rollBack();
        }
    }

    public function getBalance(Request $req,$user,$type='driver'){
        $driverId = $req->driver_id ?? null;
        $merchantId = $req->merchant_id ?? null;
        $userId = $driverId ?? $merchantId;
        $qP = User::from('users as d')
            ->where('d.account_type',$type)
            ->where('d.is_deleted', 0)
            ->where('d.company_id', $user->company_id) // Uncomment if needed
            ->join('packages as p', 'p.'.$type.'_id', '=', 'd.id')
            ->where('p.is_deleted',0)
            ->whereIn('p.status_id',[9,19])
            // ->join('payments as pmt','p.driver_payment_id','pmt.id')
            // ->where('pmt.is_settled',0)
            ->selectRaw('p.additional_fee,p.extra_charge,p.payer,p.cod,p.delivery_fee,p.price,p.taxi_fee,p.extra_charge,p.delivered_datetime,p.failed_datetime,d.id as driver_id,d.id,d.user_name as driver_name,d.code,p.status_id,p.updated_at');
            // ->groupBy(['d.id','pmt.payable_amount',DB::raw('DATE(p.delivered_datetime)'),DB::raw('DATE(p.failed_datetime)')]);
        if($userId){
            $qP->where('d.id',$userId);
        }
        $drivers = $qP->get();
        $totalPackages = 0;
        $totalAmount = 0;

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
        })->map(function ($group, $key) use(&$totalPackages,&$totalAmount) {
            // Extract date and driver_id from the key
            [$date, $driver_id] = explode('|', $key);

            // Sum the package counts for this group

            $packageTotal = $group->count(); // Count items in the group (equivalent to summing 1 per item)
            $totalPrice = $group->where('cod',1)->sum('price');
            $representative = $group->first();
            $fee = $group->where('payer','receiver')->sum('delivery_fee') + $group->sum('extra_charge') + $group->sum('additional_fee');
            $amount = $totalPrice + $fee;
            $totalPackages += $packageTotal;
            $totalAmount += $amount;
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
            'total_amount' => number_format($totalAmount,2)
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
            if($type != 'all') return (object)['total' => number_format($totalPickUpCommission,2)];
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
                'total' => number_format($totalFastDeliveryCommission + $totalNormalDeliveryCommission,2),
                'total_fast_delivery' => number_format($totalFastDeliveryCommission,2),
                'total_normal_delivery' => number_format($totalNormalDeliveryCommission,2),
            ];
        }
        //
        return (object)[
            'total_pickup' => number_format($totalPickUpCommission,2),
            'total_fast_delivery' => number_format($totalFastDeliveryCommission,2),
            'total_normal_delivery' => number_format($totalNormalDeliveryCommission,2)
        ];
    }

    public function updateDeliveryPackage(Request $req,$type,$user){
        $id = $req->id;
        $validate = validator($req->all(),[
            'cod' => 'required|in:1,0',
            'price' => 'nullable|numeric',
            'payer' => 'required|in:receiver,sender',
            'taxi_fee' => 'nullable|numeric'
        ]);
        if($validate->fails()) return DataResponse::ValidateFail($validate->errors()->first());
        $inputs = $validate->validated();
        $joinCallback = function ($join) use ($type) {
            $fkKey = $type ? $type . '_payment_id' : null;
            if ($fkKey) {
                // Join based on the specified type (driver or merchant)
                $join->on('dpmt.id', '=', "p.$fkKey");
            } else {
                // If type is null, join on both driver_payment_id and merchant_payment_id
                $join->on(function ($query) {
                    $query->whereColumn('dpmt.id', 'p.driver_payment_id')
                        ->orWhereColumn('dpmt.id', 'p.merchant_payment_id');
                });
            }
        };
        // $package = Package::where('is_deleted',0)->where('company_id',$user->company_id)->find($id);
        $package = Package::fromRaw('packages as p')->where('p.company_id',$user->company_id)
        ->leftJoin('payments as dpmt',$joinCallback)
        ->selectRaw('p.extra_charge,p.id,p.taxi_fee,p.cod,p.payer,p.zone_code,p.price,p.billed_kg,p.actual_kg,p.driver_payment_id,p.merchant_payment_id')
        ->where('p.id',$id)->first();
        if(!$package) return DataResponse::NotFound(__('messages.not_found',[
            'info' => 'Package'
        ]));
        if($package->driver_payment_id) return DataResponse::Duplicated(__('messages.info',[
            'info' => 'It seems like you try to update package which is on payment pending or paid with driver'
        ]));
        if($package->merchant_payment_id) return DataResponse::Duplicated(__('messages.info',[
            'info' => 'It seems like you try to update package which is on payment pending or paid with merchant'
        ]));
        $cod = $inputs['cod'];
        $payer = $inputs['payer'];
        $taxi_fee = $inputs['taxi_fee'] ?? $package->taxi_fee;
        $calFee = GeneralSettingService::calculatePackageFee($package->zone_code,$package->price,$package->billed_kg,$package->actual_kg,$payer,$cod,$package->extra_charge,$user,$taxi_fee,$package->merchant_id);
        $inputs['driver_total'] = $calFee->driver_total;
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
            // 'bank_id' => 'nullable|int',
            'remarks' => 'nullable|string|max:500',
            'start_date' => 'nullable',
            'end_date' => 'nullable',
            'exchange_rate' => 'nullable|numeric',
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
            if($cash) $breakDownNotes .= 'Cash: USD '.$cash.'|';
            if($cashKh) $breakDownNotes .= 'Cash: KHR '.$cashKh.'|';
            if($bankAmount) $breakDownNotes .= $validPayment->bank_name.': USD '.$bankAmount.'|';
            if($bankAmountKh) $breakDownNotes .= $validPayment->bank_name.': KHR '.$bankAmountKh.'|';
        }
        $breakDownNotes = trim($breakDownNotes, '| ');
        DB::beginTransaction();
        try{
            $disArr = [
                'payee_id' => $payeeId,
                'payee_type' => $type,
                'taxi_fee' => $validPackages->total_taxi_fee,
                'delivery_fee' => $validPackages->total_delivery_fee,
                'payable_amount' => $dueAmount,
                'create_uid' => $user->id,
                'receiver_uid' => $user->id,
                'amount' => $dueAmount,
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
                'branch_id' => $user->branch_id
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
                $type.'_disbursement_id' => $paymentId
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
        if($validPackages->error) return $validPackages;
        $packageIds = $validPackages->package_ids;
        $orderIds = $validPackages->order_ids;
        $deliveryRate = $validPackages->delivery_rate;
        $pickupRate = $validPackages->pickup_rate;
        $exchangeRate = $inputs['exchange_rate'] ?? GeneralSettingService::getLatestXRate()->buy_rate;
        $cashKh = $inputs['cash_kh'] ?? 0;
        $cash = $inputs['cash'] ?? 0;
        $bankId = $inputs['bank_id'] ?? null;
        $bankAmount = $inputs['bank_amount'] ?? 0;
        $bankAmountKh = $inputs['bank_amount_kh'] ?? 0;
        $dueAmount = $validPackages->grand_total;
        // var_dump($dueAmount);
        // return $validPackages;
        $validPayment = $this->validPayment($cash,$cashKh,$bankAmount,$bankAmountKh,$bankId,$dueAmount,$exchangeRate);
        if($validPayment->error) return $validPayment;
        $breakDownNotes = null;
        if($dueAmount > 0){
            if($cash) $breakDownNotes .= 'Cash: USD '.$cash.'|';
            if($cashKh) $breakDownNotes .= 'Cash: KHR '.$cashKh.'|';
            if($bankAmount) $breakDownNotes .= $validPayment->bank_name.': USD '.$bankAmount.'|';
            if($bankAmountKh) $breakDownNotes .= $validPayment->bank_name.': KHR '.$bankAmountKh.'|';
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
                'pickup_rate' => $pickupRate,
                'delivery_rate' => $deliveryRate,
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



    private function validCommissionPackage($payeeId,$type,$startDate,$endDate){
        $validType = $this->validType($type);
        if($validType->error) return $validType;
        $deliveredCount = 0;
        $pickUpCount = 0;
        $obj = (object)[
            'error' => false,
            'total_pickup' => 0,
            'total_delivered' => 0 ,
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

        $qP = Package::selectRaw('id,status_id,driver_id,driver_disbursement_id')
        ->whereIn('status_id',[9,19])
        ->whereNotNull('driver_id');
        if($startDate && $endDate){
            $startDate = date('Y-m-d',strtotime($startDate));
            $endDate = date('Y-m-d',strtotime($endDate));
            $qP->whereBetween('delivered_datetime',[$startDate,$endDate])->orWhereDate('delivered_datetime','<=',$endDate);
        }
        if($payeeId) $qP->where('driver_id',$payeeId);
        $packages = $qP->get();
        $qO = Order::where('is_deleted',0)->where('status_id',5);
        if($payeeId) $qO->where('driver_id',$payeeId);
        if($startDate && $endDate){
            $startDate = date('Y-m-d',strtotime($startDate));
            $endDate = date('Y-m-d',strtotime($endDate));
            $qO->whereBetween('order_datetime',[$startDate,$endDate])->orWhereDate('order_datetime','<=',$endDate);
        }
        $orders = $qO->get();
        $qDc = DriverCommission::where('is_deleted',0)->selectRaw('delivery_type,pickup_commission,delivery_commission,use_percentage');
        if($payeeId) $qDc->where($type.'_id',$payeeId);
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
            if(!$order->driver_commission_id) {
                $pickUpCount += $order->qty;
                $obj->order_ids[] = $order->id;
            }

        }
        foreach($packages as $package){
            if(!$package->driver_commission_id) {
                if($package->status_id == 9) $deliveredCount += 1;
                if($package->status_id == 19) $failedWithFeeCount +=1;
                if($package->cod) $obj->total_taxi_fee += $package->delivery_fee;
                if($package->taxi_fee) $obj->total_taxi_fee += $package->taxi_fee;
                $obj->package_ids[] = $package->id;
            }

        }
        $obj->total_pickup = $pickUpCount * $dc->normal_pickup_commission;
        $obj->total_delivered = $deliveredCount * $dc->normal_delivery_commission;
        $obj->grand_total = $obj->total_pickup + $obj->total_delivered;
        $obj->total_package = $pickUpCount + $deliveredCount;
        $obj->total_delivered_package = $deliveredCount;
        $obj->total_pickup_package = $pickUpCount;
        $obj->delivery_rate = $dc->normal_delivery_commission;
        $obj->pickup_rate = $dc->normal_pickup_commission;
        $obj->failed_with_fee_count = $failedWithFeeCount;
        if(empty($obj->pacakage_ids) && empty($obj->order_ids)){
            return DataResponse::NotFound('No package found');
        }
        return $obj;
    }

}
