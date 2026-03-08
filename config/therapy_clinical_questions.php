<?php

return [
    'care_context' => [
        [
            'question_key' => 'care_followed_before',
            'question_text' => 'Paziente già seguito in terapia per questa condizione?',
            'answer_type' => 'boolean',
            'options' => null,
        ],
        [
            'question_key' => 'caregiver_present',
            'question_text' => 'Caregiver presente?',
            'answer_type' => 'boolean',
            'options' => null,
        ],
        [
            'question_key' => 'initial_criticalities',
            'question_text' => 'Criticità iniziali riferite dal paziente',
            'answer_type' => 'text',
            'options' => null,
        ],
    ],
    'general_anamnesis' => [
        [
            'question_key' => 'relevant_comorbidities',
            'question_text' => 'Comorbidità rilevanti segnalate?',
            'answer_type' => 'text',
            'options' => null,
        ],
        [
            'question_key' => 'known_allergies',
            'question_text' => 'Allergie o intolleranze note?',
            'answer_type' => 'text',
            'options' => null,
        ],
    ],
    'biometric_info' => [
        [
            'question_key' => 'weight_kg',
            'question_text' => 'Peso (kg)',
            'answer_type' => 'number',
            'options' => null,
        ],
        [
            'question_key' => 'height_cm',
            'question_text' => 'Altezza (cm)',
            'answer_type' => 'number',
            'options' => null,
        ],
        [
            'question_key' => 'bmi',
            'question_text' => 'BMI / IMC calcolato',
            'answer_type' => 'number',
            'options' => null,
            'is_readonly' => true,
        ],
    ],
    'detailed_intake' => [
        [
            'question_key' => 'therapy_schema_current',
            'question_text' => 'Schema terapeutico attuale riferito dal paziente',
            'answer_type' => 'text',
            'options' => null,
        ],
        [
            'question_key' => 'therapy_intake_difficulty',
            'question_text' => 'Difficoltà dichiarate nell\'assunzione',
            'answer_type' => 'single_choice',
            'options' => ['Nessuna', 'Occasionale dimenticanza', 'Difficoltà frequente'],
        ],
    ],
    'adherence_base' => [
        [
            'question_key' => 'adherence_declared',
            'question_text' => 'Il paziente dichiara aderenza regolare?',
            'answer_type' => 'boolean',
            'options' => null,
        ],
        [
            'question_key' => 'monitoring_motivation',
            'question_text' => 'Motivazione al percorso di monitoraggio',
            'answer_type' => 'text',
            'options' => null,
        ],
    ],
    'flags' => [
        [
            'question_key' => 'clinical_alert_flags',
            'question_text' => 'Presenti segnali di allerta clinica?',
            'answer_type' => 'boolean',
            'options' => null,
        ],
        [
            'question_key' => 'intervention_priority',
            'question_text' => 'Priorità di intervento',
            'answer_type' => 'single_choice',
            'options' => ['Bassa', 'Media', 'Alta'],
        ],
    ],
];
