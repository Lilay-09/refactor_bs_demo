<?php

namespace App\Services;

use App\Models\TelegramBot;
use App\Models\TelegramSendLog;
use App\Models\User;
use DataResponse;
use Illuminate\Support\Facades\Auth;

class TelegramBotServiceImpl implements TelegramBotService
{
    // Your service methods go here
    public function create(array $arr){
        $validator = validator($arr,[
            'name' => 'required',
            'token' => 'required'
        ]);
        if($validator->fails()){
            return DataResponse::ValidateFail($validator->errors()->first());
        }
        $inputs = $validator->validated();
        $inputs['create_uid'] = Auth::user()->id;
        $inputs['update_uid'] = Auth::user()->id;
        $inputs['company_id'] = Auth::user()->company_id;
        $inputs['branch_id'] = Auth::user()->branch_id;
        $existsBot = $this->existsBot($inputs['token']);
        if($existsBot){
            return DataResponse::Duplicated('Bot is already exists (name: '.$existsBot->name.')');
        }
        TelegramBot::create($inputs);
        return DataResponse::JsonResult(null,false,__('messages.saved'));
    }

    public function updateById(int $id,array $arr){
        $validator = validator($arr,[
            'name' => 'required',
            'token' => 'required'
        ]);
        if($validator->fails()){
            return DataResponse::ValidateFail($validator->errors()->first());
        }
        $inputs = $validator->validated();
        $inputs['update_uid'] = Auth::user()->id;
        $inputs['company_id'] = Auth::user()->company_id;
        $inputs['branch_id'] = Auth::user()->branch_id;
        $telegramBot = TelegramBot::where('is_deleted',false)->find($id);
        if(!$telegramBot) {
            return DataResponse::NotFound(__('messages.not_found'));
        }
        $existsBot = $this->existsBot($inputs['token'],$id);
        if($existsBot){
            return DataResponse::Duplicated('Bot is already exists (name: '.$existsBot->name.')');
        }
        $telegramBot->update($inputs);
        return DataResponse::JsonResult(null,false,__('messages.saved'));
    }

    private function existsBot(string $token,?int $id=null){
        $qTb = TelegramBot::where('is_deleted',false)
        ->select('id','name','token')
        ->where('token',$token);
        if($id){
            $qTb->where('id','!=',$id);
        }
        return $qTb->first();
    }

    public function getBotById(int $id){
        $telegramBot = TelegramBot::where('is_deleted',false)
        ->select(['id','name','token'])
        ->find($id);
        if(!$telegramBot) {
            return DataResponse::NotFound(__('messages.not_found'));
        }
        return DataResponse::JsonResult($telegramBot);
    }

    public function getBots(array $filter)
    {
        return DataResponse::JsonResult(TelegramBot::where('is_deleted',false)->select(['id','name','token'])->get());
    }

    public function deleteBotById(int $id){
        $telegramBot = TelegramBot::where('is_deleted',false)
        ->find($id);
        if(!$telegramBot) {
            return DataResponse::NotFound(__('messages.not_found'));
        }
        $telegramBot->update([
            'is_deleted' => true,
            'deleted_uid' => Auth::user()->id,
            'deleted_datetime' => now()
        ]);
        return DataResponse::JsonResult(null,false,'Deleted');
    }

    public function sendLog(User $authUser,array $data){
        $validator = validator($data,[
            'package_count' => 'required|int',
            'receiver_id' => 'required|int',
            'start' => 'required',
            'end' => 'required',
        ]);
        if($validator->fails()){
            return DataResponse::ValidateFail($validator->errors()->first());
        }
        $inputs = $validator->validated();
        $inputs['sender_id'] = $authUser->id;
        $inputs['sent_at'] = now();
        $inputs['unique'] = strtotime($inputs['start']).strtotime($inputs['end']);
        TelegramSendLog::insert($inputs);
        return DataResponse::JsonResult(null,false,'Logged');
    }
    
}
