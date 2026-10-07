<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;

/**
 * A single application setting that the administrator can change from the interface.
 *
 * @property string $key
 * @property string|null $value
 */
#[Fillable(['key', 'value'])]
class Setting extends Model
{
    protected $primaryKey = 'key';

    protected $keyType = 'string';

    public $incrementing = false;

    /**
     * Get a setting, or the default when it is not set or the table does not exist yet.
     */
    public static function read(string $key, ?string $default = null): ?string
    {
        try {
            $value = static::query()->find($key)?->value;
        } catch (QueryException) {
            return $default;
        }

        return filled($value) ? $value : $default;
    }

    public static function write(string $key, ?string $value): void
    {
        static::query()->updateOrCreate(['key' => $key], ['value' => $value]);
    }
}
