<?php

namespace App\Http\Middleware;

use App\Services\BackupService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class CreateAutomaticBackup
{
    public function __construct(protected BackupService $backups) {}

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    /**
     * Create the daily database backup after the response has been sent.
     */
    public function terminate(Request $request, Response $response): void
    {
        if ($request->user() === null || ! Cache::add('registry:automatic-backup-check', true, now()->addHour())) {
            return;
        }

        try {
            $this->backups->createAutomaticBackupIfDue();
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
