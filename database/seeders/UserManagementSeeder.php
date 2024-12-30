<?php

namespace Database\Seeders;

use App\Models\AppModule;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class UserManagementSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        //
        $appId = config('app.admin_app_id');
        AppModule::insert([
            [
                'id' => 100,
                'app_id' => $appId,
                'name' => 'user_management',
                'natvie_name' => 'User Management'
            ],
            [
                'id' => 150,
                'app_id' => $appId,
                'name' => 'driver_management',
                'natvie_name' => 'Driver Management'
            ],
            [
                'id' => 200,
                'app_id' => $appId,
                'name' => 'merchant_management',
                'natvie_name' => 'Merchant Management'
            ],
            [
                'id' => 251,
                'app_id' => $appId,
                'name' => 'booking_center',
                'natvie_name' => 'Booking Center'
            ],
            [
                'id' => 252,
                'app_id' => $appId,
                'name' => 'package_trails',
                'natvie_name' => 'Package Trails'
            ],
            [
                'id' => 253,
                'app_id' => $appId,
                'name' => 'Fleet Management',
                'natvie_name' => 'Fleet Management'
            ],
            [
                'id' => 254,
                'app_id' => $appId,
                'name' => 'completed_deliveries',
                'natvie_name' => 'Completed Deliveries'
            ],
            [
                'id' => 255,
                'app_id' => $appId,
                'name' => 'Driver Transaction',
                'natvie_name' => 'Driver Transaction'
            ],
            [
                'id' => 256,
                'app_id' => $appId,
                'name' => 'Driver Balance',
                'natvie_name' => 'Driver Balance'
            ],
            [
                'id' => 257,
                'app_id' => $appId,
                'name' => 'driver_commission',
                'natvie_name' => 'Driver Commission'
            ],
            [
                'id' => 258,
                'app_id' => $appId,
                'name' => 'merchant_transaction',
                'natvie_name' => 'Merchant Transaction'
            ],
            [
                'id' => 259,
                'app_id' => $appId,
                'name' => 'Merchant Balance',
                'natvie_name' => 'Merchant Transaction'
            ],
            [
                'id' => 260,
                'app_id' => $appId,
                'name' => 'pickup_list',
                'natvie_name' => 'Pickup List'
            ],
            [
                'id' => 261,
                'app_id' => $appId,
                'name' => 'daily_packages_list',
                'natvie_name' => 'Daily Packages List'
            ],
            [
                'id' => 262,
                'app_id' => $appId,
                'name' => 'daily_package_summary',
                'natvie_name' => 'Daily Package Summary'
            ],
            [
                'id' => 263,
                'app_id' => $appId,
                'name' => 'settle_statement',
                'natvie_name' => 'Settle Statement'
            ],
            [
                'id' => 264,
                'app_id' => $appId,
                'name' => 'operations_summary',
                'natvie_name' => 'Operations Summary'
            ],
            [
                'id' => 265,
                'app_id' => $appId,
                'name' => 'review_and_feedback',
                'natvie_name' => 'Review and Feedback'
            ],
            [
                'id' => 266,
                'app_id' => $appId,
                'name' => 'merchant_list',
                'natvie_name' => 'Merchant List'
            ],
            [
                'id' => 267,
                'app_id' => $appId,
                'name' => 'merchant_summary',
                'natvie_name' => 'Merchant Summary'
            ],
            [
                'id' => 268,
                'app_id' => $appId,
                'name' => 'driver_list',
                'natvie_name' => 'Driver List'
            ],
            [
                'id' => 269,
                'app_id' => $appId,
                'name' => 'payment_by_driver',
                'natvie_name' => 'Payment By Driver'
            ],
            [
                'id' => 270,
                'app_id' => $appId,
                'name' => 'detail_package_by_driver',
                'natvie_name' => 'Detail Package by Driver'
            ],
            [
                'id' => 271,
                'app_id' => $appId,
                'name' => 'driver_commission_payment',
                'natvie_name' => 'Driver Commission Payment'
            ],
            [
                'id' => 272,
                'app_id' => $appId,
                'name' => 'Company Profile',
                'natvie_name' => 'User Management'
            ],
            [
                'id' => 273,
                'app_id' => $appId,
                'name' => 'user_management',
                'natvie_name' => 'User Management'
            ],
        ]);
    }
}
