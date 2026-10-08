<?php

namespace App\Jobs;

use App\Services\HostedBackupService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Throwable;

/**
 * Replaces a hosted registry with the contents of an archive kept in storage.
 */
class RestoreBackup implements ShouldQueue
{
    use Queueable;

    public int $timeout = 3600;

    public int $tries = 1;

    public function __construct(public string $backup) {}

    public function handle(HostedBackupService $backups): void
    {
        $path = $backups->localCopy($this->backup);

        if ($path === null) {
            throw new RuntimeException('The backup is no longer in the list.');
        }

        try {
            $backups->restore($path);
        } finally {
            File::delete($path);
        }

        $backups->finishTask();
    }

    public function failed(?Throwable $exception): void
    {
        app(HostedBackupService::class)->finishTask($exception?->getMessage() ?? 'The restore did not finish.');
    }
}
