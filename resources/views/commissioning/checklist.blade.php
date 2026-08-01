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
        <p class="checked">Checked {{ $checkedAt->toDayDateTimeString() }}</p>
        <nav class="actions" aria-label="Commissioning actions">
            <a class="button secondary" href="{{ route('x-change.commissioning.checklist') }}">Run checks again</a>
            @if ($commissioning->isOperational())
                <a class="button" href="{{ route('x-change.cockpit.dashboard') }}">Open Cockpit</a>
            @endif
        </nav>
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
            @foreach ([...$readiness['checks'], ...$installationChecks] as $check)
                <li><strong>{{ $check['passed'] ? 'Ready' : 'Action needed' }}</strong> · {{ str($check['name'])->title() }} — {{ $check['message'] }}</li>
            @endforeach
        </ul>
        @if ($readiness['missing_variables'] !== [])
            <h2>Missing variables</h2>
            <ul>@foreach ($readiness['missing_variables'] as $variable)<li><code>{{ $variable }}</code></li>@endforeach</ul>
        @endif
        <h2>Runtime processes</h2>
        <p>Configuration checks cannot prove that workers are running. Keep these responsibilities active after deployment.</p>
        <h3>Required queues</h3>
        <p><code>{{ implode(', ', $runtime['queues']) }}</code></p>
        <h3>Local development</h3>
        <ul>
            <li><code>{{ $runtime['local']['queue'] }}</code></li>
            <li><code>{{ $runtime['local']['scheduler'] }}</code></li>
            <li><code>{{ $runtime['local']['reverb'] }}</code> — {{ $runtime['broadcasting_required'] ? 'required by the active Reverb configuration' : 'optional while funding broadcasts are disabled' }}</li>
        </ul>
        <h3>Laravel Cloud</h3>
        <ul>@foreach ($runtime['cloud'] as $instruction)<li>{{ $instruction }}</li>@endforeach</ul>
        <h3>Laravel Forge</h3>
        <ul>@foreach ($runtime['forge'] as $instruction)<li>{{ $instruction }}</li>@endforeach</ul>
    </section>
</main>
</body>
</html>
