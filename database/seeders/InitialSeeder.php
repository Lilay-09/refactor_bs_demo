<?php

namespace Database\Seeders;

use App\Models\AdjustmentType;
use App\Models\PurchaseStatuses;
use App\Models\StockLocationType;
use App\Models\StockMovementType;
use App\Models\VendorType;
use DB;
use Illuminate\Database\Seeder;

class initialSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */




    public function run()
    {
        $userId  = DB::table('users')->insertGetId([
            'first_name' => 'Root',
            'last_name' => 'ឬសគុល',
            'user_name' => 'some where',
            'phone' => '092335554',
            'email' => 'root1@gmail.com',
            'password' => \Hash::make('123456'),
            'system_admin' => true,
            'create_uid' => 1, //* just default val
            'update_uid' => 1, //* just default val
            'branch_id'=>1, //* just default val
            'company_id' => 1 //* just default val

        ]);
        $comapanyId  = DB::table('companies')->insertGetId([
            'name' => 'School Root',
            'name_km' => 'ក្រុមហ៊ុន',
            'address' => 'some where',
            'email' => 'school@gmail.com',
            'phone' => '092335554',
            'description' => 'This is Root, Root represent to all branches',
            'create_uid' => $userId,
            'company_type' => 'automotive',
            'update_uid' => $userId
        ]);
        $branchId =  DB::table('branches')->insertGetId([
            'name' => 'First Branch',
            'name_km' => 'សាខា',
            'address' => 'address',
            'company_id' => $comapanyId,
            'phone' => '092335554',
            'description' => 'The initail branch',
            'create_uid' => $userId,
            'update_uid' => $userId
        ]);

        $roleId = DB::table('roles')->insertGetId([
            'name' => 'Admin',
            'description' => '',
            'create_uid' => $userId,
            'update_uid' => $userId,
            'company_id' => $comapanyId,
        ]);

        $userRoles = DB::table('user_roles')->insert([
            'user_id' => $userId,
            'role_id' => $roleId
        ]);

        //**

        $updateUser = DB::table('users')->where('id',$userId)->update([
            'create_uid' => $userId,
            'update_uid' => $userId,
            'branch_id' => $branchId,
            'company_id' => $comapanyId,
        ]);

        PurchaseStatuses::insert([
            [
                'name' => 'Pending'
            ],
            [
                'name' => 'Approved',
            ],
            [
                'name' => 'Rejected',
            ],
            [
                'name' => 'Canceled'
            ],
            [
                'name' => 'Received'
            ],
            
        ]);

        AdjustmentType::insert([
            ['name' => 'Damage'],
            ['name' => 'Theft'],
            ['name' => 'Return'],
            ['name' => 'Donation'],
            ['name' => 'Expire']
        ]);

        StockMovementType::insert([
            ['name' => 'Transfer In'],
            ['name' => 'Transfer Out']
        ]);

        StockLocationType::insert([
            ['name' => 'Warehouse'],
            ['name' => 'Branch Store'],
        ]);

    }
}
