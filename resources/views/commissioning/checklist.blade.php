<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow,noarchive">
    <title>X-Change deployment checklist</title>
    <link rel="stylesheet" href="{{ route('x-change.commissioning.assets.css') }}">
</head>
<body>
<main>
    <section>
        <p class="eyebrow">Protected operator checklist</p>
        <h1>Commission X-Change</h1>
        <p>Current state: <strong>{{ str($commissioning->state->value)->replace('_', ' ')->title() }}</strong></p>
        <ol>
            <li><code>php artisan x-change:configure --profile={{ $readiness['profile'] }}</code></li>
            <li>Supply the missing deployment secrets and runtime values below.</li>
            <li><code>php artisan optimize:clear</code></li>
            <li><code>php artisan x-change:doctor --pre-install --strict</code></li>
            <li><code>php artisan x-change:install --force --no-interaction</code></li>
            <li><code>php artisan x-change:doctor --strict</code></li>
        </ol>
        <h2>Readiness checks</h2>
        <ul>
            @foreach ($readiness['checks'] as $check)
                <li><strong>{{ $check['passed'] ? 'Ready' : 'Action needed' }}</strong> · {{ str($check['name'])->title() }} — {{ $check['message'] }}</li>
            @endforeach
        </ul>
        @if ($readiness['missing_variables'] !== [])
            <h2>Missing variables</h2>
            <ul>@foreach ($readiness['missing_variables'] as $variable)<li><code>{{ $variable }}</code></li>@endforeach</ul>
        @endif
    </section>
</main>
</body>
</html>
