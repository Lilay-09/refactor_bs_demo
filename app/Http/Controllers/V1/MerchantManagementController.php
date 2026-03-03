<?php

namespace App\Http\Controllers\V1;

use ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\MerchantPriceList;
use App\Models\TelegramSendLog;
use App\Models\User;
use App\Models\UserBank;
use App\Models\Zone;
use App\Services\BankServiceImpl;
use App\Services\GeneralSettingService;
use App\Services\UserService;
use Illuminate\Support\Facades\DB;
use Helper;
use Illuminate\Http\Request;

class MerchantManagementController extends Controller
{
    //
    protected $userClass = 'merchant';
    public function createMerchant(Request $req){
        $user = UserService::getAuthUser();
        $createMerchant = UserService::createOrUpdateUser($req,$this->userClass,$user);
        return ApiResponse::flex($createMerchant);
    }

    public function getMerchants(Request $req){
        $user = UserService::getAuthUser();
        $statusId = $req->status_id ?? null;
        $branchId = $req->branch_id ?? null;
        $search = $req->search;
        $priceList = DB::table('price_list_names as n')
        ->selectRaw('n.id,n.name,mpl.merchant_id,mpl.zone_code,mpl.zone_id')->join('merchant_price_list as mpl','mpl.price_list_id','n.id')
        ->get();
        $query = User::where('is_deleted',0)->where('company_id',$user->company_id)
        ->where('account_type',$this->userClass)
        ->with([
            'merchantType:id,name_en as names',
            'bank_accounts:id,currency,bank_name,bank_number,account_name,user_id,is_primary',
            'shop:id,owner_id,name_en as shop_name_en,name_km as shop_name_km,product_type_id,city,district,est_pcs',
            'shop.product_type:id,name',
            'telegramBot:id,user_id,group_name,group_id,bot_id,default_caption'
        ]);
        // ->selectRaw('id,cod_fee,code,name_km,username,email,gender,business_type,phone,client_type_id,address,cod,pin_address,photo_file_name,lock,has_account,photo_file_name');
        // ->select(['id','cod_fee','code','name_km','username','email','name_en',''])
        if($statusId !== null && $statusId>=0) {
            $query->where('lock',$statusId ? 0 : 1);
        }
        if($branchId){
            $query->where('branch_id',$branchId);
        }

        if($search){
            $query->where(function($q) use ($search){
                $q->where('code','ilike','%'.$search.'%')
                ->orWhere('username','ilike','%'.$search.'%')
                ->orWhere('name_km','ilike','%'.$search.'%')
                ->orWhere('phone','ilike','%'.$search.'%');
            });
        }
        $query->orderByDesc('id');
        // foreach($merhcants as $m){

        //     $merchantPriceList = $this->getMerchantPriceList($priceList,$m->id);
        //     $m->referrer = null;
        //     $m->image_url = Helper::getImageUrl($m->photo_file_name,$user->company_id,'user_profile');
        //     $m->price_list_name = $merchantPriceList?->name;
        //     $m->price_list_id = $merchantPriceList?->id;
        //     $m->zone_id = $merchantPriceList?->zone_id;
        //     $m->client_type = $m->merchantType?->name;
        //     $m->login_name = $m->login_name ?? $m->phone;
        //     $m->create_by = ($m->create_uid == $m->id) ? 'Self': 'Admin';
        //     foreach($m->bank_accounts as $b){
        //         if($b->is_primary) $m->bank_account = GeneralSettingService::concatBankInfo($b->bank_name,$b->bank_number,$b->account_name);
        //         if(!$b->bank_account) $m->bank_account = GeneralSettingService::concatBankInfo($b->bank_name,$b->bank_number,$b->account_name);
        //     }
        //     $m->image_url = Helper::getImageUrl($m->photo_file_name,$user->company_id,'user_profile');
        //     unset($m->merchantType,$m->bank_accounts,$m->photo_file_name);
        // }
        $callback = function($m) use($priceList,$user){
            $merchantPriceList = $this->getMerchantPriceList($priceList,$m->id);
            $m->referrer = null;
            $m->image_url = Helper::getImageUrl($m->photo_file_name,$user->company_id,'user_profile');
            $m->price_list_name = $merchantPriceList?->name;
            $m->price_list_id = $merchantPriceList?->id;
            $m->zone_id = $merchantPriceList?->zone_id;
            $m->client_type = $m->merchantType?->name;
            $m->login_name = $m->login_name ?? $m->phone;
            $m->create_by = ($m->create_uid == $m->id) ? 'Self': 'Admin';
            $m->shop_name_en = $m->shop?->shop_name_en;
            $m->shop_name_km = $m->shop?->shop_name_km;
            $m->city = $m->shop?->city;
            $m->district = $m->shop?->district;
            $m->est_pcs = $m->shop?->est_pcs;
            $m->product_type = $m->shop?->product_type?->name;
            unset($m->shop);
            foreach($m->bank_accounts as $b){
                if($b->is_primary) $m->bank_account = GeneralSettingService::concatBankInfo($b->bank_name,$b->bank_number,$b->account_name);
                if(!$b->bank_account) $m->bank_account = GeneralSettingService::concatBankInfo($b->bank_name,$b->bank_number,$b->account_name);
            }
            $m->image_url = Helper::getImageUrl($m->photo_file_name,$user->company_id,'user_profile');
            unset($m->merchantType,$m->bank_accounts,$m->photo_file_name);
            return $m;
        };
        return ApiResponse::PaginationV1($query,$req,'',[],1000,$callback);
    }

    public function getDefaultOptions(Request $req){
        $id = $req->id;
        $merchant = User::where('is_deleted',0)->with('merchantPriceList')->where('account_type','merchant')->selectRaw('id,cod,cod_fee')->where('id',$id)->first();
        if(!$merchant) return ApiResponse::NotFound();
        $merchant->cod = $merchant->cod ? "1":"0";
        $merchant->zone_code = $merchant->merchantPriceList?->zone_code;
        $merchant->zone_id = $merchant->merchantPriceList?->zone_id;
        $merchant->price_list_id = $merchant->merchantPriceList?->price_list_id;
        unset($merchant->merchantPriceList);
        return ApiResponse::JsonResult($merchant);
    }
    // public static function concatBankInfo($bankName,$bankNumber,$accountName){
    //     $info = $bankName;
    //     if($bankNumber) $info .= '|'.$bankNumber;
    //     if($accountName) $info .= '|'.$accountName;
    //     return $info;
    // }
    public function getOneMerchant(Request $req){
        $user = UserService::getAuthUser();
        $id = $req->id;
        $merchant = User::where('is_deleted',0)->where('company_id',$user->company_id)
        ->where('account_type',$this->userClass)
        ->with(['bank_accounts:id,bank_name,bank_number,currency,account_name,user_id,is_primary','shops:id,owner_id,name_en as shop_name_en,name_km as shop_name_km,phone,est_pcs,address,city,district,commune,product_type_id'])
        ->selectRaw('id,cod_fee,code,branch_id,name_km,username,email,gender,photo_file_name,business_type,phone,client_type_id,address,referrer_uid,cod,pin_address,login_name')
        ->find($id);
        $priceList = DB::table('price_list_names as n')
        ->selectRaw('n.id,n.name,mpl.merchant_id')
        ->join('merchant_price_list as mpl','mpl.price_list_id','n.id')
        ->where('mpl.merchant_id',$id)->first();
        if(!$merchant) return ApiResponse::NotFound(__('messages.not_found',['info' => 'Merchant']));
        $merchant->image_url = Helper::getImageUrl($merchant->photo_file_name,$user->company_id,'user_profile');
        $merchant->login_name = $merchant->login_name ?? $merchant->phone;
        if($priceList){
            $merchant->price_list_id = $priceList->id;

        }
        $cod = $merchant->cod;
        $merchant->cod = $cod ? "1":"0";
        unset($m->merchantType,$m->bank_accounts);
        return ApiResponse::JsonResult($merchant,__('messages.get one'));
    }

    private function getMerchantPriceList($rows,$merchantId){
        foreach($rows as $row){
            if($row->merchant_id == $merchantId){
                return $row;
            }
        }
        return null;
    }

    // private function getTelegramSendLogKeyByReceiverId(string $startDate, string $endDate, ?int $receiverId = null): Collection
    // {
    //     $qt = TelegramSendLog::query()
    //         ->whereDate('start', '>=', Helper::dateYMD($startDate))
    //         ->whereDate('start', '<=', Helper::dateYMD($endDate));

    //     if ($receiverId !== null) {
    //         $qt->where('receiver_id', $receiverId);
    //     }

    //     return $qt->orderByDesc('id')->get()->keyBy('receiver_id');
    // }

    private function getTelegramSendLogKeyByReceiverId(string $startDate, string $endDate, ?int $receiverId = null): array
    {
        $query = TelegramSendLog::query()
            ->whereDate('start', '>=', Helper::dateYMD($startDate))
            ->whereDate('start', '<=', Helper::dateYMD($endDate))
            ->when($receiverId, fn($q) => $q->where('receiver_id', $receiverId));

        // Fetch all logs (latest first)
        $logs = $query->orderByDesc('id')->get();
        // Group by receiver_id
        $grouped = $logs->groupBy('receiver_id');

        // Build result: last log + count + has_sent
        $result = $grouped->mapWithKeys(function ($items, $receiverId) {
            $latest = $items->first(); // because ordered DESC
            return [
                $receiverId => array_merge(
                    $latest->toArray(),
                    [
                        'has_sent'   => true,
                        'sent_count' => $items->count(),
                        'unique'     => $latest->unique,
                    ]
                )
            ];
        });

        return $result->toArray();
    }

    private function applyPackageDateFilter($query, $startDate, $endDate)
    {
        if ($startDate && $endDate) {
            $start = Helper::dateYMD($startDate) . ' 00:00:00';
            $end   = Helper::dateYMD($endDate) . ' 23:59:59';
            $statuses = [
                // 5  => 'arrive_warehouse_datetime',
                // 6  => 'assign_driver_datetime',
                // 10 => 'failed_datetime',
                // 19 => 'failed_datetime',
                9  => 'delivered_datetime',
                // 11 => 'assigned_return_at',
                // 23 => 'returned_datetime'
            ];

            $query->where(function ($q) use ($statuses, $start, $end) {
                foreach ($statuses as $status => $column) {
                    $q->orWhere(function ($q) use ($status, $column, $start, $end) {
                        $q->where('status_id', $status)->whereBetween($column, [$start, $end]);
                    });
                    // Other statuses: do NOT filter by date, but still included
                    $q->orWhere(function ($x) {
                        $x->where('status_id', '!=', 9);
                    });
                }
            });
        }
        return $query;
    }


// public function getMerchantListByDate(Request $req)
// {
//     $startDate  = $req->startDate ? Helper::dateDMY($req->startDate) : null;
//     $endDate    = $req->endDate ? Helper::dateDMY($req->endDate) : null;
//     $search     = $req->search;
//     $sentStatus = $req->query('sent_status');

//     if (!$startDate || !$endDate) {
//         return ApiResponse::ValidateFail('Please select a date range to view this report');
//     }

//     $user = UserService::getAuthUser();
//     $telegramSendLogKeyBy = $this->getTelegramSendLogKeyByReceiverId($startDate, $endDate);

//     $start = Helper::dateYMD($startDate) . ' 00:00:00';
//     $end   = Helper::dateYMD($endDate) . ' 23:59:59';

//     // Aggregate packages per merchant
//     $packageSummary = DB::table('packages')
//         ->select('merchant_id')
//         ->selectRaw("
//             COUNT(*) AS package_count,
//             SUM(CASE WHEN payer = 'sender' THEN delivery_fee + other_fee ELSE 0 END) AS fees,
//             SUM(taxi_fee) AS taxi_fee,
//             SUM(price) AS price_usd,
//             SUM(price_khr) AS price_khr,
//             SUM(driver_cod_usd) AS collected_usd,
//             SUM(driver_cod_khr) AS collected_khr,
//             MIN(status_id) AS min_status,
//             MAX(status_id) AS max_status,
//             COALESCE(
//             STRING_AGG(
//                 CASE WHEN status_id = 9 THEN '9' END,
//                 '-'
//             ),
//             ''
//         ) AS unique_signature


//         ")
//         ->where('is_deleted', 0)
//         ->where('outstanding', 0)
//         ->where(function ($q) use ($start, $end) {
//             $q->where(function ($q2) use ($start, $end) {
//                 $q2->where('status_id', 9)
//                    ->whereBetween('delivered_datetime', [$start, $end])
//                    ->orWhere('status_id', '!=', 9);
//             });
//         })
//         ->groupBy('merchant_id')
//         ->havingRaw('NOT (MIN(status_id) = 5 AND MAX(status_id) = 5)');

//     // Join aggregated packages with users
//     $query = User::query()
//         ->joinSub($packageSummary, 'pkg', function ($join) {
//             $join->on('users.id', '=', 'pkg.merchant_id');
//         })
//         ->where('users.company_id', $user->company_id)
//         ->where('users.account_type', 'merchant')
//         ->where('users.is_deleted', 0)
//         ->select([
//             'users.id',
//             'users.id as merchant_id',
//             'users.username',
//             'users.name_km',
//             'users.phone',
//             'users.code',
//             'pkg.package_count',
//             'pkg.fees',
//             'pkg.taxi_fee',
//             'pkg.price_usd',
//             'pkg.price_khr',
//             'pkg.collected_usd',
//             'pkg.collected_khr',
//         ])
//         ->with([
//             'bank_accounts:id,user_id,bank_number as account_number,bank_name,account_name,currency',
//             'telegramBot:id,user_id,group_name,group_id,bot_id,default_caption,bot_token'
//         ])
//         ->when($search, fn($q) => $q->where(fn($q2) =>
//             $q2->where('users.username','ILIKE',"%{$search}%")
//                ->orWhere('users.phone','ILIKE',"%{$search}%")
//         ))
//         ->orderByDesc('users.id');

//     $data = $query->get()->map(function ($merchant) use ($telegramSendLogKeyBy) {
//         $hasSent = $telegramSendLogKeyBy[$merchant->id] ?? null;
//         $merchant->has_sent = [
//             'has_sent'   => $hasSent && ($hasSent['unique'] === $merchant->unique_signature),
//             'sent_count' => $hasSent['sent_count'] ?? 0,
//             'unique'     => $merchant->unique_signature,
//         ];
//         return $merchant;
//     });

//     if ($sentStatus === 'sent') {
//         $data = $data->filter(fn($m) => $m->has_sent['has_sent'] === true)->values();
//     } elseif ($sentStatus === 'unsent') {
//         $data = $data->filter(fn($m) => $m->has_sent['has_sent'] === false)->values();
//     }

//     return ApiResponse::JsonResult($data);
// }

public function getMerchantListByDate(Request $req)
{
    $startDate  = $req->startDate ? Helper::dateDMY($req->startDate) : null;
    $endDate    = $req->endDate ? Helper::dateDMY($req->endDate) : null;
    $search     = $req->search;
    $sentStatus = $req->query('sent_status');

    if (!$startDate || !$endDate) {
        return ApiResponse::ValidateFail('Please select a date range to view this report');
    }

    $user = UserService::getAuthUser();
    $telegramSendLogKeyBy = $this->getTelegramSendLogKeyByReceiverId($startDate, $endDate);

    $start = Helper::dateYMD($startDate) . ' 00:00:00';
    $end   = Helper::dateYMD($endDate) . ' 23:59:59';

    // Aggregate packages per merchant
    $packageSummary = DB::table('packages')
        ->select('merchant_id')
        ->selectRaw("
            COUNT(*) AS package_count,
            SUM(CASE WHEN payer = 'sender' THEN delivery_fee + other_fee ELSE 0 END) AS fees,
            SUM(taxi_fee) AS taxi_fee,
            SUM(price) AS price_usd,
            SUM(price_khr) AS price_khr,
            SUM(driver_cod_usd) AS collected_usd,
            SUM(driver_cod_khr) AS collected_khr,
            MIN(status_id) AS min_status,
            MAX(status_id) AS max_status
        ")
        ->where('is_deleted', 0)
        ->where('outstanding', 0)
        ->whereBetween('delivered_datetime', [$start, $end])
        ->groupBy('merchant_id')
        ->havingRaw('NOT (MIN(status_id) = 5 AND MAX(status_id) = 5)');

    // Efficient unique_signature calculation
    $uniqueSignatures = DB::table('packages')
        ->select('merchant_id')
        ->selectRaw("
            STRING_AGG(cnt || '-9', '-' ORDER BY min_delivered) AS unique_signature
        ")
        ->fromSub(function($query) use ($start, $end) {
            $query->select('merchant_id', DB::raw('COUNT(*) AS cnt'), DB::raw('MIN(delivered_datetime) AS min_delivered'))
                ->from('packages')
                ->where('status_id', 9)
                ->whereBetween('delivered_datetime', [$start, $end])
                ->where('is_deleted', 0)
                ->where('outstanding', 0)
                ->groupBy('merchant_id');
        }, 'sub')
        ->groupBy('merchant_id');

    // Join aggregated packages and unique_signature with users
    $query = User::query()
        ->joinSub($packageSummary, 'pkg', function ($join) {
            $join->on('users.id', '=', 'pkg.merchant_id');
        })
        ->leftJoinSub($uniqueSignatures, 'sig', function ($join) {
            $join->on('users.id', '=', 'sig.merchant_id');
        })
        ->where('users.company_id', $user->company_id)
        ->where('users.account_type', 'merchant')
        ->where('users.is_deleted', 0)
        ->select([
            'users.id',
            'users.id as merchant_id',
            'users.username',
            'users.name_km',
            'users.phone',
            'users.code',
            'pkg.package_count',
            'pkg.fees',
            'pkg.taxi_fee',
            'pkg.price_usd',
            'pkg.price_khr',
            'pkg.collected_usd',
            'pkg.collected_khr',
            'sig.unique_signature',
        ])
        ->with([
            'bank_accounts:id,user_id,bank_number as account_number,bank_name,account_name,currency',
            'telegramBot:id,user_id,group_name,group_id,bot_id,default_caption,bot_token'
        ])
        ->when($search, fn($q) => $q->where(fn($q2) =>
            $q2->where('users.username','ILIKE',"%{$search}%")
               ->orWhere('users.phone','ILIKE',"%{$search}%")
        ))
        ->orderByDesc('users.id');

    // Map telegram sent status
    $data = $query->get()->map(function ($merchant) use ($telegramSendLogKeyBy) {
        $hasSent = $telegramSendLogKeyBy[$merchant->id] ?? null;
        $merchant->has_sent = [
            'has_sent'   => $hasSent && ($hasSent['unique'] === $merchant->unique_signature),
            'sent_count' => $hasSent['sent_count'] ?? 0,
            'unique'     => $merchant->unique_signature,
        ];
        return $merchant;
    });

    // Filter by sent / unsent if requested
    if ($sentStatus === 'sent') {
        $data = $data->filter(fn($m) => $m->has_sent['has_sent'] === true)->values();
    } elseif ($sentStatus === 'unsent') {
        $data = $data->filter(fn($m) => $m->has_sent['has_sent'] === false)->values();
    }

    return ApiResponse::JsonResult($data);
}







    // public function getMerchantListByDate(Request $req){
    //     $startDate = $req->startDate ? Helper::dateDMY($req->startDate) : null;
    //     $endDate = $req->endDate ? Helper::dateDMY($req->endDate) : null;
    //     $search = $req->search;
    //     $sentStatus = $req->query('sent_status');
    //     if(!$startDate || !$endDate) return ApiResponse::ValidateFail('Please select a date range to view this report');
    //     $user = UserService::getAuthUser();
    //     $telegramSendLogKeyBy = $this->getTelegramSendLogKeyByReceiverId($startDate,$endDate);
    //     $select = [
    //         'users.id',
    //         'users.id as merchant_id',
    //         'users.username',
    //         'users.name_km',
    //         'users.phone',
    //         'users.code',
    //         DB::raw('COUNT(packages.id) as package_count'),
    //         DB::raw("SUM(CASE WHEN packages.payer = 'sender' THEN packages.delivery_fee + packages.other_fee ELSE 0 END) as fees"),
    //         DB::raw('SUM(packages.taxi_fee) as taxi_fee'),
    //         DB::raw('SUM(packages.price) as price_usd'),
    //         DB::raw('SUM(packages.price_khr) as price_khr'),
    //         DB::raw('SUM(packages.driver_cod_usd) as collected_usd'),
    //         DB::raw('SUM(packages.driver_cod_khr) as collected_khr'),
    //     ];

    //     $query = User::query()
    //     ->join('packages', 'users.id', '=', 'packages.merchant_id')
    //     ->where('packages.is_deleted', 0)
    //     ->where('packages.outstanding', 0)
    //     ->where('users.company_id', $user->company_id)
    //     ->where('users.account_type', 'merchant')
    //     ->where('users.is_deleted', 0)
    //     ->select($select)
    //     ->with([
    //         'bank_accounts' => function ($q) {
    //             $q->select('id', 'user_id', 'bank_number as account_number', 'bank_name','account_name','currency');
    //         },
    //         'telegramBot:id,user_id,group_name,group_id,bot_id,default_caption,bot_token',
    //         // 'merchantPackages:id,merchant_id,status_id,payer,taxi_fee,delivery_fee,other_fee,driver_cod_usd,driver_cod_khr,price,price_khr'
    //         'merchantPackages' => function ($q) use ($startDate, $endDate) {
    //             $q->where('is_deleted', 0)
    //             ->where('outstanding', 0);
    //             $this->applyPackageDateFilter($q, $startDate, $endDate);
    //         }
    //     ])
    //     ->groupBy('users.id', 'users.username', 'users.name_km', 'users.phone','users.code')
    //     ->orderByDesc('users.id');


    //     if($search){
    //         $query->where(function ($q) use($search){
    //             $q->where('users.username','ILIKE',"%{$search}%")
    //             ->orWhere('users.phone','ILIKE',"%{$search}%");
    //         });
    //     }
    //     // if($startDate && $endDate){
    //     //     $startDatetime = Helper::dateYMD($startDate).' 00:00:00';
    //     //     $endDatetime = Helper::dateYMD($endDate).' 23:59:59';
    //     //     $query->where(function ($q) use ($startDatetime, $endDatetime) {
    //     //         $q->where(function ($q) use ($startDatetime, $endDatetime) {
    //     //             $q->where(function ($q) use ($startDatetime, $endDatetime) {
    //     //                 $q->where('status_id', 5)
    //     //                 ->whereBetween('arrive_warehouse_datetime', [$startDatetime, $endDatetime]);
    //     //             })->orWhere(function ($q) use ($startDatetime, $endDatetime) {
    //     //                 $q->where('status_id', 6)
    //     //                 ->whereBetween('assign_driver_datetime', [$startDatetime, $endDatetime]);
    //     //             })->orWhere(function ($q) use ($startDatetime, $endDatetime) {
    //     //                 $q->where('status_id', 10)
    //     //                 ->whereBetween('failed_datetime', [$startDatetime, $endDatetime]);
    //     //             })->orWhere(function ($q) use ($startDatetime, $endDatetime) {
    //     //                 $q->where('status_id', 19)
    //     //                 ->whereBetween('failed_datetime', [$startDatetime, $endDatetime]);
    //     //             })->orWhere(function ($q) use ($startDatetime, $endDatetime) {
    //     //                 $q->where('status_id', 9)
    //     //                 ->whereBetween('delivered_datetime', [$startDatetime, $endDatetime]);
    //     //             })->orWhere(function ($q) use ($startDatetime, $endDatetime) {
    //     //                 $q->where('status_id', 11)
    //     //                 ->whereBetween('assigned_return_at', [$startDatetime, $endDatetime]);
    //     //             })
    //     //             ->orWhere(function ($q) use ($startDatetime, $endDatetime) {
    //     //                 $q->where('status_id', 23)
    //     //                 ->whereBetween('returned_datetime', [$startDatetime, $endDatetime]);
    //     //             });
    //     //         });
    //     //     });
    //     // }
    //     $this->applyPackageDateFilter($query, $startDate, $endDate);
        
    //     $callback = function ($q) use ($telegramSendLogKeyBy) {
    //         $hasSent = $telegramSendLogKeyBy[$q->id] ?? null;
    //         $currentUnique = null;

    //         // Use the same status order as in getMerchantSummaryReportV2packageOrder
    //         $statusOrder = [9, 10, 5, 6, 11, 23, 19];

    //         // Preload grouped packages
    //         $grouped = $q->merchantPackages->groupBy('status_id');

    //         // Build currentUnique following the fixed order
    //         foreach ($statusOrder as $statusId) {
    //             if ($grouped->has($statusId)) {
    //                 $count = $grouped[$statusId]->count();
    //                 $currentUnique .= "{$count}-{$statusId}";
    //             }
    //         }

    //         // Handle merchants with empty packages
    //         if (empty($currentUnique)) {
    //             $currentUnique = '0';
    //         }

    //         // Compare with DB record
    //         if ($hasSent) {
    //             $dbUnique = $hasSent['unique'] ?? null;
    //             $q->has_sent = [
    //                 'has_sent'   => ($dbUnique === $currentUnique),
    //                 'sent_count' => $hasSent['sent_count'] ?? 0,
    //                 'unique'     => $currentUnique,
    //             ];
    //         } else {
    //             $q->has_sent = [
    //                 'has_sent'   => false,
    //                 'sent_count' => 0,
    //                 'unique'     => $currentUnique,
    //             ];
    //         }
    //     };

    //     $data = $query->get()->each($callback);
    //     if ($sentStatus === 'sent') {
    //         $data = $data->filter(fn($item) => $item->has_sent['has_sent'] === true)->values();
    //     } 
    //     elseif ($sentStatus === 'unsent') {
    //         $data = $data->filter(fn($item) => $item->has_sent['has_sent'] === false)->values();
    //     }
    //     return ApiResponse::JsonResult($data);
    //     // return ApiResponse::PaginationV1($query,$req, 'Get Merchant List By Date',[],1000,$callback,$select);
    // }

    //
    //     $user = UserService::getAuthUser();
        // $startDate = $req->startDate ? Helper::dateDMY($req->startDate) : null;
        // $endDate = $req->endDate ? Helper::dateDMY($req->endDate) : null;
        // if(!$startDate || !$endDate) return ApiResponse::ValidateFail('Please select a date range to view this report');
    //     $query = User::from('users')->join('packages', 'users.id', '=', 'packages.merchant_id')
    //     ->where('packages.is_deleted',0)
    //     ->where('packages.outstanding',0)
    //     ->where('users.company_id', $user->company_id)
    //     ->where('users.account_type', 'merchant')
    //     ->where('users.is_deleted',0)
    //     // ->selectRaw('DISTINCT users.id, users.username, users.name_km, users.phone')
    //     ->distinct()
    //     ->orderByDesc('users.id');
    //     if($startDate && $endDate){
    //         $startDatetime = Helper::dateYMD($startDate).' 00:00:00';
    //         $endDatetime = Helper::dateYMD($endDate).' 23:59:59';
    //         $query->where(function ($q) use ($startDatetime, $endDatetime) {
    //             $q->where(function ($q) use ($startDatetime, $endDatetime) {
    //                 $q->where(function ($q) use ($startDatetime, $endDatetime) {
    //                     $q->where('status_id', 5)
    //                       ->whereBetween('arrive_warehouse_datetime', [$startDatetime, $endDatetime]);
    //                 })->orWhere(function ($q) use ($startDatetime, $endDatetime) {
    //                     $q->where('status_id', 6)
    //                       ->whereBetween('assign_driver_datetime', [$startDatetime, $endDatetime]);
    //                 })->orWhere(function ($q) use ($startDatetime, $endDatetime) {
    //                     $q->where('status_id', 10)
    //                       ->whereBetween('failed_datetime', [$startDatetime, $endDatetime]);
    //                 })->orWhere(function ($q) use ($startDatetime, $endDatetime) {
    //                     $q->where('status_id', 19)
    //                       ->whereBetween('failed_datetime', [$startDatetime, $endDatetime]);
    //                 })->orWhere(function ($q) use ($startDatetime, $endDatetime) {
    //                     $q->where('status_id', 9)
    //                       ->whereBetween('delivered_datetime', [$startDatetime, $endDatetime]);
    //                 })->orWhere(function ($q) use ($startDatetime, $endDatetime) {
    //                     $q->where('status_id', 11)
    //                       ->whereBetween('returned_datetime', [$startDatetime, $endDatetime]);
    //                 });
    //             });

    //             // $q->whereRaw(
    //             //     '(packages.status_id = 5 AND packages.arrive_warehouse_datetime BETWEEN ? AND ?)
    //             //     OR (packages.status_id = 6 AND packages.assign_driver_datetime BETWEEN ? AND ?)
    //             //     OR (packages.status_id = 10 AND packages.failed_datetime BETWEEN ? AND ?)
    //             //     OR (packages.status_id = 19 AND packages.failed_datetime BETWEEN ? AND ?)
    //             //     OR (packages.status_id = 9 AND packages.delivered_datetime BETWEEN ? AND ?)
    //             //     OR (packages.status_id = 11 AND packages.returned_datetime BETWEEN ? AND ?)',
    //             //     [$startDatetime, $endDatetime, $startDatetime, $endDatetime, $startDatetime, $endDatetime, $startDatetime, $endDatetime, $startDatetime, $endDatetime, $startDatetime, $endDatetime]
    //             // );
    //         });
    //     }

    //     $select = ['users.id', 'users.username', 'users.name_km', 'users.phone',"users.*"];
    //     $callback = function ($q){
    //         return $q;
    //     };

    //     return ApiResponse::PaginationV1($query,$req, 'Get Merchant List By Date',[],1000,$callback,$select);
    // }

    public function updateMerchant(Request $req){
        $user = UserService::getAuthUser();
        $id = $req->id;
        $updateMerchant = UserService::createOrUpdateUser($req,$this->userClass,$user,$id);
        return ApiResponse::flex($updateMerchant);
    }


    public function createMerchantAccount(Request $req){
        $user = UserService::getAuthUser();
        $merchantId = $req->id;
        $createMerchant = UserService::createLoginAccount($req,$merchantId,'merchant',$user);
        return ApiResponse::flex($createMerchant);
    }

    public function setMerchantPriceList(Request $req){
        $user = UserService::getAuthUser();
        $id = $req->id;
        $priceListId = $req->price_list_id;
        $zoneId = $req->zone_id;
        if(!$priceListId) return ApiResponse::ValidateFail(__('messages.info',[
            'info' => 'Please choose a price list'
        ]));
        $merchantPriceList = MerchantPriceList::where('merchant_id',$id)->first();
        $insertOrUpdate = [
            'price_list_id' => $priceListId, // price list name
            'update_uid' => $user->id,
            'branch_id' => $user->branch_id,
            'company_id' => $user->company_id
        ];

        if($zoneId) {
            $zone = Zone::where('is_deleted',0)->find($zoneId);
            if(!$zone) return ApiResponse::NotFound(__('messages.not_found',[
                'info' => 'Zone'
            ]));
            $insertOrUpdate['zone_code'] = $zone->zone_code;
            $insertOrUpdate['zone_id'] = $zoneId;
        }
        if($merchantPriceList){
            $merchantPriceList->update($insertOrUpdate);
        }else{
            $insertOrUpdate['merchant_id'] = $id;
            $insertOrUpdate['create_uid'] = $user->id;
            MerchantPriceList::create($insertOrUpdate);
        }

        return ApiResponse::JsonResult(null,__('messages.updated'));
    }

     public function setLockMerchant(Request $req){
        $user = UserService::getAuthUser();
        return ApiResponse::flex(UserService::setLockUser($user,$req->id,'merchant'));
    }

    public function setPassword(Request $req){
        $user = UserService::getAuthUser();
        return ApiResponse::flex(UserService::setNewPassword($req,$req->id,'merchant',$user));
    }

    public function deleteMerchant(Request $req){
        $user = UserService::getAuthUser();
        return ApiResponse::flex(UserService::deleteUser($req->id,'merchant',$user));
    }

    public function getBankAccountsById(Request $req){
        $id = $req->id;
        $userBanks = UserBank::where('user_id',$id)
        ->select(['id','bank_number as account_number','account_name','currency','is_whitelist'])
        ->get();
        return ApiResponse::JsonResult($userBanks);
    }

    public function whitelistAccount(Request $req){
        $user = UserService::getAuthUser();
        $bankService = new BankServiceImpl();
        $merchantId = $req->merchantId;
        $accountId = $req->accountId;
        $userBank = UserBank::where('user_id',$merchantId)->orderByDesc('id')->find($accountId);
        if(!$userBank){
            return ApiResponse::NotFound('Bank not found');
        }
        $accountNumber = $userBank->bank_number;
        $wlAcc = $bankService->whitelistAccountToBank($accountNumber);
        if($wlAcc->error){
            // Log::info(json_encode($wlAcc));
            return ApiResponse::flex($wlAcc);
        }

        $userBank->update([
            'currency' => $wlAcc->data['data']['currency'] ?? $userBank->currency,
            'is_whitelist' => true,
            'whitelist_by' => $user->id
        ]);
        return ApiResponse::JsonResult(null,__('messages.saved'));
    }

    public function getTelegramBotByUserId(Request $req){
        return ApiResponse::flex(UserService::getUserTelegramBot($req->id,'merchant'));
    }

    public function setMerchantTelegramBot(Request $req){
        return ApiResponse::flex(UserService::setUserTelegramBot($req->botId,$req->id,$req->all(),'merchant'));
    }
}
