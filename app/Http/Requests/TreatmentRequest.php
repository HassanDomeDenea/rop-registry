<?php

namespace App\Http\Requests;

use App\Enums\EyeSide;
use App\Enums\TreatmentType;
use App\Models\Patient;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TreatmentRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Patient $patient */
        $patient = $this->route('patient');

        return [
            'type' => ['required', Rule::enum(TreatmentType::class)],
            'eye' => ['required', Rule::enum(EyeSide::class)],
            'performed_date' => ['nullable', 'date', 'before_or_equal:today'],
            'visit_id' => ['nullable', 'integer', Rule::exists('visits', 'id')->where('patient_id', $patient->id)],
            'agent' => ['nullable', 'string', 'max:255'],
            'performed_by' => ['nullable', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
