<?php

namespace App\Http\Controllers\V1;

use ApiResponse;
use App\Http\Controllers\Controller;
use App\Services\FeedbackQuestionService;
use App\Services\UserService;
use Illuminate\Http\Request;

class FeedbackQuestionController extends Controller
{
    //
    protected FeedbackQuestionService $feedbackQuestionService;
    protected $authUser;
    public function __construct(){
        $this->feedbackQuestionService = new FeedbackQuestionService();
        $this->authUser = UserService::getAuthUser();
    }

    public function getFeedbackQuestions(Request $req){
        return ApiResponse::flex($this->feedbackQuestionService->getFeedbackQuestions($req,$this->authUser));
    }

    public function getOneFeedbackQuestion(Request $req){
        return ApiResponse::flex($this->feedbackQuestionService->getOneFeedbackQuestion($req->id,$this->authUser));
    }

    public function updateFeedbackQuestion(Request $req){
        return ApiResponse::flex($this->feedbackQuestionService->updateFeedbackQuestion($req,$req->id,$this->authUser));
    }

    public function createFeedbackQuestion(Request $req){
        return ApiResponse::flex($this->feedbackQuestionService->createFeedbackQuestion($req,$this->authUser));
    }

    public function deleteFeedbackQuestion(Request $req){
        return ApiResponse::flex($this->feedbackQuestionService->deleteFeedbackQuestion($req->id,$this->authUser));
    }

    public function reoderQuestion(Request $req){
        return ApiResponse::flex($this->feedbackQuestionService->reoderQuestion($req->order_ids,$this->authUser));
    }
}
