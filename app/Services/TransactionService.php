<?php

namespace App\Services;

use App\Models\Bank;
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
        $fkKey = $type.'_payment_id';
        $packages = Package::fromRaw('packages as p')->where('p.company_id',$user->company_id)
        ->join('users as d','d.id','p.driver_id')
        ->join('tracking_statuses as ts','ts.id','p.status_id')
        ->join('users as m','m.id','p.merchant_id')
        ->leftJoin('payments as dpmt','dpmt.id','p.'.$fkKey) //** if driver paid or unpaid */
        // ->leftJoin('payments as mpmt','mpmt.id','p.merchant_payment_id') //** if driver paid or unpaid */
        ->whereIn('p.status_id',[9,19]) //* delivered and failed with fee
        ->selectRaw('p.remarks,p.cod,p.price,p.taxi_fee,p.payer,p.delivery_fee,p.merchant_total,p.driver_total,m.user_name as merchant_name,m.phone as merchant_phone,dpmt.approved,d.user_name as driver_name,p.status_id,p.id as package_id,d.id as driver_id,p.qr_code,ts.name as status_code,p.delivered_datetime,p.failed_datetime,p.zone_code,p.receiver_phone,p.delivery_type,'.$fkKey)
        ->get();
        $statusKey = $type.'_payment_status';
        foreach($packages as $package){
            $package->cod = $package->cod?'Yes':'No';
            $package->{$statusKey} = !$package->driver_payment_id ? 'Unpaid':($package->approved ? 'Approved':'Pending');
            $package->datetime = ($package->status_id == 9 && ($package->delivered_datetime || $package->delivered_datetime)) ? Helper::formatCustomDateTime($package->delivered_datetime) : Helper::formatCustomDateTime($package->failed_datetime);
            // $package->total = $
        }
        return DataResponse::Pagination($packages,$req);
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
            'exchange_rate' => 'required|string'
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
        $exchangeRate = $inputs['exchange_rate'];
        $cashKh = $inputs['cash_kh'] ?? 0;
        $cash = $inputs['cash'] ?? 0;
        $bankId = $inputs['bank_id'] ?? null;
        $bankAmount = $inputs['bank_amount'] ?? 0;
        $bankAmountKh = $inputs['bank_amount_kh'] ?? 0;
        $dueAmount = $validPackages->total_due_amount;
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
            $createPayment = Payment::create([
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
                'payment_datetime' => now(),
                'breakdown_notes' => $breakDownNotes,
                'company_id' => $user->company_id,
                'branch_id' => $user->branch_id
            ]);
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
            // DB::commit();
            // return Package::whereIn('id',$packageIds)->get();
            return DataResponse::JsonResult(Payment::find($paymentId),false,__('messages.created',[
                'info' => 'Payment'
            ]));
        }catch(Exception $e){
            Log::error($e->getMessage());
            Log::error($e->getTraceAsString());
            DB::rollBack();
            return DataResponse::Error(__('messages.error',['info' => 'Fail to receive']));
        }
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
        $totalAmountUSD = $cash + $bankAmount;
        $totalAmountKHR = $cashKh + $bankAmountKh;
        $originalCashKh = 0;
        $originalBankAmtKh = 0;
        if($totalAmountUSD && $totalAmountKHR){
            $totalAmountKHR_to_USD = $totalAmountKHR/$exchangeRate;
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
                return DataResponse::ValidateFail(__('messages.info',[
                        'info' => 'If Cash Amount USD '.$cash.',so bank amount must be USD '.$additionalSuggestion
                    ]));
            }
            if($totalAmountUSD < $dueAmount) return DataResponse::ValidateFail(__('messages.info',[
                'info' => 'Payment amount must be $'.$dueAmount.' remaining amount ($'.$dueAmount - $totalAmountUSD.')'
            ]));
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
            'total_taxi_fee' => 0,
            'driver_total' => 0,
            'merchant_total' => 0,
            'total_due_amount' => 0,
            'total_package_price'=>0,
            'total_amount' => 0
        ];
        foreach($packageIds as $key=>$id){
            $package = Package::where($type.'_id',$driverOrMerchantId)->where('is_deleted',0)->whereIn('status_id',[9,19])->find($id);
            if(!$package){
                return DataResponse::ValidateFail(__('messages.info',['info' => 'Invalid package'.' on row ('.($key+1).')']));
            }
            if($package->{$type.'_payment_id'} > 0) return DataResponse::ValidateFail(__('messages.error',[
                'info' => 'Check list might include package that has been paid',
            ]));
            if($package->status_id == 9) $obj->delivered_package_count += 1;
            $obj->total_delivery_fee += $package->delivery_fee;

            // $calPackage = GeneralSettingService::calculatePackageFee($package->zone_code,$package->price,$package->billed_kg,$package->actual_kg,$package->payer,$package->cod);
            // $totalPackages += 1;
            $obj->total_packages +=1;
            $obj->total_taxi_fee += $package->taxi_fee;
            $obj->driver_total += $package->driver_total;
            $obj->merchant_total += $package->merchant_total;
            if($package->cod){
                $obj->total_cod += $package->price;
                $payableAmt = $package->price;
                $taxiFee = $package->taxi_fee ?? 0;
                if($package->payer == 'receiver' && $type == 'driver') $payableAmt += $package->delivery_fee;
                if($package->payer == 'sender' && $type == 'merchant') $payableAmt += $package->delivery_fee;
                if($type =='merchant') $payableAmt += $taxiFee; //** add taxi for merchant */
                else if($type == 'driver') $payableAmt = $payableAmt - $taxiFee; //** sub taxi for driver */
                $obj->total_due_amount = $payableAmt;
            }else{
                $taxiFee = $package->taxi_fee ?? 0;
                $payableAmt = 0;
                if($package->payer == 'receiver' && $type == 'driver') $payableAmt += $package->delivery_fee;
                if($package->payer == 'sender' && $type == 'merchant') $payableAmt += $package->delivery_fee;
                if($type =='merchant') $payableAmt += $taxiFee; //** add taxi for merchant */
                else if($type == 'driver') $payableAmt = $payableAmt - $taxiFee; //** sub taxi for driver */
                $obj->total_due_amount = $payableAmt;
            }
            var_dump($obj->total_due_amount);
            $obj->total_amount += $package->price + $package->delivery_fee;
            $obj->total_package_price += $package->price;
        }
        return DataResponse::JsonRaw([
            'error' => false,
            'pacakage_ids' => $packageIds,
            'total_delivery_fee' => round($obj->total_delivery_fee,2),
            'total_package' => $obj->total_packages,
            'driver_total' => $obj->driver_total,
            'total_taxi_fee' => $obj->total_taxi_fee,
            'merchant_total' => $obj->merchant_total,
            'total_package_price' => $obj->total_package_price,
            'total_cod' => $obj->total_cod,
            'total_due_amount' => $obj->total_due_amount,
            'total_amount' => $obj->total_amount,
            'delivered_package_count' => $obj->delivered_package_count
        ]);
    }

    public function getPayments(Request $req,$user){
        $qP = Payment::fromRaw('payments as p')->join('users as d','d.id','p.payer_id')
        ->where('p.is_deleted',0)
        ->selectRaw('p.id as payment_id,d.user_name as payer_name,p.exchange_rate,p.amount,p.taxi_fee,p.approved');
        $payments = $qP->get();
        $paymentDetails = PaymentDetail::get();
        foreach($payments as $pmt){
            $pmt_details = $this->preparePaymentPackageAmount($paymentDetails,$pmt->payment_id);
            $totalUSD = $pmt_details->total_usd;
            $totalKHR = $pmt_details->total_khr;
            $pmt->total_usd = Helper::displayMoney($totalUSD,'USD');
            $pmt->total_khr = Helper::displayMoney($totalKHR,'KHR');
            $totalKHR_to_USD = $totalKHR/$pmt->exchange_rate;
            $totalKHR_to_USD = floor($totalKHR_to_USD * 100) / 100;
            $pmt->total = $totalUSD + $totalKHR_to_USD;
        }
        return DataResponse::Pagination($payments,$req);
    }

    public function getApprovedPayments(Request $req,$user){
        $qP = Payment::fromRaw('payments as p')->join('users as d','d.id','p.payer_id')
        ->where('p.is_deleted',0)
        ->where('p.approved',1)
        ->selectRaw('p.id as payment_id,d.user_name as payer_name,p.exchange_rate,p.amount,p.taxi_fee');
        $payments = $qP->get();
        $paymentDetails = PaymentDetail::get();
        foreach($payments as $pmt){
            $pmt_details = $this->preparePaymentPackageAmount($paymentDetails,$pmt->payment_id);
            $totalUSD = $pmt_details->total_usd;
            $totalKHR = $pmt_details->total_khr;
            $pmt->total_usd = Helper::displayMoney($totalUSD,'USD');
            $pmt->total_khr = Helper::displayMoney($totalKHR,'KHR');
            $totalKHR_to_USD = $totalKHR/$pmt->exchange_rate;
            $totalKHR_to_USD = floor($totalKHR_to_USD * 100) / 100;
            $pmt->total = $totalUSD + $totalKHR_to_USD;
        }
        return DataResponse::Pagination($payments,$req);
    }

    public function deletePayment($id,$type,$user){
        $validType = $this->validType($type);
        if($validType->error) return $validType;
        $payment = Payment::where('is_deleted',0)->where('company_id',$user->company_id)->orderByDesc('id')->find($id);
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
            $type.'_payment_id' => null,
        ]);

        return DataResponse::JsonResult(null,false,__('messages.deleted',[
            'info' => 'Payment'
        ]));

    }

    private function validType($type){
        $validType = ['driver','merchant'];
        if(!in_array($type,$validType)) return DataResponse::ValidateFail('Invalid type');
        return DataResponse::JsonResult(null);
    }

    private function preparePaymentPackageAmount($paymentDetails,$paymentId){
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
        $paymentIds = $req->payments ?? [];
        if(!isset($paymentIds[0])) return DataResponse::ValidateFail(__('messages.info',[
            'info' => 'Please check payments you want to approve'
        ]));

        DB::beginTransaction();
        try{
            foreach($paymentIds as $id){
                $pmt = Payment::where('is_deleted',0)->find($id);
                if(!$pmt) return DataResponse::ValidateFail(__('messages.info',[
                    'info' => 'check list includes invalid payment'
                ]));
                $pmt->update([
                    'approved' => 1,
                    'approved_uid' => $user->id,
                ]);
            }
            DB::commit();
            return DataResponse::JsonResult(null,__('messages.info',[
                'info' => 'Approved'
            ]));
        }catch(Exception $e){
            DB::rollBack();
        }
    }

    public function settlePayments(Request $req,$user){
        $paymentIds = $req->payments ?? [];
        if(!isset($paymentIds[0])) return DataResponse::ValidateFail(__('messages.info',[
            'info' => 'Please select payments you want to settle'
        ]));

        DB::beginTransaction();
        try{
            foreach($paymentIds as $id){
                $pmt = Payment::where('is_deleted',0)->where('approved',1)->find($id);
                if(!$pmt) return DataResponse::ValidateFail(__('messages.info',[
                    'info' => 'check list includes invalid payment'
                ]));
                $pmt->update([
                    'is_settled' => 1,
                    'settled_uid' => $user->id,
                ]);
            }
            DB::commit();
            return DataResponse::JsonResult(null,__('messages.info',[
                'info' => 'Settled'
            ]));
        }catch(Exception $e){
            DB::rollBack();
        }
    }

    public function getDriverBalance(Request $req,$user){
        $qP = User::from('users as d')
            ->where('d.is_deleted', 0)
            ->where('d.company_id', $user->company_id) // Uncomment if needed
            ->join('packages as p', 'p.driver_id', '=', 'd.id')
            ->leftJoin('payments as pmt','p.driver_payment_id','pmt.id')
            ->selectRaw('d.id, count(p.id) as package_count,SUM(p.price) as amount,d.user_name as driver_name,d.code,pmt.payable_amount')
            ->groupBy(['d.id','pmt.payable_amount']);
        $drivers = $qP->get();
        $totalPackages = 0;
        $totalAmount = 0;
        foreach ($drivers as $driver){
            $totalPackages += $driver->package_count;
            $totalAmount += $driver->amount;
            // if($driver->)
        }
        return DataResponse::Pagination($drivers,$req,__('messages.Get List'),[
            'total_packages' => $totalPackages,
            'total_amount' => $totalAmount
        ]);
    }

    public function updateDeliveryPackage(Request $req,$type,$user){
        $id = $req->id;
        $validate = validator($req->all(),[
            'cod' => 'required|in:1,0',
            'price' => 'nullable|numeric',
            'payer' => 'required|in:receiver,merchant',
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
        ->selectRaw('p.id,p.taxi_fee,p.cod,p.payer,p.zone_code,p.price,p.billed_kg,p.actual_kg,p.driver_payment_id,p.merchant_payment_id')
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
        $calFee = GeneralSettingService::calculatePackageFee($package->zone_code,$package->price,$package->billed_kg,$package->actual_kg,$payer,$cod,$taxi_fee);
        $inputs['driver_total'] = $calFee->driver_total;
        $inputs['merchant_total'] = $calFee->merchant_total;
        $package->update($inputs);
        return DataResponse::JsonResult(null,__('messages.updated',[
            'info' => 'Package'
        ]));
    }
}
