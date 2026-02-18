<?php

namespace App\Filament\Resources\TherapyResource\Pages;

use App\Filament\Resources\TherapyResource;
use App\Models\Therapy;
use App\Services\Therapies\UpdateTherapyService;
use Filament\Resources\Pages\EditRecord;

class EditTherapy extends EditRecord
{
    protected static string $resource = TherapyResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var Therapy $record */
        $record = $this->record->load(['currentChronicCare', 'latestSurvey', 'latestConsent', 'assistants']);

        $data['primary_condition'] = $record->currentChronicCare?->primary_condition;
        $data['risk_score'] = $record->currentChronicCare?->risk_score;
        $data['follow_up_date'] = $record->currentChronicCare?->follow_up_date;
        $data['notes_initial'] = $record->currentChronicCare?->notes_initial;
        $data['chronic_care'] = $record->currentChronicCare?->only([
            'care_context',
            'doctor_info',
            'general_anamnesis',
            'biometric_info',
            'detailed_intake',
            'adherence_base',
            'flags',
        ]) ?? [];
        $data['chronic_consent'] = $record->currentChronicCare?->consent ?? [];

        $data['survey'] = $record->latestSurvey ? [
            'condition_type' => $record->latestSurvey->condition_type,
            'level' => $record->latestSurvey->level,
            'answers' => $record->latestSurvey->answers,
        ] : [];

        $data['consent'] = $record->latestConsent ? [
            'signer_name' => $record->latestConsent->signer_name,
            'signer_relation' => $record->latestConsent->signer_relation,
            'signer_role' => $record->latestConsent->signer_role,
            'consent_text' => $record->latestConsent->consent_text,
            'signed_at' => $record->latestConsent->signed_at,
            'scopes_json' => $record->latestConsent->scopes_json,
        ] : [];

        $data['assistants'] = $record->assistants->map(fn ($assistant): array => [
            'assistant_id' => $assistant->id,
            'role' => $assistant->pivot?->role,
            'contact_channel' => $assistant->pivot?->contact_channel,
            'preferences_json' => $assistant->pivot?->preferences_json,
            'consents_json' => $assistant->pivot?->consents_json,
        ])->values()->all();

        return $data;
    }

    protected function handleRecordUpdate(\Illuminate\Database\Eloquent\Model $record, array $data): \Illuminate\Database\Eloquent\Model
    {
        return app(UpdateTherapyService::class)->handle((int) $record->getKey(), $data);
    }
}
