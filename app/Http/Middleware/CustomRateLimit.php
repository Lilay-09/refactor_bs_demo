<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class CustomRateLimit
{
    public function handle(Request $request, Closure $next)
    {
        // Build a unique key (user ID if logged in, otherwise IP)
        if ($request->user()) {
            $identifier = 'user:'.$request->user()->id;
        } else {
            $ip = $request->ip();
            $uaHash = substr(md5($request->userAgent() ?? 'unknown'), 0, 8);
            $identifier = "anon:{$ip}:{$uaHash}";
        }

        // Scope per route (by name or path)
        $routeName = $request->route()?->getName() ?? $request->path();
        $key = 'rate_limit:'.Str::slug($routeName).':'.$identifier;

        // Define limit per user/route
        [$maxAttempts, $decaySeconds] = $this->resolveRateLimit($request, $key);

        // Check rate limit
        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            return response()->json([
                'error' => true,
                'message' => 'Too many requests. Please wait before retrying.'
            ], 429);
        }

        RateLimiter::hit($key, $decaySeconds);

        // Continue to next middleware / controller
        $response = $next($request);

        // Add RateLimit headers properly
        $remaining = RateLimiter::remaining($key, $maxAttempts);
        $response->headers->set('X-RateLimit-Remaining', $remaining);

        // Return the response object
        return $response;
    }

    private function resolveRateLimit(Request $request, string $key): array
    {
        $user = $request->user();

        if ($user && $user->account_type === 'admin') {
            return [300, 60]; // 300 requests per minute
        }

        if ($request->is('api/admin/v1/auth/login') || $request->is('api/register')) {
            return [10, 60]; // 10 requests per minute
        }

        return [100, 60]; // Default
    }
}

