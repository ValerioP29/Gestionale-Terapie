<?php

return [
    'care_context' => [
        [
            'question_text' => 'Paziente già seguito in terapia per questa condizione?',
            'answer_type' => 'boolean',
            'options' => null,
        ],
        [
            'question_text' => 'Caregiver presente?',
            'answer_type' => 'boolean',
            'options' => null,
        ],
        [
            'question_text' => 'Criticità iniziali riferite dal paziente',
            'answer_type' => 'text',
            'options' => null,
        ],
    ],
    'general_anamnesis' => [
        [
            'question_text' => 'Comorbidità rilevanti segnalate?',
            'answer_type' => 'text',
            'options' => null,
        ],
        [
            'question_text' => 'Allergie o intolleranze note?',
            'answer_type' => 'text',
            'options' => null,
        ],
    ],
    'biometric_info' => [
        [
            'question_text' => 'Valori pressori iniziali disponibili?',
            'answer_type' => 'boolean',
            'options' => null,
        ],
        [
            'question_text' => 'Peso rilevato al primo accesso',
            'answer_type' => 'text',
            'options' => null,
        ],
    ],
    'detailed_intake' => [
        [
            'question_text' => 'Schema terapeutico attuale riferito dal paziente',
            'answer_type' => 'text',
            'options' => null,
        ],
        [
            'question_text' => 'Difficoltà dichiarate nell\'assunzione',
            'answer_type' => 'single_choice',
            'options' => ['Nessuna', 'Occasionale dimenticanza', 'Difficoltà frequente'],
        ],
    ],
    'adherence_base' => [
        [
            'question_text' => 'Il paziente dichiara aderenza regolare?',
            'answer_type' => 'boolean',
            'options' => null,
        ],
        [
            'question_text' => 'Motivazione al percorso di monitoraggio',
            'answer_type' => 'text',
            'options' => null,
        ],
    ],
    'flags' => [
        [
            'question_text' => 'Presenti segnali di allerta clinica?',
            'answer_type' => 'boolean',
            'options' => null,
        ],
        [
            'question_text' => 'Priorità di intervento',
            'answer_type' => 'single_choice',
            'options' => ['Bassa', 'Media', 'Alta'],
        ],
    ],
];
