<?php

namespace App\Services;
use App\Models\PrivacyStatement;
use App\Models\TermCondition;
// use Barryvdh\DomPDF\PDF;
use DataResponse;
use Helper;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Request;
use Log;
use Redirect;
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
            return Redirect::to('https://apps.apple.com/kh/app/js-express/id6739161811');
        } else {
            // Redirect to the Play Store (Android or other devices)
            return Redirect::to('https://play.google.com/store/apps/details?id=com.gtech.jsexpressmerchant');
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

    public static function sendSms($sender='SMS Test', $to, $content) {
        $privateKey = env('PLASGATE_PRIVATE_KEY') ?? '';
        $secret = env('PLASGATE_SECRET') ?? '';

        $url = 'https://cloudapi.plasgate.com/rest/send?private_key=' . $privateKey;

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
                return DataResponse::JsonResult(null,false,'Payment required', [],402);
            }
            if ($response->status() === 403) {
                // Handle 402 Payment Required
                return DataResponse::JsonResult(null,false,'Failed', [],402);
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
                self::$baseUrl.'/order/{order_id}/package' => 206
            ],
            'PUT' => [
                '{order_id}/driver/{driver_id}' => 201,
                self::$baseUrl.'/order/{id}/arrive' => 202,
                self::$baseUrl.'/order/{id}/status' => 203,
                self::$baseUrl.'/order/{order_id}/driver/{driver_id}' => 204,
                self::$baseUrl.'/order/{order_id}/package/{id}' => 207
            ],
            'DELETE' => [
                self::$baseUrl.'/order/{order_id}/package/{id}' => 208
            ]
        ];
    }

    private static function packagePermissionCode(){
        return [
            'GET' => [
                self::$baseUrl.'/package/{id}/print' => 214,
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
                "api/admin/v1/{lang}/$prefix" => $postCode,
            ],
            'PUT' => [
                "api/admin/v1/{lang}/$prefix" => $putCode,
                "api/admin/v1/{lang}/$prefix/{id}" => $putCode,
            ],
            'DELETE' => [
                "api/admin/v1/{lang}/$prefix/{id}" => $dCode
            ]
        ];
    }

    private static function trasactionPermissionCode(){
        return [
            'POST' => [
                "api/admin/v1/{lang}/driver/transaction/delivery/payment" => 228,
                "api/admin/v1/{lang}/merchant/transaction/delivery/payment" => 237,
            ],
            'PUT' => [
                // "api/admin/v1/{lang}/driver/transaction/delivery/package/{id}" => 0,
                "api/admin/v1/{lang}/driver/transaction/payment" => 230,
                "api/admin/v1/{lang}/driver/transaction/settle/payment" => 232,
                // "api/admin/v1/{lang}/merchant/transaction/delivery/package/{id}" => 0,
                // "api/admin/v1/{lang}/merchant/transaction/payment" => 00,
            ],
            'DELETE' => [
                "api/admin/v1/{lang}/driver/transaction/payment/{id}" => 229,
                "api/admin/v1/{lang}/merchant/transaction/settle/payment/{id}" => 231,
                "api/admin/v1/{lang}/merchant/transaction/payment/{id}" => 238
            ]
        ];
    }

    private static function driverPermissionCode(){
        return [
            'POST' => [
                "api/admin/v1/{lang}/driver" => 239,
                "api/admin/v1/{lang}/{id}/setLock" => 243,
                "api/admin/v1/{lang}/{id}/setPassword" => 244,
                "api/admin/v1/{lang}/{id}/account" => 242,
            ],
            'PUT' => [
                "api/admin/v1/{lang}/driver/{id}" => 240,
                "api/admin/v1/{lang}/driver/{id}/commission" => 241,
            ],
            'DELETE' => [
                "api/admin/v1/{lang}/driver/{id}" => 245,
            ]
        ];
    }
    private static function merchantPermissionCode(){
        return [
            'POST' => [
                "api/admin/v1/{lang}/merchant" => 246,
                "api/admin/v1/{lang}/{id}/setLock" => 249,
                "api/admin/v1/{lang}/{id}/setPassword" => 250,
                "api/admin/v1/{lang}/{id}/account" => 248,
            ],
            'PUT' => [
                "api/admin/v1/{lang}/merchant/{id}" => 247,
                "api/admin/v1/{lang}/merchant/{id}/priceList" => 303,
            ],
            'DELETE' => [
                "api/admin/v1/{lang}/merchant/{id}" => 251,
            ]
        ];
    }


    public static function getCodeByURI($uri,$method,$prefix){
        $allowed = [];
        switch($prefix){
            case 'order':
                $allowed = self::orderPermissionCode();
                break;
            case 'package':
                $allowed = self::packagePermissionCode();
                break;
            case 'trip':
                $allowed = self::fleetPermissionCode();
                break;
            case 'finished':
                $allowed = self::completePackagePermissionCode();
                break;
            case 'delivery': //* transaction
                $allowed = self::trasactionPermissionCode();
                break;
            case 'payment': //* transaction
                $allowed = self::trasactionPermissionCode();
                break;
            case 'settle': //* transaction
                $allowed = self::trasactionPermissionCode();
                break;
            case 'commission': //* transaction
                $allowed = self::simplePermissionCode('driver/commission/disbursement',null,234,302);
                break;
            case 'driver': //* Management
                $allowed = self::driverPermissionCode();
                break;
            case 'merchant': //* Management
                $allowed = self::merchantPermissionCode();
                break;
            case 'comany': //* Management
                $allowed = self::simplePermissionCode('company',null,252);
                break;
            case 'brandImage':
                $allowed = self::simplePermissionCode('brandImage',256,null,257);
                break;
            case 'promotion':
                $allowed = self::simplePermissionCode('promotion',258,259,260);
                break;
            case 'socialMedia':
                $allowed = self::simplePermissionCode('socialMedia',261,null,262);
                break;
            case 'privacyStatement':
                $allowed = self::simplePermissionCode('privacyStatement',null,263,null);
                break;
            case 'termCondition':
                $allowed = self::simplePermissionCode('termCondition',null,264,null);
                break;
            case 'xrate':
                $allowed = self::simplePermissionCode('xrate',265,266,267);
                break;
            case 'productType':
                $allowed = self::simplePermissionCode('productType',268,269,270);
                break;
            case 'remark':
                $allowed = self::simplePermissionCode('remark',289,290,291);
                break;
            case 'country':
                $allowed = self::simplePermissionCode('location/country',274,275,276);
                break;
            case 'city':
                $allowed = self::simplePermissionCode('location/city',278,279,280);
                break;
            case 'district':
                $allowed = self::simplePermissionCode('location/district',282,283,284);
                break;
            case 'commune':
                $allowed = self::simplePermissionCode('location/commune',286,287,288);
                break;
            case 'zone':
                $allowed = self::simplePermissionCode('zone',289,290,291);
                break;

            case 'name': //** price list name */
                $allowed = self::simplePermissionCode('priceList/name',292,293,294);
                break;
            case 'priceList': //** price list name */
                $allowed = self::priceListPermissionCode();
                break;
            case 'bank':
                $allowed = self::simplePermissionCode('bank',null,300,229);
                break;
        }

        // Check if the method exists in the allowed routes
        if (!isset($allowed[$method])) {
            return null; // Method not allowed
        }

        foreach ($allowed[$method] as $route => $number) {
            if (self::matchURI($uri, $route)) {
                return $number; // Return the matching number
            }
        }

        return null;
    }

    private static function matchURI($uri, $route)
    {
        // Replace dynamic parameters like {lang} with a regex pattern
        $pattern = preg_replace('/\{[^\/]+\}/', '[^\/]+', $route);
        $pattern = "#^" . $pattern . "$#";

        return preg_match($pattern, $uri);
    }

    public static function protectedRoutes(){
        return [
            self::$baseUrl.'/package/{id}/print',
            self::$baseUrl.'/package/list/print',
            self::$baseUrl.'/order/{order_id}/package/print',
        ];
    }

}
