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
            ? $disk->download($attachment->path, $attachment->fileName())
            : $disk->response($attachment->path, $attachment->fileName(), ['Cache-Control' => 'private, max-age=86400']);
    }

    /**
     * Replace the picture with its edited version. The file as first received stays on the
     * disk, so the edit can be undone; so do earlier edits, as a backup may still refer to them.
     */
    public function updateImage(Request $request, Attachment $attachment): RedirectResponse
    {
        abort_unless($attachment->isImage(), 404);

        $request->validate([
            'image' => ['required', File::types(['jpg', 'jpeg', 'png'])->max((int) config('registry.attachments.max_kilobytes'))],
        ]);

        /** @var UploadedFile $image */
        $image = $request->file('image');

        $attachment->update([
            'original_path' => $attachment->original_path ?? $attachment->path,
            'original_mime_type' => $attachment->original_mime_type ?? $attachment->mime_type,
            'original_size' => $attachment->original_size ?? $attachment->size,
            'path' => $image->storeAs("attachments/{$attachment->patient_id}", Str::uuid().'.'.$image->guessExtension(), $attachment->disk),
            'mime_type' => (string) $image->getMimeType(),
            'size' => $image->getSize(),
            'edited_at' => now(),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Picture saved.')]);

        return back();
    }

    /**
     * Return to the picture as it was first received.
     */
    public function restoreImage(Attachment $attachment): RedirectResponse
    {
        abort_unless($attachment->isEdited(), 404);

        $attachment->update([
            'path' => $attachment->original_path,
            'mime_type' => $attachment->original_mime_type,
            'size' => $attachment->original_size,
            'original_path' => null,
            'original_mime_type' => null,
            'original_size' => null,
            'edited_at' => null,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Original picture restored.')]);

        return back();
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
