<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /**
     * Apply the language chosen by the user, falling back to the remembered cookie.
     * Printed reports use their own language when one is set.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->user()->locale ?? $request->cookie('locale');

        if ($request->routeIs('patients.print', 'patients.visits.print')) {
            $locale = Setting::read('print_locale') ?? $locale;
        }

        if (is_string($locale) && array_key_exists($locale, config('registry.locales'))) {
            App::setLocale($locale);
        }

        return $next($request);
    }
}
