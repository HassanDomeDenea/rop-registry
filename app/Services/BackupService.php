<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use ZipArchive;

/**
 * Creates, lists, prunes and restores backup archives. An archive holds a consistent
 * copy of the SQLite database and, for full backups, every patient attachment.
 */
class BackupService
{
    protected const NAME_PATTERN = '/^rop-(full|database|auto|safety)-\d{8}-\d{6}\.zip$/';

    public function directory(): string
    {
        $directory = Setting::read('backup_path') ?? (string) config('registry.backup.path');

        File::ensureDirectoryExists($directory);

        return $directory;
    }

    /**
     * Create a backup archive and return its file name.
     *
     * @param  'full'|'database'|'auto'|'safety'  $type
     */
    public function create(string $type = 'full'): string
    {
        if (DB::connection()->getDriverName() !== 'sqlite') {
            throw new RuntimeException('Backups are only supported for the SQLite database driver.');
        }

        $directory = $this->directory();
        $name = sprintf('rop-%s-%s.zip', $type, now()->format('Ymd-His'));
        $snapshot = $directory.DIRECTORY_SEPARATOR.'snapshot-'.uniqid().'.sqlite';

        DB::statement("VACUUM INTO '".str_replace("'", "''", $snapshot)."'");

        try {
            $zip = new ZipArchive;

            if ($zip->open($directory.DIRECTORY_SEPARATOR.$name, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new RuntimeException('The backup archive could not be created.');
            }

            $zip->addFile($snapshot, 'database.sqlite');

            $attachments = 0;

            if (in_array($type, ['full', 'safety'], true)) {
                $attachments = $this->addAttachments($zip);
            }

            $zip->addFromString('manifest.json', (string) json_encode([
                'application' => config('app.name'),
                'type' => $type,
                'created_at' => now()->toIso8601String(),
                'attachments' => $attachments,
                'includes_attachments' => in_array($type, ['full', 'safety'], true),
            ], JSON_PRETTY_PRINT));

            $zip->close();
        } finally {
            File::delete($snapshot);
        }

        $this->prune();

        return $name;
    }

    protected function addAttachments(ZipArchive $zip): int
    {
        $disk = Storage::disk((string) config('registry.attachments.disk'));
        $count = 0;

        foreach ($disk->allFiles('attachments') as $file) {
            $zip->addFile($disk->path($file), $file);
            $count++;
        }

        return $count;
    }

    /**
     * @return list<array{name: string, type: string, size: int, created_at: string}>
     */
    public function all(): array
    {
        $backups = [];

        foreach (File::files($this->directory()) as $file) {
            if (preg_match(self::NAME_PATTERN, $file->getFilename()) !== 1) {
                continue;
            }

            $backups[] = [
                'name' => $file->getFilename(),
                'type' => explode('-', $file->getFilename())[1],
                'size' => $file->getSize() ?: 0,
                'created_at' => Carbon::createFromTimestamp($file->getMTime())->toIso8601String(),
            ];
        }

        // The timestamp follows the type in the name, so the newest archive is sorted by date, not by type.
        usort($backups, fn (array $a, array $b): int => strcmp(substr($b['name'], -19), substr($a['name'], -19)));

        return $backups;
    }

    /**
     * Resolve the absolute path of a backup, rejecting anything that is not a backup file name.
     */
    public function path(string $name): string
    {
        if (preg_match(self::NAME_PATTERN, $name) !== 1) {
            abort(404);
        }

        $path = $this->directory().DIRECTORY_SEPARATOR.$name;

        abort_unless(File::exists($path), 404);

        return $path;
    }

    public function delete(string $name): void
    {
        File::delete($this->path($name));
    }

    /**
     * Whether archives are built by a queued job instead of during the request.
     */
    public function runsInBackground(): bool
    {
        return false;
    }

    public function download(string $name): Response
    {
        return response()->download($this->path($name));
    }

    /**
     * Get the path of a backup on this computer, or null when there is no such backup.
     */
    public function localCopy(string $name): ?string
    {
        $path = $this->directory().DIRECTORY_SEPARATOR.$name;

        return File::exists($path) ? $path : null;
    }

    /**
     * Keep only the most recent automatic archives and, separately, the most recent manual ones.
     */
    public function prune(): void
    {
        $keep = max(1, (int) config('registry.backup.keep'));

        collect($this->all())
            ->groupBy(fn (array $backup): string => $backup['type'] === 'auto' ? 'auto' : 'manual')
            ->each(fn ($backups) => $backups
                ->slice($keep)
                ->each(fn (array $backup) => File::delete($this->directory().DIRECTORY_SEPARATOR.$backup['name'])));
    }

    /**
     * Determine whether the archive holds the attachments as well as the database.
     */
    public function includesAttachments(string $type): bool
    {
        return in_array($type, ['full', 'safety'], true);
    }

    /**
     * Create the daily automatic database backup when the previous one is old enough.
     */
    public function createAutomaticBackupIfDue(): ?string
    {
        $latest = collect($this->all())->first();
        $hours = (int) config('registry.backup.auto_interval_hours');

        if ($latest !== null && Carbon::parse($latest['created_at'])->gt(now()->subHours($hours))) {
            return null;
        }

        return $this->create('auto');
    }

    /**
     * Replace the database (and attachments, when present) with the contents of an archive.
     * A safety backup of the current state is created first and its name returned.
     */
    public function restore(string $archivePath): string
    {
        $zip = new ZipArchive;

        if ($zip->open($archivePath) !== true || $zip->locateName('database.sqlite') === false) {
            throw new RuntimeException('The file is not a valid registry backup archive.');
        }

        $safety = $this->create('safety');
        $temporary = storage_path('app/restore-'.uniqid());

        try {
            $zip->extractTo($temporary);
            $zip->close();

            DB::disconnect();

            $database = (string) DB::connection()->getConfig('database');

            File::delete([$database.'-wal', $database.'-shm']);
            File::copy($temporary.DIRECTORY_SEPARATOR.'database.sqlite', $database);

            if (File::isDirectory($temporary.DIRECTORY_SEPARATOR.'attachments')) {
                $target = Storage::disk((string) config('registry.attachments.disk'))->path('attachments');

                File::deleteDirectory($target);
                File::copyDirectory($temporary.DIRECTORY_SEPARATOR.'attachments', $target);
            }
        } finally {
            File::deleteDirectory($temporary);
        }

        // A backup made by an older version of the application is brought up to the current schema.
        DB::reconnect();
        Artisan::call('migrate', ['--force' => true]);

        return $safety;
    }
}
