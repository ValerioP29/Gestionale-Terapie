<?php

namespace Tests\Unit;

use App\Filament\Resources\TherapyResource;
use Tests\TestCase;

class TherapyResourceAnswerStateTest extends TestCase
{
    public function test_ui_answer_fields_are_normalized_to_single_answer_path(): void
    {
        $method = new \ReflectionMethod(TherapyResource::class, 'normalizeQuestionnaireSections');
        $method->setAccessible(true);

        $sections = $method->invoke(null, [[
            'section' => 'Anamnesi generale',
            'questions' => [
                [
                    'question_key' => 'fumo',
                    'question_label' => 'Il paziente fuma?',
                    'input_type' => 'select',
                    'options_json' => ['Sì', 'No'],
                    'ui_answer_select' => 'No',
                ],
                [
                    'question_key' => 'attivita',
                    'question_label' => 'Attività fisica',
                    'input_type' => 'multiple_choice',
                    'options_json' => ['Cammino', 'Palestra'],
                    'ui_answer_multiple' => ['Cammino'],
                ],
            ],
        ]]);

        $this->assertSame('No', $sections[0]['questions'][0]['answer']);
        $this->assertSame(['Cammino'], $sections[0]['questions'][1]['answer']);
    }
}
