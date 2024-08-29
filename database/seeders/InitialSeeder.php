<?php

namespace Database\Seeders;

use App\Models\AdjustmentType;
use App\Models\Bank;
use App\Models\CustomerType;
use App\Models\MovementType;
use App\Models\PaymentMethod;
use App\Models\PurchaseStatuses;
use App\Models\StockLocation;
use App\Models\StockLocationType;
use App\Models\VendorType;
use DB;
use Illuminate\Database\Seeder;

class InitialSeeder extends Seeder
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
                'name' => 'Partially Received'
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

        StockLocationType::insert([
            ['name' => 'Warehouse'],
            ['name' => 'Branch Store']
        ]);
        VendorType::insert([
            [
                'name' => 'Manufacturers',
                'create_uid' => $userId,
                'update_uid' => $userId,
                'branch_id' => $branchId,
                'company_id' => $comapanyId,
            ]
        ]);


        CustomerType::insert([
            [
                'name' => 'Normal Customer',
                'discount_percent' => 0,
                'create_uid' => $userId,
                'update_uid' => $userId,
                'branch_id' => $branchId,
                'company_id' => $comapanyId
            ],
            [
                'name' => 'Vip1 Customer',
                "discount_percent" => 5,
                'create_uid' => $userId,
                'update_uid' => $userId,
                'branch_id' => $branchId,
                'company_id' => $comapanyId
            ]
        ]);

        PaymentMethod::insert([
            ['name' => 'Cash'],
            ['name' => 'Bank'],
            ['name' => 'Credit Card']
        ]);

        Bank::insert([
            ['name' => 'ABA','create_uid' => $userId,
                'update_uid' => $userId,
                'branch_id' => $branchId,
                'company_id' => $comapanyId],
            ['name' => 'ACLEDA','create_uid' => $userId,
                'update_uid' => $userId,
                'branch_id' => $branchId,
                'company_id' => $comapanyId],
            ['name' => 'VATANAK','create_uid' => $userId,
                'update_uid' => $userId,
                'branch_id' => $branchId,
                'company_id' => $comapanyId]
        ]);

        DB::table('payment_statuses')->insert([
            [
                'name' => 'Pending'
            ],
            [
                'name' => 'Partially Paid'
            ],
            [
                'name' => 'Fully Paid'
            ]
        ]);
        MovementType::insert([
            ['name' => 'Transfer In'],
            ['name' => 'Transfer Out'],
            ['name' => 'Sold'],
            ['name' => 'Receive Order'],
            ['name' => 'Donation'],
            ['name' => 'Return']
        ]);

        StockLocation::insert([
            'name' => 'Main Warehouse',
            'type_id' => 1,
            'main' => true,
            'create_uid' => $userId,
            'update_uid' => $userId,
            'branch_id' => $branchId,
            'company_id' => $comapanyId
        ]);
    }
}
