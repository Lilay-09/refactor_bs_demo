<?php

namespace App\Http\Middleware;

use ApiResponse;
use App\Models\UserPermission;
use App\Services\AppSetting;
use Auth;
use Closure;
use DataResponse;
use Illuminate\Http\Request;
use Log;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\Response;

class UserAccess
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next,$class): Response
    {
        $user = Auth::user()->only(['id', 'user_name', 'email','login_name','system_admin']);
        // Log::info(json_encode($user));
        $validPermission = $this->checkPermission($request,$user['id'],$user['system_admin']);
        if($validPermission->error) return ApiResponse::flex($validPermission);
        return $next($request);
    }

    private function checkPermission($req,$userId,$systemAdmin){
        // $userPermissions = UserPermission::where('user_id',$userId)->get();
        if($systemAdmin) return DataResponse::JsonResult(null);
        $uri = Route::getCurrentRoute()->uri();
        // Get the last part of the prefix (the last segment)
        $lastPrefixSegment = basename(Route::getCurrentRoute()->getPrefix());
        $method = $req->method();
        Log::info($lastPrefixSegment.' => '.$method);
        if(in_array($method,['POST', 'PUT','DELETE'])){
            $code = AppSetting::getCodeByURI($uri,$method,$lastPrefixSegment);
            Log::info($uri.'=>'.$code);
            if(!$this->checkPermissionCode($userId,$code)) return DataResponse::Forbidden();
        }
        // if($method === 'PUT') if(!$this->checkPermissionCode($userId,[201,202])) return DataResponse::Forbidden();

        Log::info($uri);
        // if(!isset($userPermissions[0])) {
        //     if(!$systemAdmin) return DataResponse::Forbidden();
        // }
        return DataResponse::JsonResult(null);
    }

    private function checkPermissionCode($userId,$code){
        return UserPermission::where('permission_id',$code)->where('user_id',$userId)->value('permission_id');
    }

    private function checkModules(){

    }
}
