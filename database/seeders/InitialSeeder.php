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
            'first_name' => 'JS',
            'last_name' => 'ADMIN',
            'user_name' => 'JS Admin',
            'phone' => '092335554',
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

        //** Create Driver */

        $driverId = DB::table('users')->insertGetId([
            'first_name' => 'Driver',
            'last_name' => '',
            'user_name' => 'Driver',
            'phone' => '092233445',
            'email' => 'driver@gmail.com',
            'account_type' => 'driver',
            'gender' => 'M',
            'vehicle_type' => 'Motorbike',
            'password' => \Hash::make('123456'),
            'create_uid' => $userId,
            'update_uid' => $userId,
            'branch_id'=> $branchId,
            'shift_type' => 'full-time',
            'start_time' => '8:00',
            'end_time' => '17:00',
            'company_id' => $comapanyId
        ]);

        DB::table('users')->where('id',$driverId)->update([
            'code' => Helper::generateCode('JSD',$driverId,'')
        ]);



        //** Create Merchant */

        $merchantId = DB::table('users')->insertGetId([
            'first_name' => 'Merchant',
            'last_name' => '',
            'user_name' => 'Merchant',
            'phone' => '012465653',
            'email' => 'merchant@gmail.com',
            'account_type' => 'merchant',
            'gender' => 'M',
            'password' => \Hash::make('123456'),
            'create_uid' => $userId,
            'update_uid' => $userId,
            'branch_id'=> $branchId,
            'company_id' => $comapanyId
        ]);

        DB::table('users')->where('id',$merchantId)->update([
            'code' => Helper::generateCode('JSD',$merchantId,'')
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
        $userCode = Helper::generateCode('JSA',$userId,'');
        $updateUser = DB::table('users')->where('id',$userId)->update([
            'code' => $userCode,
            'create_uid' => $userId,
            'update_uid' => $userId,
            'branch_id' => $branchId,
            'company_id' => $comapanyId,
        ]);

        //warehouse
        DB::table('warehouses')->insert([
            'name' => 'Main Warehosue',
            'company_id' => $comapanyId,
            'branch_id' => $branchId
        ]);

        DB::table('tracking_statuses')->insert([
            [
                'id' => 1,
                'name' => 'Available For Pick',
                'stage' => 'pick',
                'create_uid' => $userId,
                'update_uid' => $userId,
                'branch_id' => $branchId,
                'company_id' => $comapanyId,
            ],
            [
                'id' => 2,
                'name' => 'Picked',
                'stage' => 'pick',
                'create_uid' => $userId,
                'update_uid' => $userId,
                'branch_id' => $branchId,
                'company_id' => $comapanyId,
            ],
            [
                'id' => 3,
                'name' => 'Accepted For Pickup',
                'stage' => 'pick',
                'create_uid' => $userId,
                'update_uid' => $userId,
                'branch_id' => $branchId,
                'company_id' => $comapanyId,
            ],
            [
                'id' => 4,
                'name' => 'Picked And Booked',
                'stage' => 'pick',
                'create_uid' => $userId,
                'update_uid' => $userId,
                'branch_id' => $branchId,
                'company_id' => $comapanyId,
            ],
            [
                'id' => 5,
                'name' => 'At Warehouse',
                'stage' => 'delivery',
                'create_uid' => $userId,
                'update_uid' => $userId,
                'branch_id' => $branchId,
                'company_id' => $comapanyId,
            ],
            [
                'id' => 6,
                'name' => 'On Delivery',
                'stage' => 'delivery',
                'create_uid' => $userId,
                'update_uid' => $userId,
                'branch_id' => $branchId,
                'company_id' => $comapanyId,
            ],
            [
                'id' => 7,
                'name' => 'Delayed',
                'stage' => 'delivery',
                'create_uid' => $userId,
                'update_uid' => $userId,
                'branch_id' => $branchId,
                'company_id' => $comapanyId,
            ],
            [
                'id' => 8,
                'name' => 'Delivered',
                'stage' => 'delivery',
                'create_uid' => $userId,
                'update_uid' => $userId,
                'branch_id' => $branchId,
                'company_id' => $comapanyId,
            ],
            [
                'id' => 9,
                'name' => 'Failed',
                'stage' => 'delivery',
                'create_uid' => $userId,
                'update_uid' => $userId,
                'branch_id' => $branchId,
                'company_id' => $comapanyId,
            ],
            [
                'id' => 10,
                'name' => 'Returned',
                'stage' => 'delivery',
                'create_uid' => $userId,
                'update_uid' => $userId,
                'branch_id' => $branchId,
                'company_id' => $comapanyId,
            ],
            [
                'id' => 11,
                'name' => 'Canceled',
                'stage' => 'pick',
                'create_uid' => $userId,
                'update_uid' => $userId,
                'branch_id' => $branchId,
                'company_id' => $comapanyId,
            ]
        ]);


        DB::table('vehicle_types')->insert([
            [
                'name' => 'Van',
                'create_uid' => $userId,
                'update_uid' => $userId,
                'branch_id' => $branchId,
                'company_id' => $comapanyId,
            ],
            [
                'name' => 'Tuk Tuk',
                'create_uid' => $userId,
                'update_uid' => $userId,
                'branch_id' => $branchId,
                'company_id' => $comapanyId,
            ],
            [
                'name' => 'Motor',
                'create_uid' => $userId,
                'update_uid' => $userId,
                'branch_id' => $branchId,
                'company_id' => $comapanyId,
            ],
            [
                'name' => 'Bike cycle',
                'create_uid' => $userId,
                'update_uid' => $userId,
                'branch_id' => $branchId,
                'company_id' => $comapanyId,
            ]
        ]);

        //** add default country and cities */
        $countryId = DB::table('countries')->insertGetId([
            'name' => 'Cambodia',
            'create_uid' => $userId,
            'update_uid' => $userId,
            'branch_id' => $branchId,
            'company_id' => $comapanyId,
        ]);

        foreach($this->cambodiaCities as $city){
            $cityId = DB::table('cities')->insertGetId([
                'country_id' => $countryId,
                'name' => $city,
                'create_uid' => $userId,
                'update_uid' => $userId,
                'branch_id' => $branchId,
                'company_id' => $comapanyId,
            ]);
            if($city == 'Phnom Penh'){
                foreach($this->phnomPenhDistricts as $district){
                    DB::table('districts')->insert([
                        'city_id' => $cityId,
                        'name' => $district,
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
            'zone_type' => 'local',
            'zone_code' => 'C1',
            'zone_name' => 'កោះពេជ្រ',
            'district' => 'Chroy Changvar',
            'city' => 'Phnom Penh',
            'country_id' => $countryId,
            'desctiption' => 'description',
            'create_uid' => $userId,
            'update_uid' => $userId,
            'branch_id' => $branchId,
            'company_id' => $comapanyId,
        ]);

        $priceListId = DB::table('price_lists')->insertGetId([
            // 'price' => 0,
            'create_uid' => $userId,
            'update_uid' => $userId,
            'branch_id' => $branchId,
            'company_id' => $comapanyId,
        ]);

        DB::table('price_list_zones')->insert([
            'price_list_id' => $priceListId,
            'zone_id' => $zoneId
        ]);
    }
}
