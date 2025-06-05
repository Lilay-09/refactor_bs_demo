<?php

namespace App\Services;

use Illuminate\Http\Request;

interface BranchService
{
    //

    public function createBranch(Request $req,object $authUser):object;
    public function updateBranch(int $id,Request $req,object $authUser):object;

    public function getOneBranch(int $id,object $authUser):object;
    public function getBranches(Request $req,object $authUser):object;

    public function deleteBranch(int $id,object $authUser):object;
}
