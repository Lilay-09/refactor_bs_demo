<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Log;
use RateLimiter;
use Str;
use Symfony\Component\HttpFoundation\Response;

class CustomRateLimit
{
    public function handle(Request $request, Closure $next): Response
    {
        // Build a unique key (user ID if logged in, otherwise IP)
        if ($request->user()) {
            $identifier = 'user:'.$request->user()->id;
        } else {
            $ip = $request->ip();
            $uaHash = substr(md5($request->userAgent() ?? 'unknown'), 0, 8);
            $identifier = "anon:{$ip}:{$uaHash}";
        }

        // 3) Scope per route (by name or path)
        $routeName = $request->route()?->getName() ?? $request->path();
        $key = 'rate_limit:'.Str::slug($routeName).':'.$identifier;
        // Define limit per user/route
        [$maxAttempts, $decaySeconds] = $this->resolveRateLimit($request,$key);

        // Check rate limit

        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            return response()->json([
                'error' => true,
                'message' => 'Too many requests. Please wait before retrying.'
            ], 429);
        }
        RateLimiter::hit($key, $decaySeconds);

        // Add RateLimit headers
        $response = $next($request);
        $remaining = RateLimiter::remaining($key, $maxAttempts);

        return $response->header('X-RateLimit-Limit', $maxAttempts)
                        ->header('X-RateLimit-Remaining', $remaining);
    }

    /**
     * Customize rate limits based on route/user/role
     */
    private function resolveRateLimit(Request $request,string $key): array
    {
        $user = $request->user();

        // Example: higher limits for premium users
        if ($user && $user->account_type === 'admin') {
            return [300, 60]; // 300 requests per minute
        }

        // Example: lower limit on auth routes
        if ($request->is('api/admin/v1/auth/login') || $request->is('api/register')) {
            return [10, 60]; // 10 requests per minute
        }
        return [100, 60]; // Default: 100 requests per minute
    }
}
