<?php

namespace App\Services;

use Illuminate\Http\Request;

interface CommentService
{
    //
    public function addComment(Request $req, object $authUser): object;
    public function getPackageCommentDetailsByPackageId(Request $req, int $packageId, object $authUser): object;
    public function createPackageCommentSection(Request $req, object $authUser):object;
    public function getPackageCommentSections(Request $req,object $authUser):object;
    public function getPackageCommentDetailsById(Request $req,$id,object $authUser): object;
    public function deleteCommentDescriptionById(int $commentId,string|int $treadId,int $detailId, object $authUser): object;
    public function editCommentDescriptionById(int $commentId,string|int $treadId,int $detailId, object $authUser): object;
}
