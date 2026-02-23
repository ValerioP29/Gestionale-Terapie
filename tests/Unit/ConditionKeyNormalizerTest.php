<?php

namespace Tests\Unit;

use App\Support\ConditionKeyNormalizer;
use PHPUnit\Framework\TestCase;

class ConditionKeyNormalizerTest extends TestCase
{
    public function test_it_normalizes_known_conditions(): void
    {
        $this->assertSame('diabete', ConditionKeyNormalizer::normalize('Diabete'));
        $this->assertSame('bpco', ConditionKeyNormalizer::normalize('copd'));
        $this->assertSame('ipertensione', ConditionKeyNormalizer::normalize('IPERTENSIONE'));
        $this->assertSame('dislipidemia', ConditionKeyNormalizer::normalize('ipercolesterolemia'));
    }

    public function test_it_falls_back_to_altro_for_unknown_values(): void
    {
        $this->assertSame('altro', ConditionKeyNormalizer::normalize('')); 
        $this->assertSame('altro', ConditionKeyNormalizer::normalize('non classificata'));
        $this->assertSame('altro', ConditionKeyNormalizer::normalize('unspecified'));
    }
}
