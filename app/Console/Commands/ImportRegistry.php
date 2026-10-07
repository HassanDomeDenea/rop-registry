<?php

namespace App\Console\Commands;

use App\Models\Audit;
use App\Models\Patient;
use App\Models\Visit;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Imports patients from a JSON export of the legacy one-row-per-patient workbook.
 *
 * The file holds {"patients": [...]}; every patient carries its own attributes plus
 * "visits" (each with an optional "treatment"), "review_items" and "attachments" (scanned
 * pages given as {"source": path, "caption": text}). Enumerated values must already use
 * the registry's enum values.
 */
#[Signature('registry:import {file : Path of the JSON export} {--fresh : Permanently remove the patients that are already in the registry first}')]
#[Description('Import patients, visits, treatments and review items from a JSON export of the legacy workbook')]
class ImportRegistry extends Command
{
    public function handle(): int
    {
        $file = (string) $this->argument('file');

        if (! File::exists($file)) {
            $this->components->error("File not found: {$file}");

            return self::FAILURE;
        }

        /** @var array{patients?: list<array<string, mixed>>} $data */
        $data = json_decode((string) File::get($file), true, flags: JSON_THROW_ON_ERROR);
        $records = $data['patients'] ?? [];

        if (Patient::withTrashed()->exists() && ! $this->option('fresh')) {
            $this->components->error('The registry already contains patients. Use --fresh to replace them.');

            return self::FAILURE;
        }

        $counts = ['patients' => 0, 'visits' => 0, 'treatments' => 0, 'review_items' => 0, 'attachments' => 0];
        $disk = (string) config('registry.attachments.disk');

        DB::transaction(function () use ($records, &$counts, $disk): void {
            Audit::withoutAuditing(function () use ($records, &$counts, $disk): void {
                if ($this->option('fresh')) {
                    Patient::withTrashed()->get()->each->forceDelete();
                    Storage::disk($disk)->deleteDirectory('attachments');
                }

                foreach ($records as $record) {
                    $patient = Patient::query()->create(Arr::except($record, ['visits', 'review_items', 'attachments']));
                    $counts['patients']++;

                    foreach ($record['visits'] ?? [] as $visitRecord) {
                        /** @var Visit $visit */
                        $visit = $patient->visits()->create(Arr::except($visitRecord, ['treatment']));
                        $counts['visits']++;

                        if (isset($visitRecord['treatment'])) {
                            $patient->treatments()->create([...$visitRecord['treatment'], 'visit_id' => $visit->id]);
                            $counts['treatments']++;
                        }
                    }

                    foreach ($record['review_items'] ?? [] as $item) {
                        $patient->reviewItems()->create($item);
                        $counts['review_items']++;
                    }

                    foreach ($record['attachments'] ?? [] as $attachment) {
                        if (! File::exists($attachment['source'])) {
                            $this->components->warn("Scan not found: {$attachment['source']}");

                            continue;
                        }

                        $path = "attachments/{$patient->id}/".Str::uuid().'.'.File::extension($attachment['source']);

                        Storage::disk($disk)->put($path, File::get($attachment['source']));

                        $patient->attachments()->create([
                            'disk' => $disk,
                            'path' => $path,
                            'original_name' => basename($attachment['source']),
                            'mime_type' => (string) File::mimeType($attachment['source']),
                            'size' => File::size($attachment['source']),
                            'caption' => $attachment['caption'] ?? null,
                        ]);
                        $counts['attachments']++;
                    }
                }
            });
        });

        $this->components->info(sprintf(
            'Imported %d patients, %d visits, %d treatments, %d review items and %d scans.',
            $counts['patients'], $counts['visits'], $counts['treatments'], $counts['review_items'], $counts['attachments'],
        ));

        return self::SUCCESS;
    }
}
