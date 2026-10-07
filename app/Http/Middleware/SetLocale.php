<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /**
     * Apply the language chosen by the user, falling back to the remembered cookie.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->user()->locale ?? $request->cookie('locale');

        if (is_string($locale) && array_key_exists($locale, config('registry.locales'))) {
            App::setLocale($locale);
        }

        return $next($request);
    }
}
