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

    public function subscribeTopic($channel,$token,$user,$deviceId=null,$os_name,$platform='web'){
        $companyId = $user->company_id ?? null;
        $userId = $user->id ?? null;
        $topics = $this->getTopics($companyId,$channel,$userId);
        $notifTokenId = $this->saveUserNotification($channel,$token,$user,$deviceId,$os_name,$platform);
        foreach($topics as $topic){
            $sub = $this->subscribe($token,$topic->name);
            if($sub->error) return $sub;

        }
        return DataResponse::JsonResult($topic,false,'subscribe');
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

    private function saveUserNotification($channel,$token,$user,$deviceId=null,$os_name=null,$platform='web',$serviceName='Firebase'){
        $id = null;
        $sub = UserNotificationToken::where('user_id',$user->id)->where('device_id',$deviceId)->first();
        if(!$sub) {
            $create = UserNotificationToken::create([
                'service_name' => $serviceName,
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


    private function subscribe($token,$topic)
    {
        try{
            if($topic){
                $this->messaging->subscribeToTopic($topic, $token);
            }
            return DataResponse::JsonResult([
                'action' => 'subscribed',
                'topic'=> $topic
            ]);
        }catch (InvalidArgument | NotFound $e) {
            return DataResponse::error($e->getMessage());
        }
        catch(Exception $e){
            Log::error($e->getMessage());
            Log::error($e->getTraceAsString());
            return DataResponse::error('Fail to subscribe');
        }
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
            // Handle invalid message errors
             return DataResponse::error('Invalid message');
            // return DataResponse::result([
            //     'error' => 'Invalid message: ' . $e->getMessage()
            // ], 400);
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

}
