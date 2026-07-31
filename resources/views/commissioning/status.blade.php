<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow,noarchive">
    <title>X-Change commissioning</title>
    <link rel="stylesheet" href="{{ route('x-change.commissioning.assets.css') }}">
</head>
<body>
<main>
    <section>
        <p class="eyebrow">Settlement Operating System</p>
        <h1>{{ $commissioning->isOperational() ? 'X-Change is ready' : 'X-Change is being commissioned' }}</h1>
        <p>{{ $commissioning->isOperational() ? 'Commissioning checks are complete.' : 'The service is not accepting sign-ins, claims, payments, or provider messages yet.' }}</p>
        <dl>
            <div><dt>State</dt><dd>{{ str($commissioning->state->value)->replace('_', ' ')->title() }}</dd></div>
            <div><dt>Safety</dt><dd>{{ $commissioning->isOperational() ? 'Operational controls active' : 'Financial operations locked' }}</dd></div>
        </dl>
        <p class="checked">Checked {{ $checkedAt->toDayDateTimeString() }}</p>
        <nav class="actions" aria-label="Commissioning actions">
            <a class="button secondary" href="{{ route('x-change.commissioning.status') }}">Run checks again</a>
            @if ($commissioning->isOperational())
                <a class="button" href="{{ route('x-change.cockpit.dashboard') }}">Open Cockpit</a>
            @endif
        </nav>
        @unless ($commissioning->isOperational())
            <p class="operator">Deployment operators can complete the protected server checklist.</p>
            <form method="post" action="{{ route('x-change.commissioning.checklist.unlock') }}">
                @csrf
                <label for="access_token">Operator access token</label>
                <input id="access_token" name="access_token" type="password" autocomplete="off" required>
                <button type="submit">Open checklist</button>
            </form>
        @endunless
    </section>
</main>
</body>
</html>
