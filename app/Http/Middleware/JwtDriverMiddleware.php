<?php

namespace App\Http\Middleware;

use ApiResponse;
use App\Models\User;
use App\Services\UserService;
use Closure;
use Illuminate\Http\Request;
use Log;
use Symfony\Component\HttpFoundation\Response;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Facades\JWTFactory;

class JwtDriverMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next)
    {
        $cookieHeader = $request->header('cookie');
        $cookies = $cookieHeader ? $this->parseCookies($cookieHeader):null;
        $accessToken = null;//$cookies['token'] ?? null;
        $refreshToken = $cookies['refresh_token'] ?? null;
        try {
            if ($request->headers) {
                $authHeader = $request->header('Authorization');
                $getToken = $accessToken ?? str_replace('Bearer ', '', $authHeader);
                $token = JWTAuth::setToken($getToken)->authenticate();
            }
            // else if($refreshToken){
            //     $rfPl = JWTAuth::setToken($refreshToken)->getPayload();
            //     $userId = $rfPl['sub'];

            //     $user = User::find($userId);
            //     if(!$user) return ApiResponse::Unauthorized('ddd');

            //     $newAccessTokenFactory = JWTFactory::customClaims([
            //         'system_admin' => $user->system_admin,
            //         'roles' => $rfPl['roles'],
            //         'exp' => time() + (config('jwt.ttl') * 60),
            //         'type' => 'access'
            //     ]);

            //     $newAccessToken = JWTAuth::encode($newAccessTokenFactory->make())->get();

            //     if ($newAccessToken) {
            //         return $next($request)
            //         ->withCookie(cookie('token', $newAccessToken, 0.5, '/', null, false, true)->withSameSite('None'));
            //         // ->withCookie(cookie('token', $newAccessToken, config('jwt.ttl'), '/', null, true, false)->withSameSite('None'));
            //     }
            // }
            else {
                $token = JWTAuth::parseToken()->authenticate();
            }
            $hasUser = UserService::getAuthUser('driver');

            if(!$hasUser->error){
                $payload = JWTAuth::getPayload($token);
                $payloadArr = $payload->toArray();
                if($payloadArr['type'] == 'refresh') return ApiResponse::Unauthorized('Invalid Token');
                // if($payloadArr['system_admin'] === 0) return response()->json([
                //     'status_code' => 403,
                //     'status' => 'Invalid Token',
                //     'error_message' => 'Access Denied',
                //     'errors' => []
                // ],403);
            }else{
                return ApiResponse::Unauthorized($hasUser->message);
            }
            $checkDeleteAndSuperAdmin = new ProtectedRoute($hasUser);

            // Call the CheckDeleteAndSuperAdmin middleware
            $response = $checkDeleteAndSuperAdmin->handle($request, function ($request) {
                // If CheckDeleteAndSuperAdmin passes, continue to the next middleware
                return $request;
            });

            // If the response is not null, it means CheckDeleteAndSuperAdmin returned a response (like a 403)
            if ($response !== $request) {
                return $response;
            }
        } catch (TokenInvalidException $e) {
            return ApiResponse::Unauthorized('Token is invalid');
        } catch (TokenExpiredException $e) {
            return ApiResponse::Unauthorized('Token has expired');
        } catch (JWTException $e) {
            // Log::error('Authorization not found');
           return ApiResponse::Unauthorized('Unauthorized');
        }
        return $next($request);
    }
    private function parseCookies(string $cookieHeader): array
    {
        $cookies = [];
        $pairs = explode('; ', $cookieHeader);

        foreach ($pairs as $pair) {
            [$key, $value] = explode('=', $pair, 2);
            $cookies[$key] = $value;
        }
        return $cookies;
    }
}
