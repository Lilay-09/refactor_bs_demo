<?php

namespace App\Services;

use ApiResponse;
use App\Models\NotificationTopic;
use App\Models\UserNotificationToken;
use DataResponse;
use Exception;
use Illuminate\Http\Request;
use Kreait\Firebase\Exception\Messaging\InvalidArgument;
use Kreait\Firebase\Exception\Messaging\InvalidMessage;
use Kreait\Firebase\Exception\Messaging\NotFound;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;
use Log;
use Notification;

class CloudMessagingService
{
    // Your service methods go here
    protected $serviceName = [
        'Firebase',
        'Aby'
    ];
    protected $deviceTypes = [
        'Android'
    ];

    protected $firebase;
    protected $messaging;
    protected $serviceAccount;
    public function __construct(){
        $serviceAccountPath = storage_path('fcm_service_account.json');
        $this->serviceAccount = json_decode(file_get_contents($serviceAccountPath), true);

        if (!$this->serviceAccount) {
            // Handle error: Failed to load service account credentials
            throw new Exception('Failed to load service account credentials.');
        }
        $this->firebase = (new Factory)
            ->withServiceAccount($this->serviceAccount);
        $this->messaging = $this->firebase->createMessaging();
    }

    private function subsribeValidation(Request $req,$type){
        return validator($req->all(),[
            'token' => 'required',
            'device_id' => 'nullable',
        ]);
    }

    private function sendNotifValidation(Request $req,$type){
        return validator($req->all(),[
            $type => 'required|string',
            'title' => 'required|string',
            'body' => 'nullable|string'
        ]);
    }

    public function getTopics($companyId,$channel,$userId){
        $topic = GeneralSettingService::getGeneralTopics($companyId,$channel,$userId);
        $topics = [
            'driver' => [
                (object)[
                    'type' => 'public',
                    'name' => $topic->public
                ],
                (object)[
                    'type' => 'private',
                    'name' => $topic->private
                ]
            ],
            'merchant' => [
                (object)[
                    'type' => 'public',
                    'name' => $topic->public
                ],
                (object)[
                    'type' => 'special',
                    'name' => 'promotion'
                ],
                (object)[
                    'type' => 'private',
                    'name' => $topic->private
                ]
            ],
            'admin' => [
                (object)[
                    'type' => 'public',
                    'name' => $topic->public
                ],
                (object)[
                    'type' => 'private',
                    'name' => $topic->private
                ]
            ]
        ];
        return $topics[$channel];
    }

    public function subscribeTopic($channel,$req,$user){
        $companyId = $user->company_id ?? null;
        $userId = $user->id ?? null;
        $token = $req->token ?? null;
        if(!$token) return DataResponse::ValidateFail(__('messages.info',[
            'info' => 'FCM token is required'
        ]));
        $deviceId = $req->device_id ?? null;
        $topics = $this->getTopics($companyId,$channel,$userId);
        $deviceInfo = $this->getUserDevice($req);
        $notifTokenId = $this->saveUserNotification($channel,$token,$user,$deviceId,$deviceInfo->device,$deviceInfo->platform);
        foreach($topics as $topic){
            $this->subscribe($token,$topic->name);
            $this->saveNotifTopic($notifTokenId,$topic->name,$topic->type,$user);
        }
        return DataResponse::JsonResult(null,false,'subscribed');
    }

    private function saveNotifTopic($token_id,$topic,$topicType,$user){
        $notifTopic = NotificationTopic::where('token_id',$token_id)->where('topic',$topic)->where('type',$topicType)->first();
        if(!$notifTopic){
            NotificationTopic::create([
                'token_id' => $token_id,
                'topic' => $topic,
                'type' => $topicType,
                'create_uid' => $user->id,
                'update_uid' => $user->id,
                'company_id' => $user->company_id,
                'branch_id' => $user->branch_id,
            ]);
        }
    }

    private function saveUserNotification($channel,$token,$user,$deviceId=null,$os_name=null,$platform='Web',$serviceName='Firebase'){
        $id = null;
        $sub = UserNotificationToken::where('user_id',$user->id)->where('device_id',$deviceId)->first();
        if(!$sub) {
            $create = UserNotificationToken::create([
                'service_name' => $serviceName,
                'channel' => $channel,
                'token' => $token,
                'device_id' => $deviceId,
                'platform' => $platform,
                'os_name' => $os_name,
                'subscribe_datetime' => now(),
                'user_id' => $user->id,
                'create_uid' => $user->id,
                'update_uid' => $user->id,
                'company_id' => $user->company_id,
                'branch_id' => $user->branch_id,
            ]);
            $id = $create->id;
        }else{
            $sub->update([
                'service_name' => $serviceName,
                'token' => $token,
                'device_id' => $deviceId,
                'platform' => $platform,
                'os_name' => $os_name,
                'subscribe_datetime' => now(),
                'user_id' => $user->id,
                'update_uid' => $user->id,
                'company_id' => $user->company_id,
                'branch_id' => $user->branch_id,
            ]);
            $id = $sub->id;
        }

        return $id;
    }

    private function subscribe($token, $topic)
    {
        $this->messaging->subscribeToTopic($topic, $token);
    }


    private function sendNotification($target,$targetValue,$title,$body){
        $message = CloudMessage::withTarget($target, $targetValue)
            ->withNotification([
                'title' => $title,
                'body' => $body,
            ]);
        try {
            // Send the message
            $this->messaging->send($message);
            // Handle success
            return DataResponse::JsonResult([
                'action'=> 'Sent',
            ]);
        } catch (InvalidMessage $e) {

            return DataResponse::error('Invalid message');
        } catch (Exception $e) {
            // Handle other exceptions
            return DataResponse::error('Failed to send notification');
        }
    }

    public function sendNotificationByTopic(Request $req){
        $validate = $this->sendNotifValidation($req,'topic');
        if($validate->fails()) return DataResponse::ValidateFail($validate->errors()->first());
        return $this->sendNotification('topic',$req->topic,$req->title,$req->body);
    }

    public function sendNotificationByToken(Request $req){
        $validate = $this->sendNotifValidation($req,'token');
        if($validate->fails()) return DataResponse::ValidateFail($validate->errors()->first());
        $notif = $this->sendNotification('token',$req->token,$req->title,$req->body);
        return $notif;
    }

    private function getUserDevice(Request $req)
    {
        $userAgent = $req->headers->get('User-Agent');
        $deviceType = 'Unknown';
        $deviceModel = 'Unknown';
        $platform = 'Unknown';
        $ip = $req->getClientIp();

        // Determine the device type and model
        switch (true) {
            case strpos($userAgent, 'Android') !== false:
                $deviceType = 'Android';
                preg_match('/Android.*?; (.*?)(;|$)/', $userAgent, $matches);
                $deviceModel = isset($matches[1]) ? trim($matches[1]) : 'Unknown Model';
                $platform = 'Mobile App'; // Adjusted for mobile applications
                break;

            case strpos($userAgent, 'iPhone') !== false:
                $deviceType = 'iOS';
                $deviceModel = 'iPhone';
                $platform = 'Mobile App'; // Adjusted for mobile applications
                break;

            case strpos($userAgent, 'iPad') !== false:
                $deviceType = 'iOS';
                $deviceModel = 'iPad';
                $platform = 'Mobile App'; // Adjusted for mobile applications
                break;

            // Checking for web browser requests
            case strpos($userAgent, 'Mozilla') !== false:
                if (strpos($userAgent, 'Chrome') !== false || strpos($userAgent, 'Safari') !== false) {
                    $platform = 'Web';
                }
                // Determine desktop type and model
                if (strpos($userAgent, 'Windows NT') !== false) {
                    $deviceType = 'Windows';
                    preg_match('/Windows NT (\d+\.\d+)/', $userAgent, $matches);
                    $deviceModel = 'Windows NT ' . (isset($matches[1]) ? $matches[1] : 'Unknown Version');
                } elseif (strpos($userAgent, 'Macintosh') !== false) {
                    $deviceType = 'Mac';
                    $deviceModel = 'Macintosh';
                } elseif (strpos($userAgent, 'Linux') !== false) {
                    $deviceType = 'Linux';
                    $deviceModel = 'Linux Device';
                }
                break;

            default:
                $deviceType = 'Unknown';
                $deviceModel = 'Unknown Model';
                $platform = 'Unknown';
                break;
        }

        return (object)[
            'device' => $deviceType . '|' . $deviceModel . '|' . $ip,
            'platform' => $platform
        ];
    }

    // private function getLocationFromIp($ip)
    // {
    //     // Using ipinfo.io as an example

    //     $ip = Http::get("https://api.ipify.org");
    //     $response = Http::get("http://ipinfo.io/{$ip}?token=7e2ecd4236ef49");

    //     // Check if the request was successful
    //     if ($response->successful()) {
    //         return $response;
    //     }

    //     return null;  // Return null if the request fails
    // }
}
