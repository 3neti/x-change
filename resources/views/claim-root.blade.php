<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        @if (is_array($claimShareMetadata ?? null))
            <meta name="description" content="{{ $claimShareMetadata['description'] }}">
            <meta property="og:type" content="website">
            <meta property="og:site_name" content="{{ $claimShareMetadata['site_name'] }}">
            <meta property="og:title" content="{{ $claimShareMetadata['title'] }}">
            <meta property="og:description" content="{{ $claimShareMetadata['description'] }}">
            <meta property="og:url" content="{{ $claimShareMetadata['url'] }}">
            <link rel="canonical" href="{{ $claimShareMetadata['url'] }}">
            <meta name="twitter:card" content="summary_large_image">
            <meta name="twitter:title" content="{{ $claimShareMetadata['title'] }}">
            <meta name="twitter:description" content="{{ $claimShareMetadata['description'] }}">

            @if (filled($claimShareMetadata['image_url'] ?? null))
                <meta property="og:image" content="{{ $claimShareMetadata['image_url'] }}">
                @if (str_starts_with($claimShareMetadata['image_url'], 'https://'))
                    <meta property="og:image:secure_url" content="{{ $claimShareMetadata['image_url'] }}">
                @endif
                <meta property="og:image:type" content="image/png">
                <meta property="og:image:width" content="1200">
                <meta property="og:image:height" content="630">
                <meta property="og:image:alt" content="{{ $claimShareMetadata['image_alt'] }}">
                <meta name="twitter:image" content="{{ $claimShareMetadata['image_url'] }}">
                <meta name="twitter:image:alt" content="{{ $claimShareMetadata['image_alt'] }}">
            @endif
        @endif

        <link rel="icon" href="/vendor/x-change/favicon.ico" sizes="any">
        <link rel="icon" href="/vendor/x-change/favicon.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/vendor/x-change/apple-touch-icon.png">

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet">

        @vite(['resources/css/app.css', 'resources/js/app.ts', "resources/js/pages/{$page['component']}.vue"])
        <x-inertia::head>
            <title>{{ $claimShareMetadata['title'] ?? config('x-change.branding.name', 'X-Change') }}</title>
        </x-inertia::head>
    </head>
    <body class="font-sans antialiased">
        <x-inertia::app />
    </body>
</html>
