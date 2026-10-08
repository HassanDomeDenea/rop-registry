<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PreferencesController extends Controller
{
    /**
     * Show the theme and language settings.
     */
    public function edit(): Response
    {
        return Inertia::render('settings/Appearance', [
            'printLocale' => Setting::read('print_locale'),
        ]);
    }

    /**
     * Update the interface language of the user, or the language of printed reports.
     */
    public function update(Request $request): RedirectResponse
    {
        $locales = array_keys(config('registry.locales'));

        $validated = $request->validate([
            'locale' => ['sometimes', 'required', Rule::in($locales)],
            'print_locale' => ['sometimes', 'nullable', Rule::in($locales)],
        ]);

        if (array_key_exists('print_locale', $validated)) {
            Setting::write('print_locale', $validated['print_locale']);
        }

        if (! isset($validated['locale'])) {
            return back();
        }

        $request->user()->update(['locale' => $validated['locale']]);

        return back()->withCookie(cookie()->forever('locale', $validated['locale']));
    }
}
