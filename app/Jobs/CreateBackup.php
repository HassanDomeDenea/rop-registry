<?php

namespace App\Jobs;

use App\Services\HostedBackupService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Builds a backup archive of a hosted registry, where collecting the attachments
 * from storage takes longer than a request may last.
 */
class CreateBackup implements ShouldQueue
{
    use Queueable;

    public int $timeout = 3600;

    public int $tries = 1;

    /**
     * @param  'full'|'database'  $type
     */
    public function __construct(public string $type) {}

    public function handle(HostedBackupService $backups): void
    {
        $backups->create($this->type);
        $backups->finishTask();
    }

    public function failed(?Throwable $exception): void
    {
        app(HostedBackupService::class)->finishTask($exception?->getMessage() ?? 'The backup did not finish.');
    }
}
