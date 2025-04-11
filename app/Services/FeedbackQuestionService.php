<?php

namespace App\Services;
use Illuminate\Http\Request;
use App\Models\FeedbackQuestion;
use DataResponse;

class FeedbackQuestionService
{
    private function FeedbackQuestionValidation(Request $req){
        return validator($req->all(),[
			'question_en' => 'required|string|max:150',
			'question_km' => 'nullable|max:150',
			'form_id' => 'required|int'
		]);
    }
    // Your service methods go here
    public function createFeedbackQuestion(Request $req,$user){
        $validate = $this->FeedbackQuestionValidation($req);
        if($validate->fails()) return DataResponse::ValidateFail($validate->errors()->first());
        $inputs = $validate->validated();
        $inputs['create_uid'] = $user->id;
        $inputs['update_uid'] = $user->id;
        $inputs['company_id'] = $user->company_id;
        $inputs['branch_id'] = $user->branch_id;
        FeedbackQuestion::create($inputs);
        return DataResponse::JsonResult(null,false,"Created");
    }

    public function updateFeedbackQuestion (Request $req,int $id,$user){
        $FeedbackQuestion = FeedbackQuestion::where('is_deleted',0)
        ->find($id);
        if(!$FeedbackQuestion) return DataResponse::NotFound("FeedbackQuestion not found");
        $validate = $this->FeedbackQuestionValidation($req);
        if($validate->fails()) return DataResponse::ValidateFail($validate->errors()->first());
        $inputs = $validate->validated();
        $inputs['update_uid'] = $user->id;
        $inputs['company_id'] = $user->company_id;
        $inputs['branch_id'] = $user->branch_id;
        $FeedbackQuestion->update($inputs);
        return DataResponse::JsonResult(null,false,"Updated");
    }

    public function getFeedbackQuestions(Request $req,$user){
        $FeedbackQuestion = FeedbackQuestion::query()->where('is_deleted',0)
        ->select('id','question_en','question_km','form_id')
        ->orderByDesc('display_order')->orderByDesc('id');
        return DataResponse::PaginationV1($FeedbackQuestion,$req);
    }

    public function getOneFeedbackQuestion(int $id,$user){
        $FeedbackQuestion = FeedbackQuestion::where('is_deleted',0)
        ->select('id','question_en','question_km','form_id')
        ->find($id);
        if(!$FeedbackQuestion) return DataResponse::NotFound("FeedbackQuestion not found");
        return DataResponse::JsonResult($FeedbackQuestion,false,"get one FeedbackQuestion");
    }

    public function deleteFeedbackQuestion(int $id,$user){
        $FeedbackQuestion = FeedbackQuestion::where('is_deleted',0)->find($id);
        if(!$FeedbackQuestion) return DataResponse::NotFound("FeedbackQuestion not found");
        $FeedbackQuestion->update([
            'is_deleted' => 1,
            'deleted_uid' => $user->id,
            'deleted_datetime'=> now()
        ]);
        return DataResponse::JsonResult(null,false,"Deleted");
    }

    public function reoderQuestion($orderIds,$user){
        $orderItems = [];
        $userId = $user->id;
        $feedbackQuestions = FeedbackQuestion::whereIn('id',$orderIds)->get()->keyBy('id');
        foreach($orderIds as $idx => $id){
            if(empty($feedbackQuestions[$id])) {
                return DataResponse::NotFound();
            }
            $fq = $feedbackQuestions[$id];
            $fq->display_order = $idx + 1;
            $fq->update_uid = $userId;
            $orderItems[] = $fq->only(['id', 'display_order', 'update_uid']);
        }
        if (!empty($orderItems)) {
            FeedbackQuestion::upsert($orderItems, ['id']);
        }
        return DataResponse::JsonResult(null,false,'Updated');
    }
}
