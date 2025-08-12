<?php

namespace App\Services;

use Illuminate\Http\Request;

interface CommentService
{
    //
    public function addComment(Request $req, object $authUser): object;
    public function getPackageComments(int $packageId, object $authUser): object;
    public function createPackageCommentSection(Request $req, object $authUser):object;
    public function getPackageCommentSections(Request $req,object $authUser):object;
    public function getPackageCommentDetailsById(int $id,object $authUser): object;
}
