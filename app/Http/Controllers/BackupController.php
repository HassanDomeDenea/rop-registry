<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Services\BackupService;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class BackupController extends Controller
{
    /**
     * List the available backup archives.
     */
    public function index(BackupService $backups): Response
    {
        return Inertia::render('backups/Index', [
            'backups' => $backups->all(),
            'directory' => $backups->directory(),
            'customDirectory' => Setting::read('backup_path'),
            'keep' => (int) config('registry.backup.keep'),
        ]);
    }

    /**
     * Create a new backup archive.
     */
    public function store(Request $request, BackupService $backups): RedirectResponse
    {
        $validated = $request->validate([
            'type' => ['required', Rule::in(['full', 'database'])],
        ]);

        $backups->create($validated['type']);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Backup created.')]);

        return back();
    }

    /**
     * Download a backup archive.
     */
    public function show(string $backup, BackupService $backups): BinaryFileResponse
    {
        return response()->download($backups->path($backup));
    }

    /**
     * Delete a backup archive.
     */
    public function destroy(string $backup, BackupService $backups): RedirectResponse
    {
        $backups->delete($backup);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Backup deleted.')]);

        return back();
    }

    /**
     * Choose the folder in which backups are stored, e.g. a second drive or a synced folder.
     */
    public function settings(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'path' => ['nullable', 'string', 'max:500', function (string $attribute, mixed $value, Closure $fail): void {
                try {
                    File::ensureDirectoryExists((string) $value);
                } catch (Throwable) {
                    // Reported below as a folder that cannot be used.
                }

                if (! File::isDirectory((string) $value) || ! File::isWritable((string) $value)) {
                    $fail(__('This folder does not exist or cannot be written to.'));
                }
            }],
        ]);

        Setting::write('backup_path', $validated['path'] ?? null);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Backup folder updated.')]);

        return back();
    }

    /**
     * Replace the registry with a backup: either one from the list or an uploaded archive.
     * The current state is saved first, and the administrator signs in again afterwards.
     */
    public function restore(Request $request, BackupService $backups): RedirectResponse
    {
        $validated = $request->validate([
            'confirmation' => ['required', Rule::in(['RESTORE'])],
            'backup' => ['nullable', 'required_without:archive', 'string'],
            'archive' => ['nullable', 'required_without:backup', 'file', 'extensions:zip'],
        ]);

        $path = $request->file('archive')?->getRealPath() ?: $backups->path((string) ($validated['backup'] ?? ''));

        try {
            $backups->restore($path);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['archive' => $exception->getMessage()]);
        }

        // The sessions and accounts now come from the backup, so the current sign-in no longer applies.
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return to_route('login')->with('status', __('The backup was restored. Sign in with the account stored in that backup.'));
    }
}
