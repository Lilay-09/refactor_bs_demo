<?php

namespace App\Services;

use Illuminate\Http\Request;

interface WarehouseService
{
    //
    public function createWarehouse(Request $req,object $authUser):object;

    public function updateWarehouse(int $id,Request $req,object $authUser):object;

    public function getOneWarehouse(int $id,object $authUser):object;

    public function getWarehouses(Request $req,object $authUser):object;

    public function deleteWarehouse(int $id,object $authUser):object;
}
