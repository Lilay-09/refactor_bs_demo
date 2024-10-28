<?php

namespace App\Services;

use DataResponse;
use Exception;
use Illuminate\Http\Request;
use Kreait\Firebase\Exception\Messaging\InvalidMessage;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;

class CouldMessagingService
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

    private function subsribeValidation(Request $req){
        return validator($req->all(),[
            'token' => 'required',
            'device_id' => 'nullable',
        ]);
    }
    public function subscribeTopic(Request $req,$type){

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

    public function sendNotificationByTopic($topic,$title,$body){

    }
}
