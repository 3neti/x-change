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
        <h1>X-Change is being commissioned</h1>
        <p>The service is not accepting sign-ins, claims, payments, or provider messages yet.</p>
        <dl>
            <div><dt>State</dt><dd>{{ str($state)->replace('_', ' ')->title() }}</dd></div>
            <div><dt>Safety</dt><dd>Financial operations locked</dd></div>
        </dl>
        <p class="operator">Deployment operators can complete the protected server checklist.</p>
    </section>
</main>
</body>
</html>
