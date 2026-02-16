<?php

namespace App\Http\Requests;

use App\Models\Patient;
use App\Models\Assistant;
use App\Tenancy\CurrentPharmacy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TherapyUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'patient_id' => ['sometimes', 'integer', Rule::exists(Patient::class, 'id')->where(fn ($query) => $query->where('pharmacy_id', app(CurrentPharmacy::class)->getId()))],
            'therapy_title' => ['sometimes', 'string', 'max:255'],
            'therapy_description' => ['sometimes', 'nullable', 'string'],
            'risk_score' => ['sometimes', 'nullable', 'numeric', 'between:0,100'],
            'status' => ['sometimes', Rule::in(['active', 'planned', 'completed', 'suspended'])],
            'start_date' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'end_date' => ['sometimes', 'nullable', 'date_format:Y-m-d'],

            'primary_condition' => ['sometimes', 'nullable', 'string', 'max:50'],
            'chronic_care' => ['sometimes', 'array'],
            'chronic_care.general_anamnesis' => ['nullable', 'array'],
            'chronic_care.detailed_intake' => ['nullable', 'array'],
            'chronic_care.adherence_base' => ['nullable', 'array'],
            'chronic_care.doctor_info' => ['nullable', 'array'],
            'chronic_care.biometric_info' => ['nullable', 'array'],
            'chronic_care.care_context' => ['nullable', 'array'],
            'chronic_care.flags' => ['nullable', 'array'],

            'consent' => ['sometimes', 'array'],
            'consent.signer_name' => ['required_with:consent', 'string', 'max:150'],
            'consent.signer_relation' => ['required_with:consent', Rule::in(['patient', 'caregiver', 'familiare'])],
            'consent.consent_text' => ['required_with:consent', 'string'],
            'consent.signed_at' => ['nullable', 'date'],
            'consent.scopes_json' => ['nullable', 'array'],

            'survey' => ['sometimes', 'array'],
            'survey.condition_type' => ['required_with:survey', 'string', 'max:50'],
            'survey.level' => ['required_with:survey', Rule::in(['base', 'approfondito'])],
            'survey.answers' => ['nullable', 'array'],

            'assistant_ids' => ['sometimes', 'array'],
            'assistant_ids.*' => [
                'integer',
                'distinct',
                Rule::exists(Assistant::class, 'id')->where(fn ($query) => $query->where('pharmacy_id', app(CurrentPharmacy::class)->getId())),
            ],
        ];
    }
}
