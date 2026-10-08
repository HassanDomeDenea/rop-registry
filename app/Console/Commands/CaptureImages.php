<?php

namespace App\Console\Commands;

use App\Services\CaptureService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Called by the virtual printer after a print job, with the paths of the pages it saved.
 * Without paths it only takes over what waits in the export folder.
 */
#[Signature('registry:capture {files?* : Image or PDF files, or folders that hold them} {--keep : Leave the original files in place}')]
#[Description('Put camera images into the inbox of the registry')]
class CaptureImages extends Command
{
    public function handle(CaptureService $captures): int
    {
        /** @var list<string> $files */
        $files = array_values((array) $this->argument('files'));

        $result = $captures->ingest($files, 'printer', (bool) $this->option('keep'));
        $swept = $captures->sweep();

        foreach ($result['skipped'] as $file) {
            $this->components->warn("Not taken (missing, unreadable or not an image or PDF): {$file}");
        }

        $this->components->info(sprintf(
            '%d received, %d already in the inbox%s.',
            $result['received'] + $swept,
            $result['duplicates'],
            $result['assigned_to'] === null ? '' : ', attached to '.$result['assigned_to'],
        ));

        return $files !== [] && $result['received'] + $result['duplicates'] === 0 ? self::FAILURE : self::SUCCESS;
    }
}
