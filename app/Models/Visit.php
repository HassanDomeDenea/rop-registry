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
use Illuminate\Database\Eloquent\Casts\Attribute;
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
 * @property PlusDisease|null $right_plus
 * @property Zone|null $right_zone
 * @property Stage|null $right_stage
 * @property bool|null $right_a_rop
 * @property RopType|null $right_rop_type
 * @property RopStatus|null $right_rop_status
 * @property list<int>|null $right_findings
 * @property string|null $right_notes
 * @property PlusDisease|null $left_plus
 * @property Zone|null $left_zone
 * @property Stage|null $left_stage
 * @property bool|null $left_a_rop
 * @property RopType|null $left_rop_type
 * @property RopStatus|null $left_rop_status
 * @property list<int>|null $left_findings
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
        'right_plus', 'right_zone', 'right_stage', 'right_a_rop', 'right_rop_type', 'right_rop_status', 'right_findings', 'right_notes',
        'left_plus', 'left_zone', 'left_stage', 'left_a_rop', 'left_rop_type', 'left_rop_status', 'left_findings', 'left_notes',
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
     * Limit the query to visits on which the given eye finding is ticked for either eye.
     *
     * @param  Builder<Visit>  $query
     */
    public function scopeWithFinding(Builder $query, int $findingId): void
    {
        $query->where(fn (Builder $query) => $query
            ->whereJsonContains('right_findings', $findingId)
            ->orWhereJsonContains('left_findings', $findingId));
    }

    /**
     * Get the ids of the eye findings ticked for the given eye.
     *
     * @return list<int>
     */
    public function eyeFindings(string $eye): array
    {
        /** @var list<int>|null $findings */
        $findings = $this->getAttribute("{$eye}_findings");

        return $findings ?? [];
    }

    /**
     * Get the labels of the eye findings ticked for the given eye, in the order of the list.
     *
     * @return list<string>
     */
    public function eyeFindingLabels(string $eye): array
    {
        $ticked = $this->eyeFindings($eye);

        return array_values(array_filter(
            Suggestion::eyeFindingLabels(),
            fn (int $id): bool => in_array($id, $ticked, true),
            ARRAY_FILTER_USE_KEY,
        ));
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

    /**
     * Get the fees charged most often, offered as one-tap choices on the examination forms.
     *
     * @return list<int>
     */
    public static function commonFees(): array
    {
        $fees = self::query()
            ->whereNotNull('fee')
            ->select('fee')
            ->groupBy('fee')
            ->orderByRaw('count(*) desc')
            ->orderBy('fee')
            ->limit(6)
            ->pluck('fee')
            ->map(fn (mixed $fee): int => (int) $fee)
            ->sort()
            ->all();

        return array_values($fees);
    }

    /**
     * Keep the ticked findings as a sorted list of ids, and an empty checklist as "not recorded".
     *
     * @return Attribute<list<int>|null, mixed>
     */
    protected function rightFindings(): Attribute
    {
        return Attribute::set(fn (mixed $value): ?string => self::encodeFindings($value));
    }

    /**
     * @return Attribute<list<int>|null, mixed>
     */
    protected function leftFindings(): Attribute
    {
        return Attribute::set(fn (mixed $value): ?string => self::encodeFindings($value));
    }

    protected static function encodeFindings(mixed $value): ?string
    {
        $ids = collect(is_array($value) ? $value : [])
            ->map(fn (mixed $id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->sort()
            ->values()
            ->all();

        return $ids === [] ? null : (string) json_encode($ids);
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
            'right_findings' => 'array',
            'left_plus' => PlusDisease::class,
            'left_zone' => Zone::class,
            'left_stage' => Stage::class,
            'left_a_rop' => 'boolean',
            'left_rop_type' => RopType::class,
            'left_rop_status' => RopStatus::class,
            'left_findings' => 'array',
            'management_plan' => ManagementPlan::class,
        ];
    }
}
