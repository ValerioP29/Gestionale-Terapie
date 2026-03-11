@props(['context' => 'frontend'])

@php($theme = \App\Support\ThemeRegistry::resolve($context))
<link rel="stylesheet" href="{{ asset('css/app-theme-tokens.css') }}">
<link rel="stylesheet" href="{{ asset('css/app-theme-base.css') }}">
<link rel="stylesheet" href="{{ asset('css/app-theme-components.css') }}">
<link rel="stylesheet" href="{{ asset($theme['css_path']) }}">
