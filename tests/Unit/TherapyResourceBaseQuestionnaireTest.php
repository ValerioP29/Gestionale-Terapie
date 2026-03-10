<?php

namespace Tests\Unit;

use App\Filament\Resources\TherapyResource;
use Tests\TestCase;

class TherapyResourceBaseQuestionnaireTest extends TestCase
{
    public function test_bmi_is_computed_from_weight_and_height(): void
    {
        $method = new \ReflectionMethod(TherapyResource::class, 'applyBaseSectionDerivedValues');
        $method->setAccessible(true);

        $sections = $method->invoke(null, [[
            'section' => 'Dati biometrici',
            'questions' => [
                ['question_key' => 'weight_kg', 'answer' => 80],
                ['question_key' => 'height_cm', 'answer' => 200],
                ['question_key' => 'bmi', 'answer' => null],
            ],
        ]]);

        $this->assertSame(20.0, (float) $sections[0]['questions'][2]['answer']);
    }
}
