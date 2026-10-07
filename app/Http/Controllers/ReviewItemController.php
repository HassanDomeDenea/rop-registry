<?php

namespace App\Http\Controllers;

use App\Models\Patient;
use App\Models\ReviewItem;
use App\Support\RegistryPresenter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReviewItemController extends Controller
{
    /**
     * List the open review items: facts that await confirmation against the source papers.
     */
    public function index(Request $request): Response
    {
        $search = $request->string('search')->trim()->toString();

        $items = ReviewItem::query()
            ->open()
            ->with('patient')
            ->whereHas('patient', fn (Builder $query) => $query->whereNull('deleted_at')->search($search))
            ->orderBy('patient_id')
            ->orderBy('id')
            ->paginate(40)
            ->withQueryString()
            ->through(fn (ReviewItem $item): array => [
                ...RegistryPresenter::reviewItem($item),
                'patient_name' => $item->patient->name,
                'patient_file_number' => $item->patient->file_number,
            ]);

        return Inertia::render('review/Index', [
            'items' => $items,
            'filters' => ['search' => $search],
        ]);
    }

    /**
     * Add a review note to the patient.
     */
    public function store(Request $request, Patient $patient): RedirectResponse
    {
        $patient->reviewItems()->create($request->validate([
            'field' => ['nullable', 'string', 'max:100'],
            'issue' => ['required', 'string', 'max:2000'],
        ]));

        return back();
    }

    /**
     * Resolve or reopen the review item.
     */
    public function update(Request $request, ReviewItem $reviewItem): RedirectResponse
    {
        $validated = $request->validate([
            'resolved' => ['required', 'boolean'],
            'resolution' => ['nullable', 'string', 'max:2000'],
        ]);

        $reviewItem->update([
            'resolved_at' => $validated['resolved'] ? now() : null,
            'resolution' => $validated['resolved'] ? ($validated['resolution'] ?? null) : null,
        ]);

        return back();
    }

    /**
     * Delete the review item.
     */
    public function destroy(ReviewItem $reviewItem): RedirectResponse
    {
        $reviewItem->delete();

        return back();
    }
}
