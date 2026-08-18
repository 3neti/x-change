<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow,noarchive">
    <title>Download encrypted instance keepsake</title>
    <link rel="stylesheet" href="{{ route('x-change.commissioning.assets.css') }}">
</head>
<body>
<main>
    <section>
        <p class="eyebrow">Private export</p>
        <h1>Download encrypted instance keepsake</h1>
        <p>This link works once. Save the encrypted archive before leaving this page. Your private key remains on your computer.</p>
        <dl>
            <div><dt>Reference</dt><dd><code>{{ $reference }}</code></dd></div>
            <div><dt>Expires</dt><dd>{{ $expiresAt->timezone(config('app.timezone'))->toDayDateTimeString() }}</dd></div>
        </dl>
        <form method="post" action="{{ route('x-change.cockpit.instance-keepsakes.download', ['reference' => $reference]) }}">
            @csrf
            <button type="submit">Download encrypted archive</button>
        </form>
    </section>
</main>
</body>
</html>
