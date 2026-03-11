<?php

namespace App\Support;

class ThemeRegistry
{
    public static function resolve(string $context = 'frontend'): array
    {
        $themes = config('theme.themes', []);
        $default = config('theme.default', 'clean-medical');
        $requested = config("theme.contexts.{$context}", $default);

        $key = array_key_exists($requested, $themes) ? $requested : $default;

        if (! array_key_exists($key, $themes)) {
            $key = array_key_first($themes);
        }

        return [
            'key' => $key,
            ...($themes[$key] ?? [
                'body_class' => 'theme-clean-medical',
                'css_path' => 'css/themes/theme-clean-medical.css',
            ]),
        ];
    }
}
