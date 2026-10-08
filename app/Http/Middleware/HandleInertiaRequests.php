<?php

namespace App\Http\Middleware;

use App\Enums\DeliveryMode;
use App\Enums\EyeSide;
use App\Enums\ManagementPlan;
use App\Enums\Multiplicity;
use App\Enums\PatientStatus;
use App\Enums\PlusDisease;
use App\Enums\RespiratorySupport;
use App\Enums\RopStatus;
use App\Enums\RopType;
use App\Enums\Sex;
use App\Enums\Stage;
use App\Enums\SuggestionList;
use App\Enums\TreatmentType;
use App\Enums\VisitKind;
use App\Enums\Zone;
use App\Services\ReminderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $locale = app()->getLocale();

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $request->user(),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'locale' => $locale,
            'direction' => config("registry.locales.{$locale}.dir", 'ltr'),
            'locales' => $this->locales(),
            'translations' => fn (): array => $this->translations($locale),
            'enums' => fn (): array => $this->enums(),
            'features' => ['captures' => (bool) config('registry.capture.enabled')],
            'reminderCounts' => fn (): ?array => $request->user() ? app(ReminderService::class)->counts() : null,
        ];
    }

    /**
     * Get the languages the interface is available in.
     *
     * @return list<array{code: string, label: string}>
     */
    protected function locales(): array
    {
        $locales = [];

        foreach (Config::array('registry.locales') as $code => $language) {
            $locales[] = ['code' => (string) $code, 'label' => $language['label']];
        }

        return $locales;
    }

    /**
     * Get the interface strings of the locale, keyed by their English source text.
     *
     * @return array<string, string>
     */
    protected function translations(string $locale): array
    {
        $path = lang_path("{$locale}.json");

        return File::exists($path) ? (array) json_decode((string) File::get($path), true) : [];
    }

    /**
     * Get the translated options of every registry enum.
     *
     * @return array<string, list<array{value: string, label: string, en: string}>>
     */
    protected function enums(): array
    {
        return [
            'sex' => Sex::options(),
            'multiplicity' => Multiplicity::options(),
            'delivery_mode' => DeliveryMode::options(),
            'respiratory_support' => RespiratorySupport::options(),
            'patient_status' => PatientStatus::options(),
            'zone' => Zone::options(),
            'stage' => Stage::options(),
            'rop_status' => RopStatus::options(),
            'plus_disease' => PlusDisease::options(),
            'rop_type' => RopType::options(),
            'management_plan' => ManagementPlan::options(),
            'visit_kind' => VisitKind::options(),
            'treatment_type' => TreatmentType::options(),
            'eye_side' => EyeSide::options(),
            'suggestion_list' => SuggestionList::options(),
        ];
    }
}
