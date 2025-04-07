<?php

namespace App\Http\Controllers\V1;

use ApiResponse;
use App\Services\FeedbackFormService;
use App\Services\UserService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class FeedbackFormController extends Controller
{
    //
    protected FeedbackFormService $feedbackFormService;
    protected $authUser;

    public function __construct(FeedbackFormService $feedbackFormService)
    {
        $this->feedbackFormService = $feedbackFormService;
        $this->authUser = UserService::getAuthUser();
    }

    public function createFeedbackForm(Request $req){
        return ApiResponse::flex($this->feedbackFormService->createFeedbackForm($req,$this->authUser));
    }

    public function getFeedbackForms(Request $req){
        return ApiResponse::flex($this->feedbackFormService->getFeedbackForms($req,$this->authUser));
    }
    public function getOneFeedbackForm(Request $req){
        return ApiResponse::flex($this->feedbackFormService->getOneFeedbackForm($req->id,$this->authUser));
    }

    public function updateFeedbackForm(Request $req){
        return ApiResponse::flex($this->feedbackFormService->updateFeedbackForm($req,$req->id,$this->authUser));
    }
}
