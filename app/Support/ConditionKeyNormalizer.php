<?php

namespace App\Support;

class ConditionKeyNormalizer
{
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
}
