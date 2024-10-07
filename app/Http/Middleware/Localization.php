<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;

class Localization
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $locale = $request->route('lang'); // Get the locale from the route parameter
        // \Log::info($locale);
        // Check if the locale is valid
        if (in_array($locale, config('app.supported_locales'))) {
            App::setLocale($locale);
        } else {
            // Fallback to default locale if invalid
            App::setLocale(config('app.fallback_locale'));
        }

        // \Log::info(App::getLocale()); // Log the current locale


        return $next($request);
    }
}
