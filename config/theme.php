<?php

return [
    'default' => env('APP_THEME', 'clean-medical'),

    'contexts' => [
        'frontend' => env('FRONTEND_THEME', env('APP_THEME', 'clean-medical')),
        'panel' => env('PANEL_THEME', env('APP_THEME', 'clean-medical')),
    ],

    'themes' => [
        'clean-medical' => [
            'label' => 'Clean / Medical / Light',
            'body_class' => 'theme-clean-medical',
            'css_path' => 'css/themes/theme-clean-medical.css',
        ],
        'premium-dark' => [
            'label' => 'Modern / Premium / Dark-soft',
            'body_class' => 'theme-premium-dark',
            'css_path' => 'css/themes/theme-premium-dark.css',
        ],
        'soft-green' => [
            'label' => 'Fresh / Pharmacy / Soft-green',
            'body_class' => 'theme-soft-green',
            'css_path' => 'css/themes/theme-soft-green.css',
        ],
    ],
];
