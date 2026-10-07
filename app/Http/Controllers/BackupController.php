<?php

namespace App\Http\Controllers;

use App\Services\BackupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

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
}
