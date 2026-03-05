<?php

namespace App\Services;
use App\Models\PrivacyStatement;
use App\Models\TermCondition;
// use Barryvdh\DomPDF\PDF;
use DataResponse;
use Helper;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Log;
class AppSetting
{
    // Your service methods go here
    // protected static operation

    protected $notifTopics = [
        'admin' => [
            'prefix'
        ],
        'driver' => [],
        'merchant' => []
    ];

    protected static $userAppIds;
    protected static $baseUrl = 'api/admin/v1/{lang}';

    public function __construct(){
    }

    public static function initialize() {
        // Initialize static property only once
        if (is_null(self::$userAppIds)) {
            self::$userAppIds = [
                'admin' => config('app.admin_app_id'),
                'merchant' => config('app.merchant_app_id'),
                'driver' => config('app.driver_app_id')
            ];
        }
    }


    private static function privacyTermConditionValidation(Request $req){
        return validator($req->all(),[
            'channel' => 'required|in:merchant,driver',
            'text' => 'nullable|string'
        ]);
    }

    public static function getUserAppId($userClass){
        self::initialize();
        return self::$userAppIds[$userClass];
    }

    public static function savePrivacyTermCondition(Request $req,$type,$user){
        $validate = self::privacyTermConditionValidation($req);
        if($validate->fails()) return DataResponse::ValidateFail($validate->errors()->first());
        $inputs = $validate->validated();
        $channel = $inputs['channel'];
        $inputs['update_uid'] = $user->id;
        $inputs['company_id'] = $user->company_id;
        $inputs['branch_id'] = $user->branch_id;
        $modelName = null;
        $isUpdate = 1;
        if($type == 'privacy_statement'){
            $modelName = 'Privacy Statement';
            $model = PrivacyStatement::where('channel',$channel)->where('company_id',$user->company_id)->where('is_deleted',0)->first();
            if(!$model) {
                $isUpdate = 0;
                $model = new PrivacyStatement();
            }
        }else if('term_condition'){
            $modelName = 'Terms and conditions';
            $model = TermCondition::where('channel',$channel)->where('company_id',$user->company_id)->where('is_deleted',0)->first();
            if(!$model) {
                $isUpdate = 0;
                $model = new TermCondition();
            }
        }

        if($isUpdate){
            $model->update($inputs);
            return DataResponse::JsonResult(null,false,__('messages.updated',[
                'info' => $modelName
            ]));
        }else{
            $inputs['create_uid'] = $user->id;
            $model->create($inputs);
            return DataResponse::JsonResult(null,false,__('messages.created',[
                'info' => $modelName
            ]));
        }
    }

    public static function redirectBasedOnDevice(Request $request)
    {
        $userAgent = $request->header('User-Agent');
        // Check if the device is an iPhone or iPad
        if (strpos($userAgent, 'iPhone') !== false || strpos($userAgent, 'iPad') !== false) {
            // Redirect to the App Store (iOS)
            // return Redirect::to('');
        } else {
            // Redirect to the Play Store (Android or other devices)
            // return Redirect::to('');
        }
    }

    public static function redirectStoreDriverApp(Request $req){
        $userAgent = $req->header('User-Agent');
        // Check if the device is an iPhone or iPad
        if (strpos($userAgent, 'iPhone') !== false || strpos($userAgent, 'iPad') !== false) {
            // Redirect to the App Store (iOS)
            return Redirect::to('https://www.gtechcambodia.com');
        } else {
            // Redirect to the Play Store (Android or other devices)
            return Redirect::to('https://www.gtechcambodia.com');
        }
    }



    public static function redirectCompanyWebsite(){
        return Redirect::to('https://www.gtechcambodia.com');
    }

    public static function getPrivacyTermCondition($channel,$type,$user){
        $modelName = null;
        $model = null;
        if($type == 'privacy_statement'){
            $modelName = 'Privacy Statement';
            $model = PrivacyStatement::where('channel',$channel)->where('company_id',$user->company_id)->where('is_deleted',0)
            ->selectRaw('id,channel,text')
            ->first();
        }else if('term_condition'){
            $modelName = 'Terms and conditions';
            $model = TermCondition::where('channel',$channel)->where('company_id',$user->company_id)->where('is_deleted',0)
            ->selectRaw('id,channel,text')
            ->first();
        }
        if(!$model) return DataResponse::JsonResult(null);

        return DataResponse::JsonResult($model,false,__('messages.get one',[
            'info' => $modelName
        ]));
    }

    public static function sendSms($sender, $to, $content) {
        $privateKey = config('services.plasgate.private_key') ?? '';
        $secret = config('services.plasgate.secret') ?? '';

        $url = config('services.plasgate.base_url').'/rest/send?private_key=' . $privateKey;

        $headers = [
            'X-Secret' => $secret,
            'Content-Type' => 'application/json'
        ];

        $phone_number = Helper::formatPhoneNumber($to);
        // return $phone_number;
        $data = [
            'sender' => $sender,
            'to' => $phone_number,
            'content' => $content
        ];

        $response = Http::withHeaders($headers)->post($url, $data);
        if ($response->successful()) {
            if ($response->status() === 402) {
                // Handle 402 Payment Required
                Log::info('Payment Required');
                return DataResponse::JsonResult(null,false,'Try again later', [],402);
            }
            if ($response->status() === 403) {
                return DataResponse::JsonResult(null,false,'Failed', [],403);
            }
            return DataResponse::JsonResult($response);
        }
        return DataResponse::Error($response->json()['message']);
    }


    // public static function generatePDF()
    // {
    //     // Example data
    //     $data = [
    //         'title' => 'Dynamic PDF Example',
    //         'date' => now()->toDateTimeString(),
    //         'content' => 'This PDF was generated dynamically when requested.',
    //     ];

    //     // Load the Blade view and pass data
    //     $pdf = PDF::loadView('pdf.package_history', $data);

    //     // Return the PDF file for viewing (inline)
    //     return response($pdf->output(), 200, [
    //         'Content-Type' => 'application/pdf',
    //         'Content-Disposition' => 'inline; filename="dynamic-pdf.pdf"',
    //     ]);
    // }


    private static function orderPermissionCode(){
        return [
            'GET' => [
                self::$baseUrl.'/order/{order_id}/package/print' => 335
            ],
            'POST' => [
                self::$baseUrl.'/order' => 200,
                self::$baseUrl.'/order/{order_id}/package' => 206,
                self::$baseUrl.'/order/{order_id}/link/image' => 206
            ],
            'PUT' => [
                '{order_id}/driver/{driver_id}' => 201,
                self::$baseUrl.'/order/{id}/arrive' => 202,
                self::$baseUrl.'/order/{id}/status' => 203,
                self::$baseUrl.'/order/{order_id}/driver/{driver_id}' => 204,
                self::$baseUrl.'/order/{order_id}/package/{id}' => 207
            ],
            'DELETE' => [
                self::$baseUrl.'/order/{id}' => 205,
                self::$baseUrl.'/order/{order_id}/package/{id}' => 208,
                self::$baseUrl.'/order/{order_id}/image/{imageId}' => 208
            ]
        ];
    }

    private static function packagePermissionCode(){
        return [
            'GET' => [
                self::$baseUrl.'/package/{id}/print' => 214,
            ],
            'POST' => [
                self::$baseUrl.'/package/list/print' => 214,
            ],
            'PUT' => [
                self::$baseUrl.'/package/{id}' => 215,
                self::$baseUrl.'/package/{id}/driver/{driver_id}' => 210, //assign driver
                self::$baseUrl.'/package/{id}/return' => 211,
                self::$baseUrl.'/package/{id}/changeMerchant' => 211
            ],
            'DELETE' => [
                self::$baseUrl.'/package/{id}' => 212
            ]
        ];
    }

    private static function fleetPermissionCode(){
        return [
            'POST' => [
                self::$baseUrl.'/trip' => 216,
                self::$baseUrl.'/trip/{trip_id}/takeOut/{package_id}' => 222,
            ],
            'PUT' => [
                self::$baseUrl.'/trip/{trip_id}/finish' => 217,
                self::$baseUrl.'/trip/{trip_id}/package/status' => 221,
            ],
            // 'DELETE' => [
            //     self::$baseUrl.'/package/{id}' => 213
            // ]
        ];
    }
    private static function completePackagePermissionCode(){
        return [
            'PUT' => [
                self::$baseUrl.'/finished/package/{id}' => 224,
            ],
            'GET' => [
                self::$baseUrl.'/finished/package/list/print' => 224,
            ],
            // 'DELETE' => [
            //     self::$baseUrl.'/package/{id}' => 213
            // ]
        ];
    }

    // continue here
    private static function bankPermissionCode(){
        return [
            'POST' => [
                self::$baseUrl.'/bank' => 299,
            ],
            'PUT' => [
                self::$baseUrl.'/bank/{id}' => 300,
            ],
            'DELETE' => [
                self::$baseUrl.'/bank/{id}' => 301
            ]
        ];
    }

    private static function zonePermissionCode(){
        return [
            'POST' => [
                self::$baseUrl.'/zone' => 299,
            ],
            'PUT' => [
                self::$baseUrl.'/zone/{id}' => 300,
                self::$baseUrl.'/zone/assign/driver' => 337,
            ],
            'DELETE' => [
                self::$baseUrl.'/zone/{id}' => 301
            ]
        ];
    }

    private static function priceListPermissionCode(){
        return [
            'POST' => [
                self::$baseUrl.'/priceList/{id?}' => 298,
            ],
            'PUT' => [
                self::$baseUrl.'/priceList/assign' => 296
            ],
            'DELETE' => [
                self::$baseUrl.'/priceList/assign' => 297
            ]
        ];
    }


    private static function simplePermissionCode($prefix,$postCode=null,$putCode=null,$dCode=null){
        return [
            'POST' => [
                self::$baseUrl."/driver/commission/disbursement" => $postCode,
                self::$baseUrl."/socialMedia" => $postCode,
                self::$baseUrl."/{$prefix}" => $postCode,
                self::$baseUrl."/{$prefix}/{id}" => $postCode,
            ],
            'PUT' => [
                self::$baseUrl."/{$prefix}" => $putCode,
                self::$baseUrl."/{$prefix}/{id}" => $putCode,
            ],
            'DELETE' => [
                self::$baseUrl."/{$prefix}/{id}" => $dCode
            ]
        ];
    }

    private static function trasactionPermissionCode(){
        return [
            'POST' => [
                self::$baseUrl."/driver/transaction/delivery/payment" => 228,
                self::$baseUrl."/merchant/transaction/delivery/payment" => 237,
                self::$baseUrl."/merchant/transaction/delivery/payment-bulk" => 228,
                self::$baseUrl."/merchant/transaction/payment/approve-settle-batch" => 237,
            ],
            'PUT' => [
                // self::$baseUrl."/driver/transaction/delivery/package/{id}" => 0,
                self::$baseUrl."/driver/transaction/payment" => 230,
                self::$baseUrl."/driver/transaction/settle/payment" => 232,
                // self::$baseUrl."/merchant/transaction/delivery/package/{id}" => 0,
                // self::$baseUrl."/merchant/transaction/payment" => 00,
            ],
            'DELETE' => [
                self::$baseUrl."/driver/transaction/payment/{id}" => 229,
                self::$baseUrl."/driver/transaction/settle/payment/{id}" => 233,
                self::$baseUrl."/merchant/transaction/settle/payment/{id}" => 231,
                self::$baseUrl."/merchant/transaction/payment/{id}" => 238
            ]
        ];
    }

    private static function driverPermissionCode(){
        return [
            'POST' => [
                self::$baseUrl."/driver" => 239,
                self::$baseUrl."/driver/{id}/setLock" => 243,
                self::$baseUrl."/driver/{id}/setPassword" => 244,
                self::$baseUrl."/driver/{id}/account" => 242,
                self::$baseUrl."/driver/{id}/commission" => 241,
                self::$baseUrl."/driver/{id}/setLock" => 234,
            ],
            'PUT' => [
                self::$baseUrl."/driver/{id}" => 240,
                self::$baseUrl."/driver/{id}/commission" => 241,
            ],
            'DELETE' => [
                self::$baseUrl."/driver/{id}" => 245,
            ]
        ];
    }

    
    private static function merchantPermissionCode(){
        return [
            'POST' => [
                self::$baseUrl."/merchant" => 246,
                self::$baseUrl."/merchant/{id}/setLock" => 249,
                self::$baseUrl."/merchant/{id}/setPassword" => 250,
                self::$baseUrl."/merchant/{id}/account" => 248,
                self::$baseUrl."/merchant/{id}/priceList" => 303,
            ],
            'PUT' => [
                self::$baseUrl."/merchant/{id}" => 247,
                self::$baseUrl."/merchant/{id}/priceList" => 303,
            ],
            'DELETE' => [
                self::$baseUrl."/merchant/{id}" => 251,
            ]
        ];
    }

    public static function getCodeByURI($uri, $method, $prefix) {
        static $permissionMap = [
            'order'            => 'orderPermissionCode',
            'package'          => 'packagePermissionCode',
            'trip'             => 'fleetPermissionCode',
            'finished'         => 'completePackagePermissionCode',
            'delivery'         => 'trasactionPermissionCode',
            'payment'          => 'trasactionPermissionCode',
            'settle'           => 'trasactionPermissionCode',
            'commission'       => ['driver/commission/disbursement', 234, 234, 302],
            'driver'           => 'driverPermissionCode',
            'merchant'         => 'merchantPermissionCode',
            'company'           => ['company', null, 252],
            'brandImage'       => ['brandImage', 256, null, 257],
            'promotion'        => ['promotion', 258, 259, 260],
            'socialMedia'      => ['socialMedia', 261, 304, 262],
            'privacyStatement' => ['privacyStatement', 263, 263, null],
            'termCondition'    => ['termCondition', null, 264, null],
            'xrate'            => ['xrate', 265, 266, 267],
            'productType'      => ['productType', 268, 269, 270],
            'remark'           => ['remark', 271, 272, 273],
            'country'          => ['location/country', 274, 275, 276],
            'city'             => ['location/city', 278, 279, 280],
            'district'         => ['location/district', 282, 283, 284],
            'commune'          => ['location/commune', 286, 287, 288],
            'zone'             => 'zonePermissionCode',//['zone', 289, 290, 291],
            'name'             => ['priceList/name', 292, 293, 294],
            'priceList'        => 'priceListPermissionCode',
            'bank'             => ['bank', 299, 300, 229],
            'report'           => 'companyReportPermissionCode',
            'exports'           => 'companyReportPermissionCode',
            'user'             => 'userPermissionCode',

        ];

        if (!isset($permissionMap[$prefix])) {
            return null;
        }

        // Retrieve permission codes
        $allowed = is_array($permissionMap[$prefix])
                    ? self::simplePermissionCode(...$permissionMap[$prefix])
                    : self::{$permissionMap[$prefix]}();

        if (!isset($allowed[$method])) {
            return null;
        }

        foreach ($allowed[$method] as $route => $number) {
            if (self::matchURI($uri, $route)) {
                return $number;
            }
        }

        return null;
    }

    static function getTelegramLink($userType,$receiverPhone,$notUserTypephone){
        if($userType == 'merchant') {
            $userType = 'driver';
        }else $userType = 'driver';
        $userTypeTelegramLnk = Helper::generateTelegramLink($notUserTypephone);
        $receiverTelegramLnk = Helper::generateTelegramLink($receiverPhone);
        return [
            $userType => $userTypeTelegramLnk['url'],
            $userType.'_deep_link' => $userTypeTelegramLnk['deep_link'],
            'receiver' => $receiverTelegramLnk['url'],
            'receiver_deep_link' => $receiverTelegramLnk['deep_link']
        ];
    }

    private static function matchURI($uri, $route)
    {
        // Replace dynamic parameters like {lang} with a regex pattern
        $pattern = preg_replace('/\{[^\/]+\}/', '[^\/]+', $route);
        $pattern = "#^" . $pattern . "$#";

        return preg_match($pattern, $uri);
    }

    public static function protectedRoutes():array{
        return [
            self::$baseUrl.'/package/{id}/print',
            self::$baseUrl.'/package/list/print',
            self::$baseUrl.'/order/{order_id}/package/print',
            self::$baseUrl.'/report/exports/daily-packages',
            self::$baseUrl.'/management/user/{id}/permission',
            self::$baseUrl.'/management/user/{id}/module',
        ];
    }

    public static function companyReportPermissionCode(){
        return [
            'POST' => [
                self::$baseUrl.'/report/exports/daily-packages' => 308,
            ]
        ];
    }


    public static function userPermissionCode(){
        return [
            'POST' => [
                self::$baseUrl.'/management/user' => 100,
                self::$baseUrl.'/management/user/{id}/setPassword' => 110,
            ],
            'PUT' => [
                self::$baseUrl.'/management/user/{id}' => 111,
                self::$baseUrl.'/management/user/{id}/setLoginName' => 109,
                self::$baseUrl.'/management/user/{id}/setLock' => 101,
                self::$baseUrl.'/management/user/{id}/permission/{permission_id}/assign' => 105,
                self::$baseUrl.'/management/user/{id}/module/{module_id}/assign' => 107,
            ],
            'GET' => [
                self::$baseUrl.'/management/user/{id}/permission' => 103,
                self::$baseUrl.'/management/user/{id}/module' => 104,
            ],
            'DELETE' => [
                self::$baseUrl.'/management/user/{id}' => 112,
                self::$baseUrl.'/management/user/{id}/permission/{permission_id}/remove' => 106,
                self::$baseUrl.'/management/user/{id}/module/{module_id}/remove' => 108,
            ]
        ];
    }

}
