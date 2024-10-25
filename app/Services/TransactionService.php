<?php

namespace App\Services;

use App\Models\Bank;
use App\Models\Package;
use App\Models\Payment;
use App\Models\PaymentDetail;
use DataResponse;
use DB;
use Exception;
use Illuminate\Http\Request;
use Log;

class TransactionService
{
    // Your service methods go here'
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
            'exchange_rate' => 'nullable|string'
        ]);
    }

    //* type must be one of driver or merchant
    public function receiverPaymentService(Request $req,$user,$type){
        $validType = $this->validType($type);
        if($validType->error) return $validType;
        $validate = self::receivePaymentValidation($req,$type);
        if($validate->fails()) return DataResponse::ValidateFail($validate->errors()->first());
        $inputs = $validate->validated();
        $payerId = $inputs['driver_id'] ?? $inputs['merchant_id'];
        $packageIds = $inputs['packages'];
        $validPackages = $this->validPackages($packageIds,$payerId,$type);
        if($validPackages->error) return $validPackages;
        $exhangeRate = $inputs['exchange_rate'] ?? 4100;
        $cashKh = $inputs['cash_kh'] ?? 0;
        $cash = $inputs['cash'] ?? 0;
        $bankId = $inputs['bank_id'] ?? null;
        $bankAmount = $inputs['bank_amount'] ?? 0;
        $bankAmountKh = $inputs['bank_amount_kh'] ?? 0;
        $dueAmount = $validPackages->driver_total;
        // $cashKhToUS = $cashKh / $exhangeRate;
        // $bankAmountKhToUS = $bankAmountKh / $exhangeRate;
        // $totalInputAmount = $cash + $cashKhToUS + $bankAmountKhToUS + $bankAmount;
        $validPayment = $this->validPayment($cash,$cashKh,$bankAmount,$bankAmountKh,$bankId,$dueAmount,$exhangeRate);
        if($validPayment->error) return $validPayment;

        $breakDownNotes = null;
        if($cash) $breakDownNotes .= 'Cash: USD '.$cash.'|';
        if($cashKh) $breakDownNotes .= 'Cash: KHR '.$cashKh.'|';
        if($bankAmount) $breakDownNotes .= $validPayment->bank_name.': USD '.$bankAmount.'|';
        if($bankAmountKh) $breakDownNotes .= $validPayment->bank_name.': KHR '.$bankAmountKh.'|';
        $breakDownNotes = trim($breakDownNotes, '| ');
        // return $breakDownNotes;
        DB::beginTransaction();
        try{
            $createPayment = Payment::create([
                'payer_id' => $payerId,
                'payer_type' => $type,
                'create_uid' => $user->id,
                'receiver_uid' => $user->id,
                'package_count' => '',
                'delivered_package_count' => '',
                'update_uid' => $user->id,
                'payment_datetime' => now(),
                'breakdown_notes' => $breakDownNotes,
                'company_id' => $user->company_id,
                'branch_id' => $user->branch_id
            ]);
            $paymentId = $createPayment->id;
            if($cash){
                PaymentDetail::create([
                    'payment_id' => $paymentId,
                    'method' => 'cash',
                    'amount' => $cash,
                    'original_amount' => $cash,
                    'currency_code' => 'USD'
                ]);
            }
            if($cashKh){
                PaymentDetail::create([
                    'payment_id' => $paymentId,
                    'method' => 'cash',
                    'amount' => $cashKh,
                    'original_amount' => $validPayment->original_cash_amount_kh,
                    'currency_code' => 'KHR'
                ]);
            }
            if($bankId){
                if($bankAmount){
                    PaymentDetail::create([
                        'payment_id' => $paymentId,
                        'method' => $validPayment->bank_name,
                        'amount' => $bankAmount,
                        'original_amount' => $bankAmount,
                        'currency_code' => 'USD'
                    ]);
                }

                if($bankAmountKh){
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
        $paymentSuggestion = $this->paymentSuggestion($cash,$cashKh,$bankAmount,$bankAmountKh,$dueAmount,$exhangeRate);
        if($paymentSuggestion->error) return $paymentSuggestion;
        $originalCashKh = $paymentSuggestion->original_cash_amount_kh;
        $originalBankAmtKh = $paymentSuggestion->original_bank_amount_kh;
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
                return DataResponse::ValidateFail(__('messages.info',[
                    'info' => 'Amount KHR must around (KHR '.$roundSuggestionAmtUp .' & KHR '.$suggestionAmt.') base on exchange_rate ('.$exchangeRate.')'
                ]));
            }
        }

        return DataResponse::JsonRaw([
            'error'=>false,
            'original_cash_amount_kh' => $originalCashKh,
            'original_bank_amount_kh' => $originalBankAmtKh
        ]);
    }

    public function validPackages($packageIds,$driverId,$type){
        $validType = $this->validType($type);
        if($validType->error) return $validType;
        $totalPackages = 0;
        $totalCod = 0;
        $totalDeliveryFee = 0;
        $deliveredPackageCount = 0;
        $driverTotal = 0;
        $merchantTotal = 0;
        foreach($packageIds as $key=>$id){
            $package = Package::where('driver_id',$driverId)->where('is_deleted',0)->whereIn('status_id',[9,19])->find($id);
            if(!$package){
                return DataResponse::ValidateFail(__('messages.info',['info' => 'Invalid package'.' on row ('.($key+1).')']));
            }
            if($package->{$type.'_payment_id'} > 0) return DataResponse::ValidateFail(__('messages.error',[
                'info' => 'Check list might include package that has been paid',
            ]));
            if($package->status_id == 9) $deliveredPackageCount += 1;
            $totalDeliveryFee += $package->delivery_fee;
            // $calPackage = GeneralSettingService::calculatePackageFee($package->zone_code,$package->price,$package->billed_kg,$package->actual_kg,$package->payer,$package->cod);
            $totalPackages += 1;
            $driverTotal += $package->driver_total;
            $merchantTotal += $package->merchant_total;
            $totalCod += $driverTotal;
        }
        return DataResponse::JsonRaw([
            'error' => false,
            'pacakage_ids' => $packageIds,
            'total_delivery_fee' => round($totalDeliveryFee,2),
            'total_package' => $totalPackages,
            'driver_total' => $driverTotal,
            'merchant_total' => $merchantTotal,
            'total_cod' => $totalCod,
            'delivered_package_count' => $deliveredPackageCount
        ]);
    }

    public function getPayments(Request $req,$user){
        $payments = Payment::where('is_deleted',0)->where('company_id',$user->company_id)->orderByDesc('id')->get();
        return DataResponse::Pagination($payments,$req);
    }

    public function deletePayment($id,$type,$user){
        $validType = $this->validType($type);
        if($validType->error) return $validType;
        $payment = Payment::where('is_deleted',0)->where('company_id',$user->company_id)->orderByDesc('id')->find($id);
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

    private function preparePaymentPackageAmount(){}
}
