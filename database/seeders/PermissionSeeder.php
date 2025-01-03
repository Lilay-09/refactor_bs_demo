<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        //
         Permission::insert([
            //** Booking Center */
            [
                'id' => 200,
                'module_id' => 251,
                'name' => 'Quick Order',
                'create_uid' => 1,
                'update_uid' => 1,
                'company_id' => 1,
                'branch_id' => 1,
            ],
            [
                'id' => 201,
                'module_id' => 251,
                'name' => 'Assign Driver',
                'create_uid' => 1,
                'update_uid' => 1,
                'company_id' => 1,
                'branch_id' => 1,
            ],
            [
                'id' => 202,
                'module_id' => 251,
                'name' => 'Change Status',
                'create_uid' => 1,
                'update_uid' => 1,
                'company_id' => 1,
                'branch_id' => 1,
            ],
            [
                'id' => 203,
                'module_id' => 251,
                'name' => 'Change Driver',
                'create_uid' => 1,
                'update_uid' => 1,
                'company_id' => 1,
                'branch_id' => 1,
            ],
            [
                'id' => 204,
                'module_id' => 251,
                'name' => 'Delete Order',
                'create_uid' => 1,
                'update_uid' => 1,
                'company_id' => 1,
                'branch_id' => 1,
            ],
            [
                'id' => 205,
                'module_id' => 251,
                'name' => 'Add Package',
                'create_uid' => 1,
                'update_uid' => 1,
                'company_id' => 1,
                'branch_id' => 1,
            ],
            [
                'id' => 206,
                'module_id' => 251,
                'name' => 'Edit Package',
                'create_uid' => 1,
                'update_uid' => 1,
                'company_id' => 1,
                'branch_id' => 1,
            ],
            [
                'id' => 207,
                'module_id' => 251,
                'name' => 'Delete Package',
                'create_uid' => 1,
                'update_uid' => 1,
                'company_id' => 1,
                'branch_id' => 1,
            ],
            [
                'id' => 208,
                'module_id' => 251,
                'name' => 'Print Daily Package',
                'create_uid' => 1,
                'update_uid' => 1,
                'company_id' => 1,
                'branch_id' => 1,
            ],

            //** Package Trails */
            [
                'id' => 209,
                'module_id' => 251,
                'name' => 'Delete Package',
                'create_uid' => 1,
                'update_uid' => 1,
                'company_id' => 1,
                'branch_id' => 1,
            ],
            [
                'id' => 210,
                'module_id' => 251,
                'name' => 'Print Daily Package',
                'create_uid' => 1,
                'update_uid' => 1,
                'company_id' => 1,
                'branch_id' => 1,
            ],
            [
                'id' => 211,
                'module_id' => 251,
                'name' => 'Delete Package',
                'create_uid' => 1,
                'update_uid' => 1,
                'company_id' => 1,
                'branch_id' => 1,
            ],
            [
                'id' => 211,
                'module_id' => 251,
                'name' => 'Print Daily Package',
                'create_uid' => 1,
                'update_uid' => 1,
                'company_id' => 1,
                'branch_id' => 1,
            ],
            [
                'id' => 212,
                'module_id' => 251,
                'name' => 'Delete Package',
                'create_uid' => 1,
                'update_uid' => 1,
                'company_id' => 1,
                'branch_id' => 1,
            ],
            [
                'id' => 2,
                'module_id' => 251,
                'name' => 'Print Daily Package',
                'create_uid' => 1,
                'update_uid' => 1,
                'company_id' => 1,
                'branch_id' => 1,
            ],
        ]);

    }

    public function rollback()
    {
        // Rollback logic, e.g., delete the seeded data
        Permission::query()->delete();
    }
}
