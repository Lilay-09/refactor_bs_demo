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
        // Log::info($req->all());
        return ApiResponse::flex($this->commentService->addComment($req, $authUser));
    }

    public function getPackageComments(Request $req): object
    {
        $authUser = auth()->user();
        return ApiResponse::flex($this->commentService->getPackageComments($req->packageId, $authUser));
    }
}
