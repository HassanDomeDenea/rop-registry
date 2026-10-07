<?php

namespace App\Http\Requests;

use App\Enums\DeliveryMode;
use App\Enums\Multiplicity;
use App\Enums\PatientStatus;
use App\Enums\RespiratorySupport;
use App\Enums\Sex;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
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
        return [
            'file_number' => ['nullable', 'string', 'max:50'],
            'name' => ['required', 'string', 'max:255'],
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
        ];
    }
}
