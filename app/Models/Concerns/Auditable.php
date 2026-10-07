<?php

namespace App\Models\Concerns;

use App\Models\Audit;
use BackedEnum;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;

/**
 * Records every create, update, delete and restore of the model in the audits table.
 */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(function (self $model): void {
            $model->writeAudit('created', [], $model->auditableValues($model->getAttributes()));
        });

        static::updated(function (self $model): void {
            $new = $model->auditableValues($model->getDirty());

            if ($new === []) {
                return;
            }

            $old = $model->auditableValues(Arr::only($model->getRawOriginal(), array_keys($new)));

            $model->writeAudit('updated', $old, $new);
        });

        static::deleted(function (self $model): void {
            $model->writeAudit('deleted', $model->auditableValues($model->getRawOriginal()), []);
        });

        if (method_exists(static::class, 'restored')) {
            static::restored(function (self $model): void {
                $model->writeAudit('restored', [], []);
            });
        }
    }

    /**
     * @return MorphMany<Audit, $this>
     */
    public function audits(): MorphMany
    {
        return $this->morphMany(Audit::class, 'auditable')->latest('id');
    }

    /**
     * Get the attributes that are never written to the audit trail.
     *
     * @return list<string>
     */
    protected function auditExcluded(): array
    {
        return ['id', 'created_at', 'updated_at', 'deleted_at'];
    }

    /**
     * Get a short description of the record for the audit log.
     */
    abstract public function auditLabel(): string;

    /**
     * Get the patient the audited record belongs to.
     */
    abstract public function auditPatientId(): ?int;

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    protected function auditableValues(array $values): array
    {
        return collect(Arr::except($values, $this->auditExcluded()))
            ->map(fn (mixed $value): mixed => match (true) {
                $value instanceof BackedEnum => $value->value,
                $value instanceof DateTimeInterface => $value->format('Y-m-d H:i:s'),
                default => $value,
            })
            ->all();
    }

    /**
     * @param  array<string, mixed>  $old
     * @param  array<string, mixed>  $new
     */
    protected function writeAudit(string $event, array $old, array $new): void
    {
        if (! Audit::$enabled) {
            return;
        }

        Audit::query()->create([
            'user_id' => Auth::id(),
            'event' => $event,
            'auditable_type' => $this->getMorphClass(),
            'auditable_id' => $this->getKey(),
            'patient_id' => $this->auditPatientId(),
            'label' => $this->auditLabel(),
            'old_values' => $old ?: null,
            'new_values' => $new ?: null,
            'ip_address' => request()->ip(),
            'created_at' => now(),
        ]);
    }
}
