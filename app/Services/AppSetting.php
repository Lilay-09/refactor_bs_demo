<?php

namespace App\Services;
use App\Models\PrivacyStatement;
use App\Models\TermCondition;
use Barryvdh\DomPDF\PDF;
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
        if(!$model) return DataResponse::NotFound(__('messages.not_found',[
            'info' => $modelName
        ]));

        return DataResponse::JsonResult($model,false,__('messages.get one',[
            'info' => $modelName
        ]));
    }

    public static function sendSms($sender='SMS Test', $to="092335554", $content="test content") {
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
            return DataResponse::JsonResult($response);
        }
        return DataResponse::Error($response->json()['message']);
    }


    public static function generatePDF()
    {
        // Example data
        $data = [
            'title' => 'Dynamic PDF Example',
            'date' => now()->toDateTimeString(),
            'content' => 'This PDF was generated dynamically when requested.',
        ];

        // Load the Blade view and pass data
        $pdf = PDF::loadView('pdf.package_history', $data);

        // Return the PDF file for viewing (inline)
        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="dynamic-pdf.pdf"',
        ]);
    }

    //  static function sendSms($phone_number='092335554', $text = 'testing', $sender_name = 'SMS Info') {
    // //    return DV::depends(1,['message'=>$text]);
    //   try{
    //     if (empty($text) || empty($phone_number)) return DataResponse::ValidateFail("phone_number or text cannot be empty");
    //     $privateKey = env('PLASGATE_PRIVATE_KEY') ?? '';
    //     $secret = env('PLASGATE_SECRET') ?? '';
    //     $phone_number = Helper::formatPhoneNumber($phone_number);
    //     return $phone_number;
    //     $payload = ['sender'=>$sender_name,'to'=>  $phone_number,'content'=> $text];

    //     $ch = curl_init();
    //     curl_setopt_array($ch, array(
    //         CURLOPT_URL => 'https://cloudapi.plasgate.com/rest/send?private_key=' . $privateKey,
    //         CURLOPT_RETURNTRANSFER => true,
    //         CURLOPT_ENCODING => '',
    //         CURLOPT_MAXREDIRS => 10,
    //         CURLOPT_TIMEOUT =>0,
    //         CURLOPT_FOLLOWLOCATION => true,
    //         CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    //         CURLOPT_CUSTOMREQUEST => 'POST',
    //         CURLOPT_SSL_VERIFYPEER => 2,
    //         CURLOPT_FAILONERROR=>true,
    //         // CURLOPT_CAINFO => storage_path('plasgate/ed4af1b392f59973.pem'),
    //         CURLOPT_POSTFIELDS => json_encode($payload),
    //         CURLOPT_HTTPHEADER => array(
    //             'X-Secret: ' . $secret,
    //             'Content-Type: application/json'
    //         ),
    //     ));

    //     $json_string = curl_exec($ch);
    //     $err_message = null;

    //     if (curl_errno($ch)) {
    //         $err_message = curl_error($ch);
    //         if (strpos($err_message, 'Could not resolve host') !== false) {
    //             $err_message = "Failed to connect to the SMS server. You may check your internet connection";
    //         }
    //     }

    //     curl_close($ch);
    //     if ($err_message) {
    //         return DataResponse::Error('sms provider issue: '.$err_message);
    //     }

    //     return DataResponse::JsonResult(json_encode($json_string));
    //    }catch(Exception $e){
    //      Log::error('Failed to send sms: '.$text. ' to number '.$phone_number);
    //      Log::error($e->getMessage());
    //      Log::error($e->getTraceAsString());
    //      return DataResponse::Error('Failed to send sms: '.$text. ' to number ');
    //    }
    // }

}
