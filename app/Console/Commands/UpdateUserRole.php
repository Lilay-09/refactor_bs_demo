<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\UserRoles;
use App\Services\AppSetting;
use Illuminate\Console\Command;

class UpdateUserRole extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'update:user-role';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // $existUserId = UserRoles::pluck('user_id')->toArray();
        $users = User::where('is_deleted',0)->selectRaw('id,account_type')->orderBy('id')->get();
        foreach($users as $u){
            UserRoles::where('user_id',$u->id)->delete();
            if($u->account_type == 'driver'){
                $assigned = UserRoles::where('user_id', $u->id)->where('role_id', 2)->first();
                if(!$assigned) UserRoles::create(['user_id' => $u->id, 'role_id' =>2]);
            }else if($u->account_type == 'merchant'){
                $assigned = UserRoles::where('user_id', $u->id)->where('role_id', 3)->first();
                if(!$assigned) UserRoles::create(['user_id' => $u->id, 'role_id' =>3]);
            }else if($u->account_type == 'admin'){
                $assigned = UserRoles::where('user_id', $u->id)->where('role_id', 1)->first();
                if(!$assigned) UserRoles::create(['user_id' => $u->id, 'role_id' =>1]);
            }
            $appId = AppSetting::getUserAppId($u->account_type);
            User::where('id',$u->id)->update([
                'app_id' => $appId
            ]);
        }
        $this->info('Role has been assigned');
    }
}
