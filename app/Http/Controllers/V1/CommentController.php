<?php

namespace App\Http\Controllers\V1;

use ApiResponse;
use App\Http\Controllers\Controller;
use App\Services\CommentService;
use Illuminate\Http\Request;
use Log;

class CommentController extends Controller
{
    //
    public function __construct(private CommentService $commentService)
    {

    }

    public function addComment(Request $req): object
    {
        $authUser = auth()->user();
        return ApiResponse::flex($this->commentService->addComment($req, $authUser));
    }

    public function getPackageCommentDetailsByPackageId(Request $req): object
    {
        $authUser = auth()->user();
        return ApiResponse::flex($this->commentService->getPackageCommentDetailsByPackageId($req,$req->packageId, $authUser));
    }

    public function createPackageCommentSection(Request $req){
        $authUser = auth()->user();
        return ApiResponse::flex($this->commentService->createPackageCommentSection($req,$authUser));
    }

    public function getPackageCommentSections(Request $req){
        $authUser = auth()->user();
        return ApiResponse::flex($this->commentService->getPackageCommentSections($req,$authUser));
    }

    public function getPackageCommentDetailsById(Request $req){
        $authUser = auth()->user();
        return ApiResponse::flex($this->commentService->getPackageCommentDetailsById($req,$req->id,$authUser));
    }

    public function deleteCommentDescriptionById(Request $req){
        $authUser = auth()->user();
        return ApiResponse::flex($this->commentService->deleteCommentDescriptionById($req->id,$req->threadId,$req->detailId, $authUser));
    }

    public function editCommentDescriptionById(Request $req){
        $authUser = auth()->user();
        return ApiResponse::flex($this->commentService->editCommentDescriptionById($req->id,$req->threadId,$req->detailId, $authUser));
    }
}
