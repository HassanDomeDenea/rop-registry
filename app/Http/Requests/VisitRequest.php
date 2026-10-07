<?php

namespace App\Http\Requests;

use App\Enums\ManagementPlan;
use App\Enums\PlusDisease;
use App\Enums\RopStatus;
use App\Enums\RopType;
use App\Enums\Stage;
use App\Enums\VisitKind;
use App\Enums\Zone;
use App\Models\Visit;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VisitRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [
            'kind' => ['required', Rule::enum(VisitKind::class)],
            'visit_date' => ['nullable', 'date', 'before_or_equal:today'],
            'examiner' => ['nullable', 'string', 'max:255'],
            'assessment' => ['nullable', 'string', 'max:5000'],
            'management_plan' => ['nullable', Rule::enum(ManagementPlan::class)],
            'management_notes' => ['nullable', 'string', 'max:5000'],
            'next_visit_date' => ['nullable', 'date', 'after_or_equal:visit_date'],
            'fee' => ['nullable', 'integer', 'min:0', 'max:100000000'],
            'notes' => ['nullable', 'string', 'max:10000'],
        ];

        foreach (Visit::EYES as $eye) {
            $rules += [
                "{$eye}_dilatation" => ['nullable', 'string', 'max:100'],
                "{$eye}_lens" => ['nullable', 'string', 'max:100'],
                "{$eye}_plus" => ['nullable', Rule::enum(PlusDisease::class)],
                "{$eye}_zone" => ['nullable', Rule::enum(Zone::class)],
                "{$eye}_stage" => ['nullable', Rule::enum(Stage::class)],
                "{$eye}_a_rop" => ['nullable', 'boolean'],
                "{$eye}_rop_type" => ['nullable', Rule::enum(RopType::class)],
                "{$eye}_rop_status" => ['nullable', Rule::enum(RopStatus::class)],
                "{$eye}_notes" => ['nullable', 'string', 'max:2000'],
            ];
        }

        return $rules;
    }
}
