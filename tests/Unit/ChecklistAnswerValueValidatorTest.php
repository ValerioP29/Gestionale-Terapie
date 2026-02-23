<?php

namespace Tests\Unit;

use App\Services\Therapies\Followups\ChecklistAnswerValueValidator;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\TestCase;

class ChecklistAnswerValueValidatorTest extends TestCase
{
    private ChecklistAnswerValueValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->validator = new ChecklistAnswerValueValidator();
    }

    public function test_boolean_values_are_normalized(): void
    {
        $this->assertTrue($this->validator->validateAndNormalize('true', 'boolean'));
        $this->assertFalse($this->validator->validateAndNormalize('0', 'boolean'));
    }

    public function test_number_requires_numeric_value(): void
    {
        $this->assertSame('10.5', $this->validator->validateAndNormalize('10.5', 'number'));

        $this->expectException(ValidationException::class);
        $this->validator->validateAndNormalize('abc', 'number');
    }

    public function test_date_requires_valid_y_m_d(): void
    {
        $this->assertSame('2026-03-10', $this->validator->validateAndNormalize('2026-03-10', 'date'));

        $this->expectException(ValidationException::class);
        $this->validator->validateAndNormalize('10/03/2026', 'date');
    }

    public function test_select_accepts_only_declared_options(): void
    {
        $this->assertSame('ottima', $this->validator->validateAndNormalize('ottima', 'select', ['ottima', 'parziale']));

        $this->expectException(ValidationException::class);
        $this->validator->validateAndNormalize('scarsa', 'select', ['ottima', 'parziale']);
    }

    public function test_text_requires_scalar_value(): void
    {
        $this->assertSame('nota', $this->validator->validateAndNormalize(' nota ', 'text'));

        $this->expectException(ValidationException::class);
        $this->validator->validateAndNormalize(['nota'], 'text');
    }
}
