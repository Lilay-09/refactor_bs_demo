<?php

namespace App\Services;

use Illuminate\Http\Request;

interface TransferService
{
    //
    public function createTransfer(Request $req,object $authUser):object;
    public function updateTransfer():object;
}

