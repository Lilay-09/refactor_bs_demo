<?php

namespace App\Services;

use App\DTO\MerchantRequestedSettlementDTO;
use App\Enums\PaymentStatus;
use App\Models\Disbursement;
use App\Models\Package;
use DataResponse;
use Illuminate\Http\Request;

class MerchantTransactionServiceImpl implements MerchantTransactionService
{
    // Your service methods go here
    public function getRequestedSettlement(Request $req,object $authUSer):object{
        $qPmt = Disbursement::query()
        ->where('payment_status_id',PaymentStatus::REQUESTED->value)
        ->with(['merchant:id,username,phone','pmtPackages:disbursement_id,package_id','pmtPackages.package:id,driver_cod_usd,driver_cod_khr'])
        ->where('is_deleted',false)
        ->where('payee_type','merchant');
        $select = ['id','requested_date',
            // 'id','payee_id','payee_type','package_count','payment_date'
        ];

        $callback = function($q) {
            $q->merchant_name = $q->merchant->username;
            $q->merchant_phone = $q->merchant->phone;
            $q->finish_date = $q->payment_datetime;
            $q->fees = $q->delivery_fee;
            $q->driver_cod_usd = 0;
            $q->driver_cod_khr = 0;
            foreach($q->pmtPackages as $pkg){
                $q->driver_cod_usd += $pkg->package->driver_cod_usd;
                $q->driver_cod_khr += $pkg->package->driver_cod_khr;
            }

            $q->cod_to_be_paid_usd = $q->amount_due_usd;
            $q->cod_to_be_paid_khr = $q->amount_due_khr;

            // $disbursement->package_count = $packageIds->count();
            unset($q->pmtPackages,$q->merchant);
            // return $q;
            return MerchantRequestedSettlementDTO::fromModel($q);

        };

        return DataResponse::PaginationV1($qPmt,$req,'',[],500,$callback,$select);
    }
}
