<?php

namespace App\Models;

use App\Enums\EyeSide;
use App\Enums\TreatmentType;
use App\Models\Concerns\Auditable;
use App\Services\PatientSummarizer;
use Database\Factories\TreatmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $patient_id
 * @property int|null $visit_id
 * @property TreatmentType $type
 * @property EyeSide $eye
 * @property Carbon|null $performed_date
 * @property string|null $agent
 * @property string|null $performed_by
 * @property string|null $location
 * @property string|null $notes
 * @property string|null $source_reference
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Patient $patient
 * @property-read Visit|null $visit
 */
class Treatment extends Model
{
    /** @use HasFactory<TreatmentFactory> */
    use Auditable, HasFactory, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'visit_id', 'type', 'eye', 'performed_date', 'agent', 'performed_by', 'location', 'notes', 'source_reference',
    ];

    protected static function booted(): void
    {
        $refresh = fn (Treatment $treatment) => app(PatientSummarizer::class)->refreshById($treatment->patient_id);

        static::saved($refresh);
        static::deleted($refresh);
        static::restored($refresh);
    }

    /**
     * @return BelongsTo<Patient, $this>
     */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class)->withTrashed();
    }

    /**
     * @return BelongsTo<Visit, $this>
     */
    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }

    /**
     * Get the number of eyes treated in this session.
     */
    public function eyesCount(): int
    {
        return $this->eye === EyeSide::Both ? 2 : 1;
    }

    public function auditLabel(): string
    {
        return $this->patient->name.' · '.$this->type->label();
    }

    public function auditPatientId(): ?int
    {
        return $this->patient_id;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => TreatmentType::class,
            'eye' => EyeSide::class,
            'performed_date' => 'date:Y-m-d',
        ];
    }
}
