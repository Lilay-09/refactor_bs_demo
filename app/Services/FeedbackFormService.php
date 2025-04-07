<?php

namespace App\Services;
use Illuminate\Http\Request;
use App\Models\FeedbackForm;
use DataResponse;

class FeedbackFormService
{
    private function FeedbackFormValidation(Request $req){
        return validator($req->all(),[
			'name_en' => 'required|string|max:50',
			'name_km' => 'nullable|max:50',
			'description' => 'nullable',
			'channel' => 'nullable'
		]);
    }
    // Your service methods go here
    public function createFeedbackForm(Request $req,$user){
        $validate = $this->FeedbackFormValidation($req);
        if($validate->fails()) return DataResponse::ValidateFail($validate->errors()->first());
        $inputs = $validate->validated();
        $inputs['create_uid'] = $user->id;
        $inputs['update_uid'] = $user->id;
        $inputs['company_id'] = $user->company_id;
        $inputs['branch_id'] = $user->branch_id;
        FeedbackForm::create($inputs);
        return DataResponse::JsonResult(null,"Created");
    }

    public function updateFeedbackForm (Request $req,$id,$user){
        $FeedbackForm = FeedbackForm::where('is_deleted',0)->find($id);
        if(!$FeedbackForm) return DataResponse::NotFound("FeedbackForm not found");
        $validate = $this->FeedbackFormValidation($req);
        if($validate->fails()) return DataResponse::ValidateFail($validate->errors()->first());
        $inputs = $validate->validated();
        $inputs['update_uid'] = $user->id;
        $inputs['company_id'] = $user->company_id;
        $inputs['branch_id'] = $user->branch_id;
        $FeedbackForm->update($inputs);
        return DataResponse::JsonResult(null,"Updated");
    }

    public function getFeedbackForms(Request $req,$user){
        $FeedbackForm = FeedbackForm::query()->where('is_deleted',0);
        return DataResponse::PaginationV1($FeedbackForm,$req);
    }

    public function getOneFeedbackForm(int $id,$user){
        $FeedbackForm = FeedbackForm::where('is_deleted',0)->find($id);
        if(!$FeedbackForm) return DataResponse::NotFound("FeedbackForm not found");
        return DataResponse::JsonResult($FeedbackForm,"get one FeedbackForm");
    }

    public function deleteFeedbackForm(int $id,$user){
        $FeedbackForm = FeedbackForm::where('is_deleted',0)->find($id);
        if(!$FeedbackForm) return DataResponse::NotFound("FeedbackForm not found");
        $FeedbackForm->update([
            'is_deleted' => 1,
            'deleted_uid' => $user->id,
            'deleted_datetime'=> now()
        ]);
        return DataResponse::JsonResult(null,"Deleted");
    }
}
