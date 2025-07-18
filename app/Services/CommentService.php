<?php

namespace App\Services;

use Illuminate\Http\Request;

interface CommentService
{
    //
    public function addComment(Request $req, object $authUser): object;
    public function getPackageComments(int $packageId, object $authUser): object;
}
