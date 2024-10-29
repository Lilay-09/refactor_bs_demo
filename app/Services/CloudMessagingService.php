<?php

namespace App\Services;

use DataResponse;
use Exception;
use Illuminate\Http\Request;
use Kreait\Firebase\Exception\Messaging\InvalidArgument;
use Kreait\Firebase\Exception\Messaging\InvalidMessage;
use Kreait\Firebase\Exception\Messaging\NotFound;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;
use Log;

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
    // public function subscribeTopics(Request $req,$user=null){
    //     $validate = validator($req->all(),[
    //         'user_id' => 'nullable',
    //         'token' => 'required|string',
    //         'topics' => 'required|array'
    //     ]);
    //     if($validate->fails()) return DataResponse::ValidateFail($validate->errors()->first());
    //     $inputs = $validate->validated();
    //     $topics = $inputs['topic'];
    // }

    public function subscribeTopic($channel,$token,$user){
        $companyId = $user->company_id ?? null;
        $userId = $user->id ?? null;
        $topic = GeneralSettingService::getGeneralTopics($companyId,$channel,$userId);
        $public = $this->subscribe($token,$topic->public);
        $private = $this->subscribe($token,$topic->private);
        if($private->error) return $private;
        if($public->error) return $public;
        return DataResponse::JsonResult($topic,false,'subscribe');
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
