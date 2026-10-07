<?php

namespace App\Models;

use App\Enums\ManagementPlan;
use App\Enums\PlusDisease;
use App\Enums\RopStatus;
use App\Enums\RopType;
use App\Enums\Stage;
use App\Enums\VisitKind;
use App\Enums\Zone;
use App\Models\Concerns\Auditable;
use App\Services\PatientSummarizer;
use App\Support\RopClassifier;
use Database\Factories\VisitFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $patient_id
 * @property VisitKind $kind
 * @property Carbon|null $visit_date
 * @property string|null $examiner
 * @property string|null $right_dilatation
 * @property string|null $right_lens
 * @property PlusDisease|null $right_plus
 * @property Zone|null $right_zone
 * @property Stage|null $right_stage
 * @property bool|null $right_a_rop
 * @property RopType|null $right_rop_type
 * @property RopStatus|null $right_rop_status
 * @property string|null $right_notes
 * @property string|null $left_dilatation
 * @property string|null $left_lens
 * @property PlusDisease|null $left_plus
 * @property Zone|null $left_zone
 * @property Stage|null $left_stage
 * @property bool|null $left_a_rop
 * @property RopType|null $left_rop_type
 * @property RopStatus|null $left_rop_status
 * @property string|null $left_notes
 * @property string|null $assessment
 * @property ManagementPlan|null $management_plan
 * @property string|null $management_notes
 * @property Carbon|null $next_visit_date
 * @property int|null $fee
 * @property string|null $notes
 * @property string|null $source_reference
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Patient $patient
 * @property-read Collection<int, Treatment> $treatments
 * @property-read Collection<int, Attachment> $attachments
 */
class Visit extends Model
{
    /** @use HasFactory<VisitFactory> */
    use Auditable, HasFactory, SoftDeletes;

    public const EYES = ['right', 'left'];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'kind', 'visit_date', 'examiner',
        'right_dilatation', 'right_lens', 'right_plus', 'right_zone', 'right_stage', 'right_a_rop', 'right_rop_type', 'right_rop_status', 'right_notes',
        'left_dilatation', 'left_lens', 'left_plus', 'left_zone', 'left_stage', 'left_a_rop', 'left_rop_type', 'left_rop_status', 'left_notes',
        'assessment', 'management_plan', 'management_notes', 'next_visit_date', 'fee', 'notes', 'source_reference',
    ];

    protected static function booted(): void
    {
        $refresh = fn (Visit $visit) => app(PatientSummarizer::class)->refreshById($visit->patient_id);

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
     * @return HasMany<Treatment, $this>
     */
    public function treatments(): HasMany
    {
        return $this->hasMany(Treatment::class);
    }

    /**
     * @return HasMany<Attachment, $this>
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(Attachment::class);
    }

    /**
     * Order visits by date, placing undated records after the dated ones.
     *
     * @param  Builder<Visit>  $query
     */
    public function scopeChronological(Builder $query): void
    {
        $query->orderByRaw('visit_date is null')->orderBy('visit_date')->orderBy('id');
    }

    /**
     * Determine whether the given eye has documented retinopathy at this visit.
     */
    public function eyeHasRop(string $eye): bool
    {
        /** @var RopStatus|null $status */
        $status = $this->getAttribute("{$eye}_rop_status");
        /** @var Stage|null $stage */
        $stage = $this->getAttribute("{$eye}_stage");

        return ($status?->indicatesRop() ?? false)
            || ($stage?->indicatesRop() ?? false)
            || $this->getAttribute("{$eye}_a_rop") === true
            || $this->getAttribute("{$eye}_rop_type") !== null;
    }

    /**
     * Determine whether the given eye was documented as free of retinopathy at this visit.
     */
    public function eyeExcludesRop(string $eye): bool
    {
        /** @var RopStatus|null $status */
        $status = $this->getAttribute("{$eye}_rop_status");
        /** @var Stage|null $stage */
        $stage = $this->getAttribute("{$eye}_stage");

        return ! $this->eyeHasRop($eye)
            && (($status?->excludesRop() ?? false) || $stage === Stage::StageZero);
    }

    /**
     * Get the ETROP type of the given eye, preferring the recorded value.
     */
    public function eyeRopType(string $eye): ?RopType
    {
        /** @var RopType|null $recorded */
        $recorded = $this->getAttribute("{$eye}_rop_type");

        return $recorded ?? RopClassifier::classify(
            $this->getAttribute("{$eye}_zone"),
            $this->getAttribute("{$eye}_stage"),
            $this->getAttribute("{$eye}_plus"),
            $this->getAttribute("{$eye}_a_rop"),
        );
    }

    public function auditLabel(): string
    {
        return $this->patient->name.' · '.($this->visit_date?->format('Y-m-d') ?? '—');
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
            'kind' => VisitKind::class,
            'visit_date' => 'date:Y-m-d',
            'next_visit_date' => 'date:Y-m-d',
            'right_plus' => PlusDisease::class,
            'right_zone' => Zone::class,
            'right_stage' => Stage::class,
            'right_a_rop' => 'boolean',
            'right_rop_type' => RopType::class,
            'right_rop_status' => RopStatus::class,
            'left_plus' => PlusDisease::class,
            'left_zone' => Zone::class,
            'left_stage' => Stage::class,
            'left_a_rop' => 'boolean',
            'left_rop_type' => RopType::class,
            'left_rop_status' => RopStatus::class,
            'management_plan' => ManagementPlan::class,
        ];
    }
}
