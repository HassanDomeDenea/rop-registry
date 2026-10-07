<?php

namespace App\Enums\Concerns;

use Illuminate\Support\Str;

trait HasOptions
{
    /**
     * Get the translated, human readable label of the case.
     */
    public function label(): string
    {
        return __('enums.'.Str::snake(class_basename(static::class)).'.'.$this->value);
    }

    /**
     * Get the English label, used where clinical terms are shown untranslated.
     */
    public function englishLabel(): string
    {
        return __('enums.'.Str::snake(class_basename(static::class)).'.'.$this->value, [], 'en');
    }

    /**
     * Get every case as a value / label pair for select inputs.
     *
     * @return list<array{value: string, label: string, en: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $case): array => ['value' => $case->value, 'label' => $case->label(), 'en' => $case->englishLabel()],
            self::cases(),
        );
    }

    /**
     * Get every case value.
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
