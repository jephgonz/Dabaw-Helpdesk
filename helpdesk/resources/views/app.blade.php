<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title inertia>{{ config('app.name', 'Jephthah Helpdesk') }}</title>
    <link rel="icon" href="{{ asset('assets/images/SP.ico') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/app.css') }}">
    <script>window.__APP_BASE_PATH__ = @json(rtrim(request()->getBaseUrl(), '/'));</script>
    @vite('resources/js/app.jsx')
    @inertiaHead
</head>
<body>
    @inertia
</body>
</html>
