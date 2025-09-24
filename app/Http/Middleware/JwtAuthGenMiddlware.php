<?php

namespace App\Http\Middleware;

use ApiResponse;
use App\Services\UserService;
use Closure;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
use Tymon\JWTAuth\Facades\JWTAuth;

class JwtAuthGenMiddlware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next)
    {
        try {
            if ($request->headers) {
                $authHeader = $request->header('Authorization');
                $getToken = $accessToken ?? str_replace('Bearer ', '', $authHeader);
                $token = JWTAuth::setToken($getToken)->authenticate();
            }
            else {
                $token = JWTAuth::parseToken()->authenticate();
            }
            $hasUser = UserService::getUserAuthAccess($request->role);
            if(!$hasUser->error){
                $payload = JWTAuth::getPayload($token);
                $payloadArr = $payload->toArray();
                if($payloadArr['type'] == 'refresh') return ApiResponse::Unauthorized('Invalid Token');
            }else{
                return ApiResponse::Unauthorized($hasUser->message);
            }
        } catch (TokenInvalidException $e) {
            return ApiResponse::Unauthorized('Token is invalid');
        } catch (TokenExpiredException $e) {
            return ApiResponse::Unauthorized('Token has expired');
        } catch (JWTException $e) {
           return ApiResponse::Unauthorized('Unauthorized');
        }
        return $next($request);
    }
}
