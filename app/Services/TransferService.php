<?php

namespace App\Services;

use Illuminate\Http\Request;

interface TransferService
{
    //
    public function createTransfer(Request $req,object $authUser):object;
    public function updateTransfer(int $id, Request $req, object $authUser):object;
    public function getOneTransfer(int $id,object $authUser):object;
    public function deleteTransfer(int $id,object $authuser):object;
    public function getTransfers(Request $req,object $authUser):object;
    public function createReceive(int $id,Request $req,object $authUser):object;
    public function getReceiveTransfers(Request $req,object $authUser):object;
    public function getReceiveTransferById(int $id,object $authUser):object;
    // public function getAvailableTransfer(Request $req,object $authUser):object;
}

