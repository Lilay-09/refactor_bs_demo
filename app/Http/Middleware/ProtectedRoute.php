<?php

namespace App\Http\Middleware;

use ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Log;
use Symfony\Component\HttpFoundation\Response;

class ProtectedRoute
{

    protected $user;
    public function __construct($user){
        $this->user = $user;
    }
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next)
    {
        // Check if the request method is DELETE
        // if ($request->isMethod('delete')) {
        //     // Assuming you have a user role checking system in place
        //     // Replace 'isSuperAdmin()' with your actual method of checking the Super Admin role
        //     $user = $this->user;
        //     Log::error(json_encode($user));
        //     if (!$user || !$user->system_admin) {
        //         // Return a 403 Forbidden response if the user is not a Super Admin
        //         return ApiResponse::Forbidden('Forbidden: Only Super Admins can delete.');
        //     }
        // }

        return $next($request);
    }
}
