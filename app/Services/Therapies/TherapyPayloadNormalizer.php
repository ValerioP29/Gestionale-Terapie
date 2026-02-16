<?php

namespace App\Services\Therapies;

use Carbon\Carbon;

class TherapyPayloadNormalizer
{
    /** @var array<int, string> */
    private const CHRONIC_CARE_BLOCKS = [
        'general_anamnesis',
        'detailed_intake',
        'adherence_base',
        'doctor_info',
        'biometric_info',
        'care_context',
        'flags',
    ];

    public function normalize(array $payload): array
    {
        $normalized = $this->normalizeValue($payload);

        if (! is_array($normalized)) {
            return [];
        }

        if (isset($normalized['chronic_care']) && is_array($normalized['chronic_care'])) {
            $normalized['chronic_care'] = $this->normalizeChronicCare($normalized['chronic_care']);
        }

        if (isset($normalized['consent']) && is_array($normalized['consent'])) {
            $normalized['consent'] = $this->normalizeValue($normalized['consent']);
        }

        if (isset($normalized['survey']) && is_array($normalized['survey'])) {
            $normalized['survey'] = $this->normalizeValue($normalized['survey']);
        }

        return $normalized;
    }

    /** @return array<string, mixed> */
    public function normalizeChronicCare(array $chronicCare): array
    {
        $normalized = [];

        foreach (self::CHRONIC_CARE_BLOCKS as $block) {
            if (! array_key_exists($block, $chronicCare) || ! is_array($chronicCare[$block])) {
                continue;
            }

            $cleanBlock = $this->normalizeValue($chronicCare[$block]);

            if (is_array($cleanBlock) && $cleanBlock !== []) {
                $normalized[$block] = $cleanBlock;
            }
        }

        return $normalized;
    }

    /**
     * @param mixed $value
     * @return mixed
     */
    private function normalizeValue(mixed $value): mixed
    {
        if (is_array($value)) {
            $normalized = [];

            foreach ($value as $key => $item) {
                $normalizedItem = $this->normalizeValue($item);

                if ($this->isEmptyValue($normalizedItem)) {
                    continue;
                }

                if (is_string($key)) {
                    $normalizedItem = $this->normalizeDateLikeValue($key, $normalizedItem);
                }

                $normalized[$key] = $normalizedItem;
            }

            return $normalized;
        }

        if (is_string($value)) {
            $trimmed = trim($value);

            if ($trimmed === '') {
                return null;
            }

            return $this->coerceBool($trimmed);
        }

        return $value;
    }

    private function normalizeDateLikeValue(string $key, mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        if (! str_contains($key, 'date') && ! str_ends_with($key, '_at')) {
            return $value;
        }

        try {
            return Carbon::createFromFormat('Y-m-d', $value)->format('Y-m-d');
        } catch (\Throwable) {
            return $value;
        }
    }

    private function coerceBool(string $value): mixed
    {
        $lower = strtolower($value);

        return match ($lower) {
            'true', '1', 'yes', 'on' => true,
            'false', '0', 'no', 'off' => false,
            default => $value,
        };
    }

    private function isEmptyValue(mixed $value): bool
    {
        if ($value === null) {
            return true;
        }

        return is_array($value) && $value === [];
    }
}
