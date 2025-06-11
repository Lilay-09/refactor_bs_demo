<?php

namespace Database\Seeders;

use DB;
use Helper;
use Illuminate\Database\Seeder;

class InitialSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */


    protected $cambodiaCities = [
        'Phnom Penh',
        'Siem Reap',
        'Battambang',
        'Sihanoukville',
        'Kampong Cham',
        'Kampot',
        'Kandal',
        'Banteay Meanchey',
        'Kampong Chhnang',
        'Kampong Speu',
        'Kampong Thom',
        'Kep',
        'Koh Kong',
        'Kratie',
        'Mondulkiri',
        'Oddar Meanchey',
        'Pailin',
        'Preah Vihear',
        'Prey Veng',
        'Pursat',
        'Ratanakiri',
        'Stung Treng',
        'Svay Rieng',
        'Takeo',
        'Tbong Khmum'
    ];

    protected $phnomPenhDistricts = [
        'Chamkarmon',
        'Dangkao',
        'Kamboul',
        'Mean Chey',
        'Por Sen Chey',
        'Prampir Makara',
        'Preaek Pnov',
        'Russey Keo',
        'Sen Sok',
        'Chbar Ampov',
        'Chroy Changvar',
        'Toul Kork',
        'Boeng Keng Kang',
        'Khan Doun Penh',
        'Khan Kandal',
    ];



    public function run()
    {
        $userId  = DB::table('users')->insertGetId([
            'first_name' => 'NG',
            'last_name' => 'ADMIN',
            'has_account' => true,
            'username' => 'Admin',
            'phone' => '092335554',
            'login_name' => 'admin',
            'email' => 'admin@gmail.com',
            'account_type' => 'admin',
            'gender' => 'M',
            'password' => \Hash::make('gt123456dms'),
            'system_admin' => true,
            'create_uid' => 1, //* just default val
            'update_uid' => 1, //* just default val
            'branch_id'=>1, //* just default val
            'company_id' => 1 //* just default val
        ]);

        $userId  = DB::table('users')->insertGetId([
            'first_name' => 'NG',
            'last_name' => 'ADMIN',
            'has_account' => true,
            'username' => 'ngadmin',
            'phone' => '093691531',
            'login_name' => 'ngadmin',
            'email' => 'ngadmin@gmail.com',
            'account_type' => 'admin',
            'gender' => 'M',
            'password' => \Hash::make('ngx@123456'),
            'system_admin' => true,
            'create_uid' => 1, //* just default val
            'update_uid' => 1, //* just default val
            'branch_id'=>1, //* just default val
            'company_id' => 1 //* just default val
        ]);

        $comapanyId  = DB::table('companies')->insertGetId([
            'name_en' => 'School Root',
            'name_km' => 'ក្រុមហ៊ុន',
            'address' => 'some where',
            'email' => 'school@gmail.com',
            'phone' => '092335554',
            'description' => 'This is Root, Root represent to all branches',
            'create_uid' => $userId,
            'company_type' => 'express',
            'update_uid' => $userId
        ]);
        $branchId =  DB::table('branches')->insertGetId([
            'name_en' => 'First Branch',
            'name_km' => 'សាខា',
            'address_en' => 'address',
            'company_id' => $comapanyId,
            'phone' => '092335554',
            'description_en' => 'The initail branch',
            'create_uid' => $userId,
            'update_uid' => $userId
        ]);

        $branchId2 =  DB::table('branches')->insertGetId([
            'name_en' => 'Second Branch',
            'name_km' => 'សាខា2',
            'address_en' => 'address',
            'company_id' => $comapanyId,
            'phone' => '092335554',
            'description_en' => 'The initail branch',
            'create_uid' => $userId,
            'update_uid' => $userId
        ]);

        //** Create Driver */

        $driverId = DB::table('users')->insertGetId([
            'first_name' => 'Driver',
            'has_account' => true,
            'login_name' => '092233445',
            'last_name' => '',
            'username' => 'Driver',
            'phone' => '092233445',
            'email' => 'driver@gmail.com',
            'account_type' => 'driver',
            'gender' => 'M',
            'vehicle_type' => 'Motor',
            'password' => \Hash::make('123456'),
            'create_uid' => $userId,
            'update_uid' => $userId,
            'branch_id'=> $branchId,
            'shift_type' => 'full-time',
            'start_time' => '8:00',
            'end_time' => '17:00',
            'salary' => '450',
            'company_id' => $comapanyId
        ]);
        Helper::setRefCode('user_code_control','users','code',$branchId,$comapanyId,$driverId,null,'JSD');

        //** Create Merchant */

        $merchantId = DB::table('users')->insertGetId([
            'first_name' => 'Merchant',
            'last_name' => '',
            'login_name' => 'merchant',
            'has_account' => true,
            'username' => 'Merchant',
            'phone' => '012465653',
            'email' => 'merchant@gmail.com',
            'account_type' => 'merchant',
            'business_type' => 'Cosmetics',
            'gender' => 'F',
            'password' => \Hash::make('123456'),
            'create_uid' => $userId,
            'update_uid' => $userId,
            'branch_id'=> $branchId,
            'company_id' => $comapanyId
        ]);

        Helper::setRefCode('user_code_control','users','code',$branchId,$comapanyId,$merchantId,null,'JSM');


        $roleId = DB::table('roles')->insertGetId([
            'name_en' => 'Admin',
            'description' => '',
            'create_uid' => $userId,
            'update_uid' => $userId,
            'company_id' => $comapanyId,
        ]);

        $driverRoleId = DB::table('roles')->insertGetId([
            'name_en' => 'Driver',
            'description' => '',
            'create_uid' => $userId,
            'update_uid' => $userId,
            'company_id' => $comapanyId,
        ]);

        $merchantRoleId = DB::table('roles')->insertGetId([
            'name_en' => 'Merchant',
            'description' => '',
            'create_uid' => $userId,
            'update_uid' => $userId,
            'company_id' => $comapanyId,
        ]);

        $userRoles = DB::table('user_roles')->insert([
            'user_id' => $userId,
            'role_id' => $roleId
        ]);

        DB::table('user_roles')->insert([
            'user_id' => $driverId,
            'role_id' => $driverRoleId
        ]);

        DB::table('user_roles')->insert([
            'user_id' => $merchantId,
            'role_id' => $merchantRoleId
        ]);


        //**
        // $userCode = Helper::generateCode('JSA',$userId,'');
        $updateUser = DB::table('users')->where('id',$userId)->update([
            'create_uid' => $userId,
            'update_uid' => $userId,
            'branch_id' => $branchId,
            'company_id' => $comapanyId,
        ]);
        Helper::setRefCode('user_code_control','users','code',$branchId,$comapanyId,$userId,null,'JSA');

        //warehouse
        DB::table('warehouses')->insert([
            [
                'name_en' => 'Main Warehosue',
                'shortcut' => 'NGW1',
                'company_id' => $comapanyId,
                'branch_id' => $branchId
            ],
            [
                'name_en' => 'Main Warehosue',
                'shortcut' => 'NGW2',
                'company_id' => $comapanyId,
                'branch_id' => 2
            ]
        ]);

        //** Tracking Status */

        DB::table('tracking_statuses')->insert([
            [
                'id' => 1,
                'name' => 'Available For Pick',
                'stage' => 'pick',
                'hidden' => false,
                'create_uid' => $userId,
                'update_uid' => $userId,
                'branch_id' => $branchId,
                'company_id' => $comapanyId,
            ],
            [
                'id' => 2,
                'name' => 'Picked',
                'stage' => 'pick',
                'hidden' => false,
                'create_uid' => $userId,
                'update_uid' => $userId,
                'branch_id' => $branchId,
                'company_id' => $comapanyId,
            ],
            [
                'id' => 3,
                'name' => 'Accepted For Pickup',
                'stage' => 'pick',
                'hidden' => false,
                'create_uid' => $userId,
                'update_uid' => $userId,
                'branch_id' => $branchId,
                'company_id' => $comapanyId,
            ],
            [
                'id' => 4,
                'name' => 'Picked And Booked',
                'stage' => 'pick',
                'hidden' => false,
                'create_uid' => $userId,
                'update_uid' => $userId,
                'branch_id' => $branchId,
                'company_id' => $comapanyId,
            ],
            [
                'id' => 5,
                'name' => 'At Warehouse',
                'stage' => 'delivery',
                'hidden' => false,
                'create_uid' => $userId,
                'update_uid' => $userId,
                'branch_id' => $branchId,
                'company_id' => $comapanyId,
            ],
            [
                'id' => 6,
                'name' => 'On Delivery',
                'stage' => 'delivery',
                'hidden' => false,
                'create_uid' => $userId,
                'update_uid' => $userId,
                'branch_id' => $branchId,
                'company_id' => $comapanyId,
            ],
            [
                'id' => 7,
                'name' => 'Pending',
                'stage' => 'pick',
                'hidden' => true,
                'create_uid' => $userId,
                'update_uid' => $userId,
                'branch_id' => $branchId,
                'company_id' => $comapanyId,
            ],
            [
                'id' => 8,
                'name' => 'Delayed',
                'stage' => 'delivery',
                'hidden' => false,
                'create_uid' => $userId,
                'update_uid' => $userId,
                'branch_id' => $branchId,
                'company_id' => $comapanyId,
            ],
            [
                'id' => 9,
                'name' => 'Delivered',
                'stage' => 'delivery',
                'hidden' => false,
                'create_uid' => $userId,
                'update_uid' => $userId,
                'branch_id' => $branchId,
                'company_id' => $comapanyId,
            ],
            [
                'id' => 10,
                'name' => 'Failed',
                'stage' => 'delivery',
                'hidden' => false,
                'create_uid' => $userId,
                'update_uid' => $userId,
                'branch_id' => $branchId,
                'company_id' => $comapanyId,
            ],
            [
                'id' => 11,
                'name' => 'Returning',
                'stage' => 'delivery',
                'hidden' => false,
                'create_uid' => $userId,
                'update_uid' => $userId,
                'branch_id' => $branchId,
                'company_id' => $comapanyId,
            ],
            [
                'id' => 12,
                'name' => 'Pending',
                'stage' => 'delivery',
                'hidden' => false,
                'create_uid' => $userId,
                'update_uid' => $userId,
                'branch_id' => $branchId,
                'company_id' => $comapanyId,
            ],
            [
                'id' => 13,
                'name' => 'Accepted for Pickup',
                'stage' => 'delivery',
                'hidden' => false,
                'create_uid' => $userId,
                'update_uid' => $userId,
                'branch_id' => $branchId,
                'company_id' => $comapanyId,
            ],
            [
                'id' => 14,
                'name' => 'On Delivery',
                'stage' => 'fleet',
                'hidden' => false,
                'create_uid' => $userId,
                'update_uid' => $userId,
                'branch_id' => $branchId,
                'company_id' => $comapanyId,
            ],
            [
                'id' => 15,
                'name' => 'All Completed',
                'stage' => 'fleet',
                'hidden' => false,
                'create_uid' => $userId,
                'update_uid' => $userId,
                'branch_id' => $branchId,
                'company_id' => $comapanyId,
            ],
            [
                'id' => 16,
                'name' => 'Done',
                'stage' => 'fleet',
                'hidden' => false,
                'create_uid' => $userId,
                'update_uid' => $userId,
                'branch_id' => $branchId,
                'company_id' => $comapanyId,
            ],
            [
                'id' => 17,
                'name' => 'Failed',
                'stage' => 'fleet',
                'hidden' => false,
                'create_uid' => $userId,
                'update_uid' => $userId,
                'branch_id' => $branchId,
                'company_id' => $comapanyId,
            ],
            [
                'id' => 18,
                'name' => 'Canceled',
                'stage' => 'delivery',
                'hidden' => false,
                'create_uid' => $userId,
                'update_uid' => $userId,
                'branch_id' => $branchId,
                'company_id' => $comapanyId,
            ],
            [
                'id' => 19,
                'name' => 'Failed With Fee',
                'stage' => 'delivery',
                'hidden' => false,
                'create_uid' => $userId,
                'update_uid' => $userId,
                'branch_id' => $branchId,
                'company_id' => $comapanyId,
            ],
            [
                'id' => 20,
                'name' => 'Canceled',
                'stage' => 'pick',
                'hidden' => false,
                'create_uid' => $userId,
                'update_uid' => $userId,
                'branch_id' => $branchId,
                'company_id' => $comapanyId,
            ],
            [
                'id' => 21,
                'name' => 'Dropped',
                'stage' => 'pick',
                'hidden' => false,
                'create_uid' => $userId,
                'update_uid' => $userId,
                'branch_id' => $branchId,
                'company_id' => $comapanyId,
            ],
            [
                'id' => 22,
                'name' => 'In Transit',
                'stage' => 'transfer',
                'hidden' => false,
                'create_uid' => $userId,
                'update_uid' => $userId,
                'branch_id' => $branchId,
                'company_id' => $comapanyId,
            ],
            [
                'id' => 23,
                'name' => 'Returned',
                'stage' => 'delivery',
                'hidden' => false,
                'create_uid' => $userId,
                'update_uid' => $userId,
                'branch_id' => $branchId,
                'company_id' => $comapanyId,
            ],
        ]);

        //_______
        //** Create vehicle types */

        DB::table('vehicle_types')->insert([
            [
                'name_en' => 'Motor',
                'create_uid' => $userId,
                'update_uid' => $userId,
                'branch_id' => $branchId,
                'company_id' => $comapanyId,
            ]
        ]);

        //** add default country and cities */
        $countryId = DB::table('countries')->insertGetId([
            'name_en' => 'Cambodia',
            'create_uid' => $userId,
            'update_uid' => $userId,
            'branch_id' => $branchId,
            'company_id' => $comapanyId,
        ]);

        foreach($this->cambodiaCities as $city){
            $cityId = DB::table('cities')->insertGetId([
                'country_id' => $countryId,
                'name_en' => $city,
                'create_uid' => $userId,
                'update_uid' => $userId,
                'branch_id' => $branchId,
                'company_id' => $comapanyId,
            ]);
            if($city == 'Phnom Penh'){
                foreach($this->phnomPenhDistricts as $district){
                    DB::table('districts')->insert([
                        'city_id' => $cityId,
                        'name_en' => $district,
                        'create_uid' => $userId,
                        'update_uid' => $userId,
                        'branch_id' => $branchId,
                        'company_id' => $comapanyId,
                    ]);
                }
            }
        }

        //** Add Default Zone  */
        $zoneId = DB::table('zones')->insertGetId([
            'id' => 300,
            'zone_type' => 'local',
            'zone_code' => 'Def',
            'zone_name' => 'Default Zone',
            'district' => 'Chroy Changvar',
            'city' => 'Phnom Penh',
            'country_id' => $countryId,
            'description' => 'description',
            'create_uid' => $userId,
            'update_uid' => $userId,
            'branch_id' => $branchId,
            'company_id' => $comapanyId,
        ]);

        $chZoneId = DB::table('zones')->insertGetId([
            'parent_id' => 300,
            'zone_type' => 'local',
            'zone_code' => 'TC',
            'zone_name' => 'Test Child',
            'identity' => 'child',
            'district' => 'Chroy Changvar',
            'city' => 'Phnom Penh',
            'country_id' => $countryId,
            'description' => 'description',
            'create_uid' => $userId,
            'update_uid' => $userId,
            'branch_id' => $branchId,
            'company_id' => $comapanyId,
        ]);

        $priceListNameId = DB::table('price_list_names')->insertGetId([
            'id' => 30,
            'name' => 'Default',
            'kg_marker' => 3,
            'create_uid' => $userId,
            'update_uid' => $userId,
            'branch_id' => $branchId,
            'company_id' => $comapanyId,
        ]);

        $priceListId = DB::table('price_list')->insertGetId([
            'price_list_name_id' => $priceListNameId,
            'base_fee' => 1.25,
            'below_kg' => 3,
            'above_kg' => 3,
            'create_uid' => $userId,
            'update_uid' => $userId,
            'branch_id' => $branchId,
            'company_id' => $comapanyId,
        ]);

        DB::table('price_list_zones')->insert([
            'price_list_id' => $priceListId,
            'zone_id' => $zoneId,
            'identifier' => 007,
            'base_fee'=> 1.25
        ]);

         DB::table('business_types')->insert([
            [
                'name_en' => 'Cosmetics',
                'hidden' => false,
                'create_uid' => $userId,
                'update_uid' => $userId,
                'branch_id' => $branchId,
                'company_id' => $comapanyId,
            ],
            [
                'name_en' => 'Foods and Suplements',
                'hidden' => false,
                'create_uid' => $userId,
                'update_uid' => $userId,
                'branch_id' => $branchId,
                'company_id' => $comapanyId,
            ],
            [
                'name_en' => 'Foods and Beverage',
                'hidden' => false,
                'create_uid' => $userId,
                'update_uid' => $userId,
                'branch_id' => $branchId,
                'company_id' => $comapanyId,
            ],
            [
                'name_en' => 'Fashion and Clothing',
                'hidden' => false,
                'create_uid' => $userId,
                'update_uid' => $userId,
                'branch_id' => $branchId,
                'company_id' => $comapanyId,
            ],
            [
                'name_en' => 'Eletronics',
                'hidden' => false,
                'create_uid' => $userId,
                'update_uid' => $userId,
                'branch_id' => $branchId,
                'company_id' => $comapanyId,
            ],
            [
                'name_en' => 'Phone and Accessories',
                'hidden' => false,
                'create_uid' => $userId,
                'update_uid' => $userId,
                'branch_id' => $branchId,
                'company_id' => $comapanyId,
            ],
            [
                'name_en' => 'Automotive',
                'hidden' => false,
                'create_uid' => $userId,
                'update_uid' => $userId,
                'branch_id' => $branchId,
                'company_id' => $comapanyId,
            ],
        ]);

        DB::table('client_types')->insert([
            [
                'name_en' => 'Vip',
                'create_uid' => $userId,
                'update_uid' => $userId,
                'branch_id' => $branchId,
                'company_id' => $comapanyId,
            ],
            [
                'name_en' => 'Normal',
                'create_uid' => $userId,
                'update_uid' => $userId,
                'branch_id' => $branchId,
                'company_id' => $comapanyId,
            ]
        ]);
    }

}
