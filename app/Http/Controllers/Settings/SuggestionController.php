<?php

namespace App\Http\Controllers\Settings;

use App\Enums\SuggestionList;
use App\Http\Controllers\Controller;
use App\Models\Suggestion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SuggestionController extends Controller
{
    /**
     * Show the pick lists the administrator can edit.
     */
    public function index(): Response
    {
        return Inertia::render('settings/Lists', [
            'suggestions' => Suggestion::query()
                ->orderBy('position')
                ->orderBy('id')
                ->get(['id', 'list', 'label', 'hidden_at'])
                ->map(fn (Suggestion $suggestion): array => [
                    'id' => $suggestion->id,
                    'list' => $suggestion->list->value,
                    'label' => $suggestion->label,
                    'hidden' => $suggestion->hidden_at !== null,
                    'visits_count' => $suggestion->list === SuggestionList::EyeFinding ? $suggestion->visitsCount() : null,
                ]),
        ]);
    }

    /**
     * Add an entry to a list.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'list' => ['required', Rule::enum(SuggestionList::class)],
            'label' => $this->labelRules($request->string('list')->toString()),
        ]);

        Suggestion::remember(SuggestionList::from($validated['list']), $validated['label']);

        return back();
    }

    /**
     * Rename an entry. Records already saved keep the text they were saved with.
     */
    public function update(Request $request, Suggestion $suggestion): RedirectResponse
    {
        $suggestion->update($request->validate([
            'label' => $this->labelRules($suggestion->list->value, $suggestion),
        ]));

        return back();
    }

    /**
     * Remove an entry from its list. An eye finding that saved visits still hold is hidden
     * from the forms instead, so that those visits and the statistics keep it.
     */
    public function destroy(Suggestion $suggestion): RedirectResponse
    {
        if ($suggestion->list === SuggestionList::EyeFinding && $suggestion->visitsCount() > 0) {
            $suggestion->update(['hidden_at' => now()]);

            Inertia::flash('toast', ['type' => 'success', 'message' => __('Hidden from the examination form. Visits that recorded it keep it.')]);

            return back();
        }

        $suggestion->delete();

        return back();
    }

    /**
     * Hide an entry from the forms, or offer it again.
     */
    public function visibility(Request $request, Suggestion $suggestion): RedirectResponse
    {
        $hidden = $request->validate(['hidden' => ['required', 'boolean']])['hidden'];

        $suggestion->update(['hidden_at' => $hidden ? now() : null]);

        return back();
    }

    /**
     * @return list<mixed>
     */
    protected function labelRules(string $list, ?Suggestion $ignore = null): array
    {
        return [
            'required', 'string', 'max:100',
            Rule::unique('suggestions', 'label')->where('list', $list)->ignore($ignore),
        ];
    }
}
