<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use App\Models\Patient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttachmentController extends Controller
{
    /**
     * Upload one or more pictures or PDF documents for the patient.
     */
    public function store(Request $request, Patient $patient): RedirectResponse
    {
        $validated = $request->validate([
            'files' => ['required', 'array', 'min:1', 'max:30'],
            'files.*' => [
                'required',
                File::types(config('registry.attachments.mimes'))->max((int) config('registry.attachments.max_kilobytes')),
            ],
            'visit_id' => ['nullable', 'integer', Rule::exists('visits', 'id')->where('patient_id', $patient->id)],
        ]);

        $disk = (string) config('registry.attachments.disk');

        /** @var list<UploadedFile> $files */
        $files = $validated['files'];

        foreach ($files as $file) {
            $extension = strtolower($file->getClientOriginalExtension() ?: (string) $file->guessExtension());
            $path = $file->storeAs("attachments/{$patient->id}", Str::uuid().'.'.$extension, $disk);

            $patient->attachments()->create([
                'visit_id' => $validated['visit_id'] ?? null,
                'disk' => $disk,
                'path' => $path,
                'original_name' => Str::limit($file->getClientOriginalName(), 200, ''),
                'mime_type' => (string) $file->getMimeType(),
                'size' => $file->getSize(),
            ]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => trans_choice(':count file uploaded.|:count files uploaded.', count($files), ['count' => count($files)])]);

        return back();
    }

    /**
     * Display or download the attachment.
     */
    public function show(Request $request, Attachment $attachment): StreamedResponse
    {
        $disk = Storage::disk($attachment->disk);

        abort_unless($disk->exists($attachment->path), 404);

        return $request->boolean('download')
            ? $disk->download($attachment->path, $attachment->original_name)
            : $disk->response($attachment->path, $attachment->original_name, ['Cache-Control' => 'private, max-age=86400']);
    }

    /**
     * Update the caption of the attachment.
     */
    public function update(Request $request, Attachment $attachment): RedirectResponse
    {
        $attachment->update($request->validate([
            'caption' => ['nullable', 'string', 'max:255'],
            'visit_id' => ['nullable', 'integer', Rule::exists('visits', 'id')->where('patient_id', $attachment->patient_id)],
        ]));

        return back();
    }

    /**
     * Delete the attachment. The file is kept on disk so the record can be restored from a backup.
     */
    public function destroy(Attachment $attachment): RedirectResponse
    {
        $attachment->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Attachment deleted.')]);

        return back();
    }
}
