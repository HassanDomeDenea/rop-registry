<?php

namespace App\Services;

use App\Models\Attachment;
use App\Models\Capture;
use App\Models\Patient;
use App\Models\Setting;
use App\Models\Visit;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Receives images from the camera, either as file paths handed over by the virtual
 * printer or as files exported into a watched folder, and keeps them in an inbox
 * until they are assigned to a patient.
 */
class CaptureService
{
    /**
     * Where the files wait. It lies inside the attachments folder, so full backups include it.
     */
    protected const INBOX = 'attachments/_inbox';

    /**
     * A file that changed this recently may still be written by the camera software.
     */
    protected const SETTLE_SECONDS = 3;

    /**
     * Exported files that arrive within this time of each other belong to one batch.
     */
    protected const BATCH_GAP_MINUTES = 2;

    /**
     * Get the folder the camera software exports into, when one is set.
     */
    public function folder(): ?string
    {
        return Setting::read('capture_folder', config('registry.capture.folder'));
    }

    /**
     * Take over the given files, and the files inside the given folders, as one batch.
     * A source file is removed only after its copy is safely in the inbox.
     *
     * @param  list<string>  $paths
     * @return array{received: int, duplicates: int, skipped: list<string>, assigned_to: string|null}
     */
    public function ingest(array $paths, string $source, bool $keepOriginals = false): array
    {
        $result = ['received' => 0, 'duplicates' => 0, 'skipped' => [], 'assigned_to' => null];
        $batch = (string) Str::uuid();
        $received = [];

        foreach ($this->expand($paths) as $file) {
            $outcome = $this->take($file, $batch, $source, $keepOriginals);

            if ($outcome instanceof Capture) {
                $received[] = $outcome;
                $result['received']++;
            } elseif ($outcome === 'duplicate') {
                $result['duplicates']++;
            } else {
                $result['skipped'][] = $file;
            }
        }

        $result['assigned_to'] = $this->deliverToTarget($received);

        return $result;
    }

    /**
     * Take over the files that are ready in the export folder.
     */
    public function sweep(): int
    {
        $folder = $this->folder();

        if ($folder === null || ! File::isDirectory($folder)) {
            return 0;
        }

        $batch = $this->openExportBatch();
        $received = [];

        foreach ($this->expand([$folder]) as $file) {
            if (time() - (int) @filemtime($file) < self::SETTLE_SECONDS) {
                continue;
            }

            $outcome = $this->take($file, $batch, 'export', false);

            if ($outcome instanceof Capture) {
                $received[] = $outcome;
            }
        }

        $this->deliverToTarget($received);

        return count($received);
    }

    /**
     * Move the captures into the attachments of the patient, on the visit of today when there is one.
     *
     * @param  iterable<Capture>  $captures
     */
    public function assign(iterable $captures, Patient $patient, bool $toVisitOfToday = true): ?Visit
    {
        $visit = $toVisitOfToday
            ? $patient->visits()->whereDate('visit_date', Carbon::today())->latest('id')->first()
            : null;

        foreach ($captures as $capture) {
            $disk = Storage::disk($capture->disk);
            $path = "attachments/{$patient->id}/".basename($capture->path);

            if (! $disk->exists($capture->path) || ! $disk->move($capture->path, $path)) {
                continue;
            }

            $patient->attachments()->create([
                'visit_id' => $visit?->id,
                'disk' => $capture->disk,
                'path' => $path,
                'original_name' => $capture->original_name,
                'mime_type' => $capture->mime_type,
                'size' => $capture->size,
                'hash' => $capture->hash,
                'caption' => __('Camera').' · '.$capture->created_at?->format('Y-m-d H:i'),
            ]);

            $capture->delete();
        }

        return $visit;
    }

    /**
     * Remove a capture and its file for good.
     */
    public function discard(Capture $capture): void
    {
        Storage::disk($capture->disk)->delete($capture->path);
        $capture->delete();
    }

    /**
     * Send the images that arrive in the next minutes straight to this patient.
     */
    public function receiveFor(Patient $patient): void
    {
        Setting::write('capture_target', (string) json_encode([
            'patient_id' => $patient->id,
            'until' => now()->addMinutes((int) config('registry.capture.receive_minutes'))->toIso8601String(),
            'received' => 0,
        ]));
    }

    public function stopReceiving(): void
    {
        Setting::write('capture_target', null);
    }

    /**
     * Get the patient who currently receives incoming images, if any.
     *
     * @return array{patient_id: int, name: string, until: string, received: int}|null
     */
    public function target(): ?array
    {
        $target = json_decode((string) Setting::read('capture_target', ''), true);

        if (! is_array($target) || Carbon::parse($target['until'])->isPast()) {
            return null;
        }

        $patient = Patient::query()->whereKey($target['patient_id'])->first();

        return $patient === null ? null : [
            'patient_id' => $patient->id,
            'name' => $patient->name,
            'until' => (string) $target['until'],
            'received' => (int) $target['received'],
        ];
    }

    /**
     * Get what the interface polls for: the inbox size and the receiving patient.
     *
     * @return array{pending: int, target: array{patient_id: int, name: string, until: string, received: int}|null}
     */
    public function status(): array
    {
        $this->sweep();

        return [
            'pending' => Capture::query()->count(),
            'target' => $this->target(),
        ];
    }

    /**
     * @param  list<Capture>  $captures
     */
    protected function deliverToTarget(array $captures): ?string
    {
        $target = $captures === [] ? null : $this->target();
        $patient = $target === null ? null : Patient::query()->whereKey($target['patient_id'])->first();

        if ($target === null || $patient === null) {
            return null;
        }

        $this->assign($captures, $patient);

        Setting::write('capture_target', (string) json_encode([
            'patient_id' => $patient->id,
            'until' => $target['until'],
            'received' => $target['received'] + count($captures),
        ]));

        return $patient->name;
    }

    /**
     * Copy one file into the inbox.
     *
     * @return Capture|'duplicate'|'skipped'
     */
    protected function take(string $file, string $batch, string $source, bool $keepOriginal): Capture|string
    {
        $extension = strtolower(File::extension($file));

        if (! File::isFile($file) || ! in_array($extension, (array) config('registry.attachments.mimes'), true)) {
            return 'skipped';
        }

        $hash = @hash_file('sha256', $file);

        if ($hash === false) {
            return 'skipped';
        }

        if (Capture::query()->where('hash', $hash)->exists() || Attachment::query()->where('hash', $hash)->exists()) {
            $this->removeOriginal($file, $keepOriginal);

            return 'duplicate';
        }

        $disk = (string) config('registry.attachments.disk');
        $path = self::INBOX.'/'.Str::uuid().'.'.$extension;
        $stream = @fopen($file, 'r');

        if ($stream === false) {
            return 'skipped';
        }

        $stored = Storage::disk($disk)->put($path, $stream);
        fclose($stream);

        if (! $stored || Storage::disk($disk)->size($path) !== File::size($file)) {
            Storage::disk($disk)->delete($path);

            return 'skipped';
        }

        try {
            $capture = Capture::query()->create([
                'batch' => $batch,
                'source' => $source,
                'disk' => $disk,
                'path' => $path,
                'original_name' => Str::limit(basename($file), 200, ''),
                'mime_type' => (string) File::mimeType($file),
                'size' => File::size($file),
                'hash' => $hash,
            ]);
        } catch (UniqueConstraintViolationException) {
            // Another window took the same file at the same moment.
            Storage::disk($disk)->delete($path);

            return 'duplicate';
        }

        $this->removeOriginal($file, $keepOriginal);

        return $capture;
    }

    protected function removeOriginal(string $file, bool $keepOriginal): void
    {
        if (! $keepOriginal) {
            @unlink($file);
        }
    }

    /**
     * Continue the batch of the export folder while files keep arriving, otherwise start a new one.
     */
    protected function openExportBatch(): string
    {
        $latest = Capture::query()->where('source', 'export')->latest('id')->first();

        return $latest !== null && $latest->created_at?->gt(now()->subMinutes(self::BATCH_GAP_MINUTES))
            ? $latest->batch
            : (string) Str::uuid();
    }

    /**
     * Replace folders by the files inside them, in name order.
     *
     * @param  list<string>  $paths
     * @return list<string>
     */
    protected function expand(array $paths): array
    {
        $files = [];

        foreach ($paths as $path) {
            if (File::isDirectory($path)) {
                $inside = array_map(fn ($file): string => $file->getPathname(), File::files($path));
                sort($inside, SORT_NATURAL | SORT_FLAG_CASE);
                $files = [...$files, ...$inside];
            } else {
                $files[] = $path;
            }
        }

        return $files;
    }
}
