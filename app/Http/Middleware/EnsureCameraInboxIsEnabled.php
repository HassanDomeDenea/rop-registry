<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCameraInboxIsEnabled
{
    /**
     * The camera inbox reads folders of the computer the registry runs on; a hosted registry has none.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless((bool) config('registry.capture.enabled'), 404);

        return $next($request);
    }
}
