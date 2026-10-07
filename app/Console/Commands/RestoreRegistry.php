<?php

namespace App\Console\Commands;

use App\Services\BackupService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

#[Signature('registry:restore {archive : Backup file name, or the full path of a backup archive} {--force : Do not ask for confirmation}')]
#[Description('Replace the registry database and attachments with the contents of a backup archive')]
class RestoreRegistry extends Command
{
    public function handle(BackupService $backups): int
    {
        $archive = (string) $this->argument('archive');
        $path = File::exists($archive) ? $archive : $backups->directory().DIRECTORY_SEPARATOR.basename($archive);

        if (! File::exists($path)) {
            $this->components->error("Backup archive not found: {$path}");

            return self::FAILURE;
        }

        if (! $this->option('force') && ! $this->confirm('This replaces the current registry data with the backup. Continue?')) {
            return self::SUCCESS;
        }

        $safety = $backups->restore($path);

        $this->components->info("Registry restored. The previous state was saved as {$safety}.");

        return self::SUCCESS;
    }
}
