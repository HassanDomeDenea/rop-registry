<?php

namespace App\Models;

use App\Enums\DeliveryMode;
use App\Enums\Multiplicity;
use App\Enums\PatientStatus;
use App\Enums\RespiratorySupport;
use App\Enums\Sex;
use App\Enums\Stage;
use App\Models\Concerns\Auditable;
use App\Services\PatientSummarizer;
use App\Support\DuplicateFinder;
use Carbon\CarbonInterface;
use Database\Factories\PatientFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string|null $file_number
 * @property string $name
 * @property string|null $name_key
 * @property Carbon|null $dob
 * @property Sex $sex
 * @property int|null $birth_weight_g
 * @property int|null $ga_weeks
 * @property int|null $ga_days
 * @property Multiplicity|null $multiplicity
 * @property DeliveryMode|null $delivery_mode
 * @property Carbon|null $referral_date
 * @property string|null $referring_doctor
 * @property int|null $nicu_days
 * @property RespiratorySupport|null $respiratory_support
 * @property list<string>|null $illnesses
 * @property int|null $support_days
 * @property int|null $o2_days
 * @property int|null $cpap_days
 * @property string|null $systemic_illness
 * @property string|null $phone
 * @property string|null $phone_alt
 * @property string|null $address
 * @property string|null $notes
 * @property string|null $source_notes
 * @property PatientStatus $status
 * @property bool $unverified
 * @property int $exams_count
 * @property Carbon|null $first_visit_date
 * @property Carbon|null $last_visit_date
 * @property Carbon|null $next_appointment_date
 * @property bool|null $any_rop
 * @property Stage|null $highest_stage
 * @property bool $any_plus
 * @property bool $type_one
 * @property bool $had_injection
 * @property bool $had_laser
 * @property Carbon|null $last_injection_date
 * @property bool $treatment_pending
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Collection<int, Visit> $visits
 * @property-read Collection<int, Treatment> $treatments
 * @property-read Collection<int, Attachment> $attachments
 * @property-read Collection<int, ReviewItem> $reviewItems
 */
class Patient extends Model
{
    /** @use HasFactory<PatientFactory> */
    use Auditable, HasFactory, SoftDeletes;

    /**
     * The attributes the administrator may edit directly.
     *
     * @var list<string>
     */
    protected $fillable = [
        'file_number', 'name', 'dob', 'sex', 'birth_weight_g', 'ga_weeks', 'ga_days', 'multiplicity',
        'delivery_mode', 'referral_date', 'referring_doctor', 'nicu_days', 'respiratory_support',
        'support_days', 'o2_days', 'cpap_days', 'illnesses', 'systemic_illness', 'phone', 'phone_alt', 'address',
        'notes', 'source_notes', 'status', 'unverified',
    ];

    /**
     * The summary columns maintained by the PatientSummarizer.
     *
     * @var list<string>
     */
    public const SUMMARY_COLUMNS = [
        'exams_count', 'first_visit_date', 'last_visit_date', 'next_appointment_date', 'any_rop',
        'highest_stage', 'any_plus', 'type_one', 'had_injection', 'had_laser', 'last_injection_date',
        'treatment_pending',
    ];

    protected static function booted(): void
    {
        static::saving(function (Patient $patient): void {
            $patient->name_key = app(DuplicateFinder::class)->normalize((string) $patient->name);
        });

        static::saved(function (Patient $patient): void {
            if ($patient->wasChanged('status')) {
                app(PatientSummarizer::class)->refresh($patient);
            }
        });
    }

    /**
     * @return HasMany<Visit, $this>
     */
    public function visits(): HasMany
    {
        return $this->hasMany(Visit::class)->chronological();
    }

    /**
     * @return HasMany<Treatment, $this>
     */
    public function treatments(): HasMany
    {
        return $this->hasMany(Treatment::class)->orderByRaw('performed_date is null')->orderBy('performed_date')->orderBy('id');
    }

    /**
     * @return HasMany<Attachment, $this>
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(Attachment::class)->orderBy('id');
    }

    /**
     * @return HasMany<ReviewItem, $this>
     */
    public function reviewItems(): HasMany
    {
        return $this->hasMany(ReviewItem::class)->orderByRaw('resolved_at is not null')->orderBy('id');
    }

    /**
     * Get the gestational age at birth in days.
     */
    public function gestationalAgeInDays(): ?int
    {
        if ($this->ga_weeks === null) {
            return null;
        }

        return $this->ga_weeks * 7 + ($this->ga_days ?? 0);
    }

    /**
     * Get the postmenstrual age in days on the given date.
     */
    public function postmenstrualAgeInDays(?CarbonInterface $date): ?int
    {
        $gestationalAge = $this->gestationalAgeInDays();

        if ($gestationalAge === null || $this->dob === null || $date === null || $date->lt($this->dob)) {
            return null;
        }

        return $gestationalAge + (int) $this->dob->diffInDays($date);
    }

    /**
     * Get the chronological (postnatal) age in days on the given date.
     */
    public function chronologicalAgeInDays(?CarbonInterface $date): ?int
    {
        if ($this->dob === null || $date === null || $date->lt($this->dob)) {
            return null;
        }

        return (int) $this->dob->diffInDays($date);
    }

    /**
     * Limit the query to patients matching the free text search.
     *
     * @param  Builder<Patient>  $query
     */
    public function scopeSearch(Builder $query, ?string $term): void
    {
        $term = trim((string) $term);

        if ($term === '') {
            return;
        }

        $query->where(function (Builder $query) use ($term): void {
            $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';

            $key = app(DuplicateFinder::class)->normalize($term);

            $query->where('name', 'like', $like)
                ->when($key !== '', fn (Builder $query) => $query->orWhere('name_key', 'like', '%'.$key.'%'))
                ->orWhere('file_number', 'like', $like)
                ->orWhere('phone', 'like', $like)
                ->orWhere('phone_alt', 'like', $like)
                ->orWhere('referring_doctor', 'like', $like);
        });
    }

    /**
     * Limit the query to patients whose identity is confirmed.
     *
     * @param  Builder<Patient>  $query
     */
    public function scopeVerified(Builder $query): void
    {
        $query->where('unverified', false);
    }

    public function auditLabel(): string
    {
        return $this->name;
    }

    public function auditPatientId(): ?int
    {
        return $this->id;
    }

    /**
     * @return list<string>
     */
    protected function auditExcluded(): array
    {
        return ['id', 'name_key', 'created_at', 'updated_at', 'deleted_at', ...self::SUMMARY_COLUMNS];
    }

    /**
     * Get the ticked illnesses and the free-text remainder as one line.
     */
    public function illnessSummary(): ?string
    {
        $parts = array_filter([...($this->illnesses ?? []), $this->systemic_illness], filled(...));

        return $parts === [] ? null : implode('; ', $parts);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'dob' => 'date:Y-m-d',
            'referral_date' => 'date:Y-m-d',
            'sex' => Sex::class,
            'multiplicity' => Multiplicity::class,
            'delivery_mode' => DeliveryMode::class,
            'respiratory_support' => RespiratorySupport::class,
            'status' => PatientStatus::class,
            'unverified' => 'boolean',
            'illnesses' => 'array',
            'first_visit_date' => 'date:Y-m-d',
            'last_visit_date' => 'date:Y-m-d',
            'next_appointment_date' => 'date:Y-m-d',
            'last_injection_date' => 'date:Y-m-d',
            'any_rop' => 'boolean',
            'highest_stage' => Stage::class,
            'any_plus' => 'boolean',
            'type_one' => 'boolean',
            'had_injection' => 'boolean',
            'had_laser' => 'boolean',
            'treatment_pending' => 'boolean',
        ];
    }
}
