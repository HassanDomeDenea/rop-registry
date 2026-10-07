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
                ->get(['id', 'list', 'label']),
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
     * Remove an entry from its list.
     */
    public function destroy(Suggestion $suggestion): RedirectResponse
    {
        $suggestion->delete();

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
