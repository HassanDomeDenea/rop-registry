<?php

namespace App\Http\Controllers;

use App\Models\Capture;
use App\Models\Patient;
use App\Models\Setting;
use App\Services\CaptureService;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class CaptureController extends Controller
{
    /**
     * Show the camera inbox: received images grouped by the print job or export they came from.
     */
    public function index(CaptureService $captures): Response
    {
        $captures->sweep();

        $batches = Capture::query()->oldest('id')->get()
            ->groupBy('batch')
            ->map(fn (Collection $items, string $batch): array => [
                'id' => $batch,
                'source' => $items->first()?->source,
                'received_at' => $items->first()?->created_at?->toIso8601String(),
                'captures' => $items->map(fn (Capture $capture): array => [
                    'id' => $capture->id,
                    'name' => $capture->original_name,
                    'size' => $capture->size,
                    'is_image' => $capture->isImage(),
                    'url' => route('captures.show', $capture),
                ])->values(),
            ])
            ->sortByDesc('received_at')
            ->values();

        return Inertia::render('captures/Index', [
            'batches' => $batches,
            'suggestions' => $this->suggestions(),
            'folder' => $captures->folder(),
            'command' => base_path('capture-images.bat'),
        ]);
    }

    /**
     * Report the inbox size and the receiving patient; polled by every open window.
     */
    public function status(CaptureService $captures): JsonResponse
    {
        return response()->json($captures->status());
    }

    /**
     * Display a received image or PDF.
     */
    public function show(Capture $capture): StreamedResponse
    {
        $disk = Storage::disk($capture->disk);

        abort_unless($disk->exists($capture->path), 404);

        return $disk->response($capture->path, $capture->original_name, ['Cache-Control' => 'private, max-age=86400']);
    }

    /**
     * Attach the chosen captures to a patient.
     */
    public function assign(Request $request, CaptureService $captures): RedirectResponse
    {
        $validated = $request->validate([
            'captures' => ['required', 'array', 'min:1'],
            'captures.*' => ['integer'],
            'patient_id' => ['required', 'integer', 'exists:patients,id'],
            'today_visit' => ['boolean'],
        ]);

        $patient = Patient::query()->whereKey($validated['patient_id'])->firstOrFail();
        $chosen = Capture::query()->whereKey($validated['captures'])->oldest('id')->get();

        $visit = $captures->assign($chosen, $patient, (bool) ($validated['today_visit'] ?? true));

        Inertia::flash('toast', ['type' => 'success', 'message' => $visit !== null
            ? __(':count image(s) attached to :name, on the visit of today.', ['count' => $chosen->count(), 'name' => $patient->name])
            : __(':count image(s) attached to :name.', ['count' => $chosen->count(), 'name' => $patient->name])]);

        return back();
    }

    /**
     * Discard captures and delete their files.
     */
    public function destroy(Request $request, CaptureService $captures): RedirectResponse
    {
        $validated = $request->validate([
            'captures' => ['required', 'array', 'min:1'],
            'captures.*' => ['integer'],
        ]);

        Capture::query()->whereKey($validated['captures'])->get()->each(fn (Capture $capture) => $captures->discard($capture));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Images discarded.')]);

        return back();
    }

    /**
     * Send the images that arrive next straight to the patient.
     */
    public function receive(Patient $patient, CaptureService $captures): RedirectResponse
    {
        $captures->receiveFor($patient);

        return back();
    }

    /**
     * Stop sending incoming images to a patient.
     */
    public function stop(CaptureService $captures): RedirectResponse
    {
        $captures->stopReceiving();

        return back();
    }

    /**
     * Set the folder the camera software exports into.
     */
    public function settings(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'folder' => ['nullable', 'string', 'max:500', function (string $attribute, mixed $value, Closure $fail): void {
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

        Setting::write('capture_folder', $validated['folder'] ?? null);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Camera folder updated.')]);

        return back();
    }

    /**
     * Patients who are most likely in front of the camera: seen or expected today.
     *
     * @return array<int, array{id: int, name: string, file_number: string|null, dob: string|null}>
     */
    protected function suggestions(): array
    {
        $today = Carbon::today();

        return Patient::query()
            ->where(fn (Builder $query) => $query
                ->whereDate('next_appointment_date', $today)
                ->orWhereDate('last_visit_date', $today)
                ->orWhereDate('created_at', $today))
            ->latest('updated_at')
            ->limit(8)
            ->get()
            ->map(fn (Patient $patient): array => [
                'id' => $patient->id,
                'name' => $patient->name,
                'file_number' => $patient->file_number,
                'dob' => $patient->dob?->format('Y-m-d'),
            ])
            ->all();
    }
}
