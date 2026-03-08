<?php

namespace App\Support;

class ConditionKeyNormalizer
{
    public const CUSTOM_PREFIX = 'custom:';

    /** @return array<string, string> */
    public static function options(): array
    {
        return [
            'diabete' => 'Diabete',
            'bpco' => 'BPCO',
            'ipertensione' => 'Ipertensione',
            'dislipidemia' => 'Dislipidemia',
            'altro' => 'Altro / non classificato',
        ];
    }

    public static function normalize(?string $value): string
    {
        $raw = trim((string) $value);

        if ($raw === '') {
            return 'altro';
        }

        $lower = mb_strtolower($raw);

        if (str_starts_with($lower, self::CUSTOM_PREFIX)) {
            return $lower;
        }

        $map = [
            'diabete' => 'diabete',
            'diabetico' => 'diabete',
            'bpco' => 'bpco',
            'bpco/copd' => 'bpco',
            'copd' => 'bpco',
            'ipertensione' => 'ipertensione',
            'iperteso' => 'ipertensione',
            'dislipidemia' => 'dislipidemia',
            'ipercolesterolemia' => 'dislipidemia',
            'altro' => 'altro',
            'other' => 'altro',
            'fidelizzazione' => 'altro',
            'unspecified' => 'altro',
        ];

        return $map[$lower] ?? 'altro';
    }

    public static function customKeyFromName(?string $value): string
    {
        $name = trim((string) $value);

        if ($name === '') {
            return 'altro';
        }

        $slug = trim((string) preg_replace('/[^a-z0-9]+/i', '-', mb_strtolower($name)), '-');

        return $slug === '' ? 'altro' : self::CUSTOM_PREFIX.$slug;
    }

    public static function isCustom(?string $value): bool
    {
        return str_starts_with(trim((string) $value), self::CUSTOM_PREFIX);
    }
}
