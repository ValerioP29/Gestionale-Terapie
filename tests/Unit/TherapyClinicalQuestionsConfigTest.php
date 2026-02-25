<?php

namespace Tests\Unit;

use Tests\TestCase;

class TherapyClinicalQuestionsConfigTest extends TestCase
{
    public function test_clinical_questions_defaults_have_required_schema(): void
    {
        $sections = config('therapy_clinical_questions');

        $this->assertIsArray($sections);
        $this->assertArrayHasKey('care_context', $sections);

        foreach ($sections as $questions) {
            $this->assertIsArray($questions);

            foreach ($questions as $question) {
                $this->assertIsArray($question);
                $this->assertArrayHasKey('question_text', $question);
                $this->assertArrayHasKey('answer_type', $question);
                $this->assertArrayHasKey('options', $question);
                $this->assertContains($question['answer_type'], ['text', 'boolean', 'single_choice']);
            }
        }
    }
}
