<?php

namespace App\Http\Middleware;

use ApiResponse;
use App\Models\UserPermission;
use App\Services\AppSetting;
use Closure;
use DataResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class UserAccess
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth()->user();
        if ($user['system_admin']) {
            return $next($request);
        }

        $validPermission = $this->checkPermission($request,$user['id']);
        if($validPermission->error) return ApiResponse::flex($validPermission);
        return $next($request);
    }

    private function checkPermission($req,$userId){
        // $userPermissions = UserPermission::where('user_id',$userId)->get();
        // if($systemAdmin) {
        //     return DataResponse::JsonResult(null);
        // }
        // $uri = Route::getCurrentRoute()->uri();
        // $lastPrefixSegment = basename(Route::getCurrentRoute()->getPrefix());
        $route = $req->route();
        $uri = $route->uri();
        $prefix = $route->getPrefix();
        $lastPrefixSegment = basename($prefix);

        $method = $req->method();
        $code = AppSetting::getCodeByURI($uri,$method,$lastPrefixSegment);
        if($method =='GET' && in_array($uri,AppSetting::protectedRoutes())){
            if(!$this->checkPermissionCode($userId,$code)) return DataResponse::Forbidden();
        }

        if(in_array($method,['POST', 'PUT','DELETE'])){
            if(!$this->checkPermissionCode($userId,$code)) {
                return DataResponse::Forbidden();
            }
        }
        return DataResponse::JsonResult(null);
    }

    private function checkPermissionCode($userId, $code): bool
    {
        $permissions = Cache::remember("user_permissions_{$userId}", 600, function () use ($userId) {
            return UserPermission::where('user_id', $userId)
                ->pluck('permission_id') // or 'permission_code' if you're using strings
                ->toArray();
        });

        return in_array($code, $permissions);
    }


    // private function checkPermissionCode($userId,$code){
    //     return Cache::remember('user_permission_'.$userId,60,function () use($userId,$code){
    //         return UserPermission::where('permission_id',$code)->where('user_id',$userId)->value('permission_id');
    //     });
    // }

    // private function checkGetRoute(){

    // }

    // private function checkModules(){

    // }
}
