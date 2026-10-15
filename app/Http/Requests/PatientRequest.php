<?php

namespace App\Http\Requests;

use App\Enums\DeliveryMode;
use App\Enums\Multiplicity;
use App\Enums\PatientStatus;
use App\Enums\RespiratorySupport;
use App\Enums\Sex;
use App\Models\Patient;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Lang;
use Illuminate\Validation\Rule;

class PatientRequest extends FormRequest
{
    /**
     * An empty checklist is stored as "not recorded".
     */
    protected function prepareForValidation(): void
    {
        if ($this->input('illnesses') === []) {
            $this->merge(['illnesses' => null]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $patient = $this->route('patient');

        return [
            'file_number' => ['nullable', 'string', 'max:50'],
            'name' => ['required', 'string', 'max:255'],
            'mother_name' => ['nullable', 'string', 'max:255'],
            'dob' => ['nullable', 'date', 'before_or_equal:today'],
            'sex' => ['required', Rule::enum(Sex::class)],
            'birth_weight_g' => ['nullable', 'integer', 'between:200,7000'],
            'ga_weeks' => ['nullable', 'integer', 'between:20,44'],
            'ga_days' => ['nullable', 'integer', 'between:0,6'],
            'multiplicity' => ['nullable', Rule::enum(Multiplicity::class)],
            'delivery_mode' => ['nullable', Rule::enum(DeliveryMode::class)],
            'referral_date' => ['nullable', 'date'],
            'referring_doctor' => ['nullable', 'string', 'max:255'],
            'nicu_days' => ['nullable', 'integer', 'between:0,400'],
            'respiratory_support' => ['nullable', Rule::enum(RespiratorySupport::class)],
            'support_days' => ['nullable', 'integer', 'between:0,400'],
            'o2_days' => ['nullable', 'integer', 'between:0,400'],
            'cpap_days' => ['nullable', 'integer', 'between:0,400'],
            'illnesses' => ['nullable', 'array', 'max:30'],
            'illnesses.*' => ['string', 'distinct', 'max:100'],
            'systemic_illness' => ['nullable', 'string', 'max:2000'],
            'phone' => ['nullable', 'string', 'max:40'],
            'phone_alt' => ['nullable', 'string', 'max:40'],
            'address' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:10000'],
            'status' => ['required', Rule::enum(PatientStatus::class)],
            'visit' => ['nullable', 'array'],
            'visit_id' => [
                'nullable',
                'integer',
                Rule::exists('visits', 'id')
                    ->where('patient_id', $patient instanceof Patient ? $patient->id : null)
                    ->withoutTrashed(),
            ],
            ...(is_array($this->input('visit')) ? VisitRequest::fieldRules('visit.') : []),
        ];
    }

    /**
     * Name the fields of the first examination as they are named on the visit form.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return collect(array_keys(VisitRequest::fieldRules()))
            ->mapWithKeys(fn (string $field): array => [
                "visit.{$field}" => Lang::has("validation.attributes.{$field}")
                    ? (string) __("validation.attributes.{$field}")
                    : str_replace('_', ' ', $field),
            ])
            ->all();
    }

    /**
     * Get the validated fields of the patient itself.
     *
     * @return array<string, mixed>
     */
    public function patientAttributes(): array
    {
        return $this->safe()->except(['visit', 'visit_id']);
    }

    /**
     * Get the validated first examination, or null when it is not saved with the patient.
     *
     * @return array<string, mixed>|null
     */
    public function visitAttributes(): ?array
    {
        $visit = $this->validated('visit');

        return is_array($visit) ? $visit : null;
    }
}
