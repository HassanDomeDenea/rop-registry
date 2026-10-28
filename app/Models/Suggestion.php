<?php

namespace App\Models;

use App\Enums\SuggestionList;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * One entry of a list the administrator picks from while typing, e.g. an illness or a referring doctor.
 *
 * @property int $id
 * @property SuggestionList $list
 * @property string $label
 * @property int $position
 * @property Carbon|null $hidden_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['list', 'label', 'position', 'hidden_at'])]
class Suggestion extends Model
{
    /**
     * The eye finding labels by id, kept until an entry of any list changes.
     *
     * @var array<int, string>|null
     */
    protected static ?array $eyeFindingLabels = null;

    protected static function booted(): void
    {
        $forget = fn () => static::$eyeFindingLabels = null;

        static::saved($forget);
        static::deleted($forget);
    }

    /**
     * Get the labels of a list in display order.
     *
     * @return list<string>
     */
    public static function labels(SuggestionList $list): array
    {
        /** @var list<string> */
        return static::query()
            ->where('list', $list)
            ->whereNull('hidden_at')
            ->orderBy('position')
            ->orderBy('id')
            ->pluck('label')
            ->all();
    }

    /**
     * Add a label to the end of a list unless it is already there.
     */
    public static function remember(SuggestionList $list, ?string $label): void
    {
        $label = trim((string) $label);

        if ($label === '' || static::query()->where('list', $list)->where('label', $label)->exists()) {
            return;
        }

        static::query()->create([
            'list' => $list,
            'label' => $label,
            'position' => (int) static::query()->where('list', $list)->max('position') + 1,
        ]);
    }

    /**
     * Get the eye findings in display order, including the hidden ones that saved visits still hold.
     *
     * @return list<array{id: int, label: string, hidden: bool}>
     */
    public static function eyeFindings(): array
    {
        return static::query()
            ->where('list', SuggestionList::EyeFinding)
            ->orderBy('position')
            ->orderBy('id')
            ->get()
            ->map(fn (Suggestion $finding): array => [
                'id' => $finding->id,
                'label' => $finding->label,
                'hidden' => $finding->hidden_at !== null,
            ])
            ->all();
    }

    /**
     * Get the label of every eye finding by id, in the order of the list.
     *
     * @return array<int, string>
     */
    public static function eyeFindingLabels(): array
    {
        /** @var array<int, string> */
        return static::$eyeFindingLabels ??= static::query()
            ->where('list', SuggestionList::EyeFinding)
            ->orderBy('position')
            ->orderBy('id')
            ->pluck('label', 'id')
            ->all();
    }

    /**
     * Count the visits on which this eye finding is ticked for either eye.
     */
    public function visitsCount(): int
    {
        return Visit::query()->withFinding($this->id)->count();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'list' => SuggestionList::class,
            'position' => 'integer',
            'hidden_at' => 'datetime',
        ];
    }
}
