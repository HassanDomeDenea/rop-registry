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
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['list', 'label', 'position'])]
class Suggestion extends Model
{
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
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'list' => SuggestionList::class,
            'position' => 'integer',
        ];
    }
}
