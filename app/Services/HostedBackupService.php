<?php

namespace App\Services;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Database\Connection;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use League\Flysystem\StorageAttributes;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use ZipArchive;

/**
 * Backups for a hosted registry, where the database is a database server and the files
 * live in object storage. The archives are the same as the ones a registry on a single
 * computer writes (an SQLite database plus the attachments), so a backup made on either
 * one can be restored on the other.
 *
 * @phpstan-type BackupTask array{kind: 'backup'|'restore', status: 'running'|'failed', message: string|null, started_at: string}
 */
class HostedBackupService extends BackupService
{
    /**
     * Where the archives are kept on the attachments disk.
     */
    protected const FOLDER = 'backups';

    protected const ARCHIVE_CONNECTION = 'registry_archive';

    /**
     * The disk name written into archives: the one a registry on a single computer uses.
     */
    protected const ARCHIVE_DISK = 'local';

    /**
     * Tables that belong to one running installation and are never carried in an archive.
     */
    protected const TRANSIENT_TABLES = [
        'migrations', 'sqlite_sequence', 'sessions', 'cache', 'cache_locks',
        'jobs', 'job_batches', 'failed_jobs', 'password_reset_tokens',
    ];

    protected const TASK_KEY = 'registry:backup-task';

    public function runsInBackground(): bool
    {
        return true;
    }

    /**
     * Get the local folder in which archives are assembled and unpacked.
     */
    public function directory(): string
    {
        $directory = storage_path('app/backup-work');

        File::ensureDirectoryExists($directory);

        return $directory;
    }

    /**
     * Create a backup archive in storage and return its file name.
     *
     * @param  'full'|'database'|'auto'|'safety'  $type
     */
    public function create(string $type = 'full'): string
    {
        $name = sprintf('rop-%s-%s.zip', $type, now()->format('Ymd-His'));
        $workspace = $this->workspace();

        try {
            $snapshot = $workspace.DIRECTORY_SEPARATOR.'database.sqlite';
            $archive = $workspace.DIRECTORY_SEPARATOR.$name;

            $this->writeSnapshot($snapshot);

            $zip = new ZipArchive;

            if ($zip->open($archive, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new RuntimeException('The backup archive could not be created.');
            }

            $zip->addFile($snapshot, 'database.sqlite');

            $attachments = $this->includesAttachments($type)
                ? $this->addStoredAttachments($zip, $workspace.DIRECTORY_SEPARATOR.'files')
                : 0;

            $zip->addFromString('manifest.json', (string) json_encode([
                'application' => config('app.name'),
                'type' => $type,
                'created_at' => now()->toIso8601String(),
                'attachments' => $attachments,
                'includes_attachments' => $this->includesAttachments($type),
            ], JSON_PRETTY_PRINT));

            $zip->close();

            $this->store($archive, self::FOLDER.'/'.$name);
        } finally {
            File::deleteDirectory($workspace);
        }

        $this->prune();

        return $name;
    }

    /**
     * @return list<array{name: string, type: string, size: int, created_at: string}>
     */
    public function all(): array
    {
        $backups = [];

        /** @var iterable<StorageAttributes> $entries */
        $entries = $this->disk()->listContents(self::FOLDER, false);

        foreach ($entries as $entry) {
            $name = basename($entry->path());

            if (! $entry->isFile() || preg_match(self::NAME_PATTERN, $name) !== 1) {
                continue;
            }

            $backups[] = [
                'name' => $name,
                'type' => explode('-', $name)[1],
                'size' => (int) ($entry['fileSize'] ?? 0),
                'created_at' => Carbon::createFromTimestamp((int) $entry->lastModified())->toIso8601String(),
            ];
        }

        usort($backups, fn (array $a, array $b): int => strcmp(substr($b['name'], -19), substr($a['name'], -19)));

        return $backups;
    }

    /**
     * Resolve the storage path of a backup, rejecting anything that is not a backup file name.
     */
    public function path(string $name): string
    {
        if (preg_match(self::NAME_PATTERN, $name) !== 1) {
            abort(404);
        }

        $path = self::FOLDER.'/'.$name;

        abort_unless($this->disk()->exists($path), 404);

        return $path;
    }

    public function delete(string $name): void
    {
        $this->disk()->delete($this->path($name));
    }

    public function prune(): void
    {
        $keep = max(1, (int) config('registry.backup.keep'));

        collect($this->all())
            ->groupBy(fn (array $backup): string => $backup['type'] === 'auto' ? 'auto' : 'manual')
            ->each(fn ($backups) => $backups
                ->slice($keep)
                ->each(fn (array $backup) => $this->disk()->delete(self::FOLDER.'/'.$backup['name'])));
    }

    /**
     * Send the browser straight to storage, so a large archive does not pass through the application.
     */
    public function download(string $name): Response
    {
        return redirect()->away($this->disk()->temporaryUrl($this->path($name), now()->addMinutes(30), [
            'ResponseContentDisposition' => 'attachment; filename="'.$name.'"',
        ]));
    }

    /**
     * Fetch an archive from storage into the working folder and return where it was put.
     */
    public function localCopy(string $name): ?string
    {
        if (preg_match(self::NAME_PATTERN, $name) !== 1 || ! $this->disk()->exists(self::FOLDER.'/'.$name)) {
            return null;
        }

        $path = $this->directory().DIRECTORY_SEPARATOR.$name;

        $this->fetch(self::FOLDER.'/'.$name, $path);

        return $path;
    }

    /**
     * Get an address the browser can send an archive to directly, and the name it will have in the list.
     * The archive keeps the kind of backup its own name states and is dated now, so it sorts as the newest.
     *
     * @return array{name: string, url: string, headers: array<string, string>}
     */
    public function uploadTarget(string $originalName): array
    {
        $type = preg_match(self::NAME_PATTERN, $originalName, $matches) === 1 ? $matches[1] : 'full';
        $name = sprintf('rop-%s-%s.zip', $type, now()->format('Ymd-His'));

        $target = $this->disk()->temporaryUploadUrl(self::FOLDER.'/'.$name, now()->addHours(6));

        return [
            'name' => $name,
            'url' => (string) $target['url'],
            'headers' => array_map(fn (mixed $value): string => (string) Arr::first(Arr::wrap($value)), $target['headers']),
        ];
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
        $workspace = $this->workspace();

        try {
            $zip->extractTo($workspace);
            $zip->close();

            // A backup made by an older version of the application is brought up to the current schema.
            $this->openArchiveDatabase($workspace.DIRECTORY_SEPARATOR.'database.sqlite');

            Schema::disableForeignKeyConstraints();

            try {
                DB::transaction(fn () => $this->copyTables(
                    DB::connection(self::ARCHIVE_CONNECTION),
                    DB::connection(),
                    (string) config('registry.attachments.disk'),
                ));
            } finally {
                Schema::enableForeignKeyConstraints();
            }

            if (File::isDirectory($workspace.DIRECTORY_SEPARATOR.'attachments')) {
                $this->replaceStoredAttachments($workspace);
            }
        } finally {
            DB::purge(self::ARCHIVE_CONNECTION);
            File::deleteDirectory($workspace);
        }

        return $safety;
    }

    /**
     * Get the backup or restore that is running, or the one that just failed.
     *
     * @return BackupTask|null
     */
    public function task(): ?array
    {
        return Cache::get(self::TASK_KEY);
    }

    /**
     * Mark a backup or restore as running. Returns false while another one is still at work.
     *
     * @param  'backup'|'restore'  $kind
     */
    public function startTask(string $kind): bool
    {
        if (($this->task()['status'] ?? null) === 'running') {
            return false;
        }

        Cache::put(self::TASK_KEY, [
            'kind' => $kind,
            'status' => 'running',
            'message' => null,
            'started_at' => now()->toIso8601String(),
        ], now()->addHours(2));

        return true;
    }

    /**
     * Mark the running backup or restore as finished; a failure stays visible for a while.
     */
    public function finishTask(?string $error = null): void
    {
        $task = $this->task();

        if ($error === null || $task === null) {
            Cache::forget(self::TASK_KEY);

            return;
        }

        Cache::put(self::TASK_KEY, [...$task, 'status' => 'failed', 'message' => $error], now()->addMinutes(30));
    }

    /**
     * Write the registry as an SQLite database, the form every archive carries.
     */
    protected function writeSnapshot(string $path): void
    {
        File::put($path, '');

        try {
            $this->openArchiveDatabase($path);

            $this->copyTables(DB::connection(), DB::connection(self::ARCHIVE_CONNECTION), self::ARCHIVE_DISK);
        } finally {
            DB::purge(self::ARCHIVE_CONNECTION);
        }
    }

    /**
     * Open an SQLite file as a second database connection, at the current schema.
     */
    protected function openArchiveDatabase(string $path): void
    {
        config(['database.connections.'.self::ARCHIVE_CONNECTION => [
            'driver' => 'sqlite',
            'database' => $path,
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]]);

        DB::purge(self::ARCHIVE_CONNECTION);

        Artisan::call('migrate', ['--database' => self::ARCHIVE_CONNECTION, '--force' => true]);
    }

    /**
     * Replace the registry tables of one database with the rows of another.
     * Stored files are recorded under the disk of the receiving side.
     */
    protected function copyTables(Connection $from, Connection $to, string $disk): void
    {
        $tables = array_diff(
            array_intersect($this->tables($from), $this->tables($to)),
            self::TRANSIENT_TABLES,
        );

        foreach ($tables as $table) {
            $columns = array_values(array_intersect(
                $from->getSchemaBuilder()->getColumnListing($table),
                $to->getSchemaBuilder()->getColumnListing($table),
            ));

            $to->table($table)->delete();

            $rows = [];

            foreach ($from->table($table)->cursor() as $row) {
                $row = Arr::only((array) $row, $columns);

                if (array_key_exists('disk', $row)) {
                    $row['disk'] = $disk;
                }

                $rows[] = $row;

                if (count($rows) === 200) {
                    $to->table($table)->insert($rows);
                    $rows = [];
                }
            }

            if ($rows !== []) {
                $to->table($table)->insert($rows);
            }
        }
    }

    /**
     * @return list<string>
     */
    protected function tables(Connection $connection): array
    {
        return array_column($connection->getSchemaBuilder()->getTables(), 'name');
    }

    /**
     * Bring every stored attachment into the working folder and add it to the archive.
     */
    protected function addStoredAttachments(ZipArchive $zip, string $folder): int
    {
        $count = 0;

        foreach ($this->disk()->allFiles('attachments') as $file) {
            $local = $folder.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $file);

            $this->fetch($file, $local);
            $zip->addFile($local, $file);
            $count++;
        }

        return $count;
    }

    /**
     * Replace the stored attachments with the ones unpacked from an archive.
     */
    protected function replaceStoredAttachments(string $workspace): void
    {
        $this->disk()->deleteDirectory('attachments');

        foreach (File::allFiles($workspace.DIRECTORY_SEPARATOR.'attachments') as $file) {
            $relative = Str::after(str_replace('\\', '/', $file->getPathname()), str_replace('\\', '/', $workspace).'/');

            $this->store($file->getPathname(), $relative);
        }
    }

    protected function store(string $localPath, string $storagePath): void
    {
        $stream = fopen($localPath, 'r');

        if ($stream === false) {
            throw new RuntimeException("The file could not be read: {$localPath}");
        }

        try {
            if (! $this->disk()->writeStream($storagePath, $stream)) {
                throw new RuntimeException("The file could not be stored: {$storagePath}");
            }
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
    }

    protected function fetch(string $storagePath, string $localPath): void
    {
        File::ensureDirectoryExists(dirname($localPath));

        $stream = $this->disk()->readStream($storagePath);

        if ($stream === null || file_put_contents($localPath, $stream) === false) {
            throw new RuntimeException("The file could not be fetched: {$storagePath}");
        }

        fclose($stream);
    }

    protected function workspace(): string
    {
        $workspace = $this->directory().DIRECTORY_SEPARATOR.'work-'.uniqid();

        File::ensureDirectoryExists($workspace);

        return $workspace;
    }

    protected function disk(): Filesystem
    {
        return Storage::disk((string) config('registry.attachments.disk'));
    }
}
