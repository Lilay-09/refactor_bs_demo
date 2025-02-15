<?php

namespace App\Http\Middleware;

use ApiResponse;
use App\Models\User;
use App\Services\UserService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Facades\JWTFactory;

class JwtAuthMiddleware
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
        $accessToken = $cookies['access_token'] ?? null;
        $refreshToken = $cookies['refresh_token'] ?? null;
        try {
            if ($request->headers && $accessToken) {
                // If no valid token in headers, try to get the token from the cookies
                $token = JWTAuth::setToken($accessToken)->authenticate();
            }

            else {
                $token = JWTAuth::parseToken()->authenticate();
            }
            $hasUser = UserService::getUserAuthAccess();

            if(!$hasUser->error){
                $payload = JWTAuth::getPayload($token);
                $payloadArr = $payload->toArray();
                if($payloadArr['type'] == 'refresh') return ApiResponse::Unauthorized('Invalid Token');
                if($payloadArr['system_admin'] === 0) return response()->json([
                    'status_code' => 403,
                    'status' => 'Invalid Token',
                    'error_message' => 'Access Denied',
                    'errors' => []
                ],403);
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
                return $response; // Return the CheckDeleteAndSuperAdmin's response
            }
            // return $next($request);
        } catch (TokenInvalidException $e) {
            // Token is invalid
            return ApiResponse::Unauthorized('Token is invalid');
        } catch (TokenExpiredException $e) {
            return ApiResponse::Unauthorized('Token has expired');
        } catch (JWTException $e) {
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
