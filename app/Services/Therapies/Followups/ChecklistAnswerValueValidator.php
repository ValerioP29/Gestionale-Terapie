<?php

namespace App\Services\Therapies\Followups;

use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

class ChecklistAnswerValueValidator
{
    /**
     * @param array<int, string>|null $options
     */
    public function validateAndNormalize(mixed $value, string $inputType, ?array $options = null): mixed
    {
        if ($value === null) {
            return null;
        }

        return match ($inputType) {
            'boolean' => $this->normalizeBoolean($value),
            'number' => $this->normalizeNumber($value),
            'date' => $this->normalizeDate($value),
            'select' => $this->normalizeSelect($value, $options),
            'text' => $this->normalizeText($value),
            default => $this->normalizeText($value),
        };
    }

    private function normalizeBoolean(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return match ((int) $value) {
                1 => true,
                0 => false,
                default => throw $this->error('Il valore deve essere booleano (true/false).'),
            };
        }

        if (is_string($value)) {
            $normalized = strtolower(trim($value));

            return match ($normalized) {
                '1', 'true', 'yes', 'on' => true,
                '0', 'false', 'no', 'off' => false,
                default => throw $this->error('Il valore deve essere booleano (true/false).'),
            };
        }

        throw $this->error('Il valore deve essere booleano (true/false).');
    }

    private function normalizeNumber(mixed $value): string
    {
        if (! is_numeric($value)) {
            throw $this->error('Il valore deve essere numerico.');
        }

        return (string) $value;
    }

    private function normalizeDate(mixed $value): string
    {
        if (! is_string($value)) {
            throw $this->error('Il valore deve essere una data valida (Y-m-d).');
        }

        try {
            return CarbonImmutable::createFromFormat('Y-m-d', trim($value))->format('Y-m-d');
        } catch (\Throwable) {
            throw $this->error('Il valore deve essere una data valida (Y-m-d).');
        }
    }

    /**
     * @param array<int, string>|null $options
     */
    private function normalizeSelect(mixed $value, ?array $options): string
    {
        if (! is_scalar($value)) {
            throw $this->error('Il valore selezionato non è valido.');
        }

        $candidate = trim((string) $value);

        $allowed = collect($options ?? [])
            ->map(static fn (mixed $option): string => trim((string) $option))
            ->filter(static fn (string $option): bool => $option !== '')
            ->values()
            ->all();

        if (! in_array($candidate, $allowed, true)) {
            throw $this->error('Il valore selezionato non è tra le opzioni consentite.');
        }

        return $candidate;
    }

    private function normalizeText(mixed $value): string
    {
        if (is_array($value) || is_object($value)) {
            throw $this->error('Il valore deve essere testuale.');
        }

        return trim((string) $value);
    }

    private function error(string $message): ValidationException
    {
        return ValidationException::withMessages([
            'answers' => $message,
        ]);
    }
}
