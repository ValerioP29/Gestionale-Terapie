<?php

namespace App\Http\Requests;

use App\Models\Patient;
use App\Models\Assistant;
use App\Tenancy\CurrentPharmacy;
use App\Support\ConditionKeyNormalizer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TherapyStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'patient_id' => ['required', 'integer', Rule::exists(Patient::class, 'id')->where(fn ($query) => $query->where('pharmacy_id', app(CurrentPharmacy::class)->getId()))],
            'therapy_title' => ['required', 'string', 'max:255'],
            'therapy_description' => ['nullable', 'string'],
            'risk_score' => ['nullable', 'numeric', 'between:0,100'],
            'status' => ['nullable', Rule::in(['active', 'planned', 'completed', 'suspended'])],
            'start_date' => ['nullable', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d'],

            'primary_condition' => ['required', 'string'],
            'custom_condition_name' => ['nullable', 'string', 'max:120'],
            'chronic_care' => ['nullable', 'array'],
            'chronic_care.general_anamnesis' => ['nullable', 'array'],
            'chronic_care.detailed_intake' => ['nullable', 'array'],
            'chronic_care.adherence_base' => ['nullable', 'array'],
            'chronic_care.doctor_info' => ['nullable', 'array'],
            'chronic_care.biometric_info' => ['nullable', 'array'],
            'chronic_care.care_context' => ['nullable', 'array'],
            'chronic_care.flags' => ['nullable', 'array'],

            'consent' => ['nullable', 'array'],
            'consent.signer_name' => ['required_with:consent', 'string', 'max:150'],
            'consent.signer_relation' => ['required_with:consent', Rule::in(['patient', 'caregiver', 'familiare'])],
            'consent.consent_text' => ['required_with:consent', 'string'],
            'consent.signed_at' => ['required_with:consent', 'date'],
            'consent.scopes_json' => ['required_with:consent', 'array'],
            'consent.scopes_json.*' => ['string', Rule::in(['privacy', 'marketing', 'profiling', 'clinical_data'])],

            'survey' => ['nullable', 'array'],
            'survey.condition_type' => ['nullable', 'string'],
            'survey.level' => ['required_with:survey', Rule::in(['base', 'approfondito'])],
            'survey.base_questions' => ['nullable', 'array'],
            'survey.approfondito_questions' => ['nullable', 'array'],

            'assistant_ids' => ['nullable', 'array'],
            'assistant_ids.*' => [
                'integer',
                'distinct',
                Rule::exists(Assistant::class, 'id')->where(fn ($query) => $query->where('pharmacy_id', app(CurrentPharmacy::class)->getId())),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'patient_id.required' => 'Seleziona un paziente valido.',
            'primary_condition.required' => 'La condizione clinica principale è obbligatoria.',
            'primary_condition.in' => 'Seleziona una condizione clinica valida.',
            'consent.signer_name.required_with' => 'Inserisci il nominativo del firmatario.',
            'consent.signer_relation.required_with' => 'Seleziona il ruolo del firmatario.',
            'consent.consent_text.required_with' => 'Inserisci il testo del consenso.',
            'consent.signed_at.required_with' => 'Indica data e ora della firma del consenso.',
            'consent.scopes_json.required_with' => 'Seleziona i consensi obbligatori per completare la presa in carico.',
        ];
    }
}
