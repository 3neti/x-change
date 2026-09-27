<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <title>{{ $agreement->title }} · {{ config('app.name') }}</title>
    <style>
        :root { color-scheme: light dark; font-family: ui-sans-serif, system-ui, sans-serif; }
        body { margin: 0; background: #f4f5f7; color: #171717; }
        main { width: min(760px, calc(100% - 32px)); margin: 32px auto; }
        .panel { background: #fff; border: 1px solid #dedede; border-radius: 20px; box-shadow: 0 20px 60px rgba(0,0,0,.08); overflow: hidden; }
        header, .agreement, form { padding: 24px; }
        header { border-bottom: 1px solid #e7e7e7; }
        header p, .meta { color: #666; }
        .agreement { max-height: min(60vh, 680px); overflow-y: auto; line-height: 1.65; }
        .agreement h1 { font-size: 1.65rem; }
        .agreement h2 { margin-top: 2rem; font-size: 1.1rem; }
        form { border-top: 1px solid #e7e7e7; }
        label { display: flex; gap: 12px; align-items: flex-start; font-weight: 650; }
        input[type=checkbox] { width: 20px; height: 20px; flex: 0 0 auto; }
        .actions { display: flex; gap: 12px; margin-top: 20px; }
        button { border: 0; border-radius: 999px; padding: 12px 20px; font: inherit; font-weight: 700; cursor: pointer; }
        .accept { background: #111; color: #fff; flex: 1; }
        .decline { background: transparent; border: 1px solid #aaa; color: inherit; }
        .error { color: #b42318; margin-top: 8px; }
        @media (prefers-color-scheme: dark) {
            body { background: #111; color: #f5f5f5; }
            .panel { background: #202020; border-color: #393939; }
            header, form { border-color: #393939; }
            header p, .meta { color: #aaa; }
            .accept { background: #fff; color: #111; }
        }
        @media (max-width: 560px) { main { margin: 12px auto; } .actions { flex-direction: column; } }
    </style>
</head>
<body>
<main>
    <section class="panel" aria-labelledby="agreement-title">
        <header>
            <p class="meta">{{ config('app.name') }} · Version {{ $agreement->version }}</p>
            <h1 id="agreement-title">{{ $agreement->title }}</h1>
            @if ($agreement->effectiveAt)
                <p>Effective {{ $agreement->effectiveAt }}</p>
            @endif
        </header>

        <article class="agreement">{!! $agreement->html !!}</article>

        <form method="POST" action="{{ route('x-change.legal.eula.accept') }}">
            @csrf
            <input type="hidden" name="agreement_sha256" value="{{ $agreement->sha256 }}">
            <label>
                <input type="checkbox" name="accepted" value="1" required>
                <span>I have read and accept this agreement, including its beta, provider-held funds, pooled-account, and ledger-attribution disclosures.</span>
            </label>
            @error('accepted')<p class="error">{{ $message }}</p>@enderror
            @error('agreement_sha256')<p class="error">{{ $message }}</p>@enderror
            <div class="actions">
                <button class="accept" type="submit">Accept and continue</button>
                <button class="decline" type="submit" form="decline-agreement">Decline and sign out</button>
            </div>
        </form>

        <form id="decline-agreement" method="POST" action="{{ route('x-change.legal.eula.decline') }}" hidden>
            @csrf
        </form>
    </section>
</main>
</body>
</html>
