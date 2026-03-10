<?php

namespace Tests\Unit;

use App\Filament\Resources\TherapyResource\Pages\EditTherapy;
use Tests\TestCase;

class TherapyEditPageMappingTest extends TestCase
{
    public function test_legacy_base_questions_are_mapped_to_sections_for_ui_fill(): void
    {
        $page = app(EditTherapy::class);
        $method = new \ReflectionMethod($page, 'questionsToSections');
        $method->setAccessible(true);

        $sections = $method->invoke($page, [
            [
                'section' => 'Anamnesi generale',
                'question_key' => 'fumo',
                'question_label' => 'Il paziente fuma?',
                'input_type' => 'select',
                'options_json' => ['Sì', 'No'],
                'answer' => 'No',
            ],
            [
                'section' => 'Dati biometrici',
                'question_key' => 'weight_kg',
                'question_label' => 'Peso',
                'input_type' => 'number',
                'answer' => 72,
            ],
        ]);

        $this->assertCount(2, $sections);
        $this->assertSame('Anamnesi generale', $sections[0]['section']);
        $this->assertSame('fumo', $sections[0]['questions'][0]['question_key']);
        $this->assertSame('No', $sections[0]['questions'][0]['answer']);
    }

    public function test_legacy_approfondito_flat_rows_are_mapped_to_sections_for_ui_fill(): void
    {
        $page = app(EditTherapy::class);
        $method = new \ReflectionMethod($page, 'questionsToSections');
        $method->setAccessible(true);

        $sections = $method->invoke($page, [
            [
                'section' => 'Approfondito personalizzato',
                'question_key' => 'q_custom',
                'question_label' => 'Sintomo notturno',
                'input_type' => 'boolean',
                'answer' => '1',
            ],
        ]);

        $this->assertCount(1, $sections);
        $this->assertSame('Approfondito personalizzato', $sections[0]['section']);
        $this->assertSame('q_custom', $sections[0]['questions'][0]['question_key']);
    }
}
