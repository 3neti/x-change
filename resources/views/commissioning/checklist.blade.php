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
        <h2>Readiness checks</h2>
        <ul>
            @foreach ([...$readiness['checks'], ...$installationChecks] as $check)
                <li><strong>{{ $check['passed'] ? 'Ready' : 'Action needed' }}</strong> · {{ str($check['name'])->title() }} — {{ $check['message'] }}</li>
            @endforeach
        </ul>
        @if ($systemPrincipalRecovery !== null)
            <aside class="directive">
                <h2>{{ $systemPrincipalRecovery['title'] }}</h2>
                <p>{{ $systemPrincipalRecovery['description'] }}</p>
                <pre><code>{{ $systemPrincipalRecovery['command'] }}</code></pre>
                <p>The provisioning reference is generated and reused automatically. A deployment control reference remains available as an advanced command option.</p>
                <p>Verify afterward with <code>{{ $systemPrincipalRecovery['verification_command'] }}</code>, then select <strong>Run checks again</strong>.</p>
            </aside>
        @endif
        @if ($commissioningRecovery !== null)
            <aside class="directive">
                <h2>{{ $commissioningRecovery['title'] }}</h2>
                <p>{{ $commissioningRecovery['description'] }}</p>
                <pre><code>{{ $commissioningRecovery['command'] }}</code></pre>
                <p>Verify afterward with <code>{{ $commissioningRecovery['verification_command'] }}</code>, then select <strong>Run checks again</strong>.</p>
            </aside>
        @endif
        @if ($readiness['missing_variables'] !== [])
            <h2>Missing variables</h2>
            <ul>@foreach ($readiness['missing_variables'] as $variable)<li><code>{{ $variable }}</code></li>@endforeach</ul>
        @endif
        <section class="governance" aria-labelledby="commercial-governance-heading">
            <p class="eyebrow">Commercial control</p>
            <h2 id="commercial-governance-heading">Commercial Governance</h2>
            <p>{{ $commercialGovernance['message'] }}</p>
            <dl>
                <div><dt>Mode</dt><dd>{{ str($commercialGovernance['mode'] ?? 'invalid')->replace('_', ' ')->title() }}</dd></div>
                <div><dt>State</dt><dd>{{ str($commercialGovernance['state'])->replace('_', ' ')->title() }}</dd></div>
                <div><dt>Issuance</dt><dd>{{ $commercialGovernance['issuance_available'] ? 'Available' : 'Blocked' }}</dd></div>
                <div><dt>Price changes</dt><dd>{{ $commercialGovernance['changes_locked'] ? 'Changes locked' : 'Maker-checker governed' }}</dd></div>
                <div><dt>Maker authorities</dt><dd>{{ $commercialGovernance['roles']['maker_count'] }}</dd></div>
                <div><dt>Checker authorities</dt><dd>{{ $commercialGovernance['roles']['checker_count'] }}</dd></div>
            </dl>
            <h3>Active Offering profiles</h3>
            <ul class="profiles">
                @forelse ($commercialGovernance['profiles'] as $profile)
                    <li>
                        <strong>{{ str($profile['profile'])->replace('_', ' ')->title() }}</strong>
                        — {{ $profile['active'] ? 'Active' : 'Action needed' }}
                        @if ($profile['active'])
                            · {{ str($profile['origin'])->replace('_', ' ')->title() }}
                            · v{{ $profile['version'] }}
                            @if ($profile['source_package_version'])
                                · package {{ $profile['source_package_version'] }}
                            @endif
                        @endif
                    </li>
                @empty
                    <li>No governed Commercial Offering profile is active.</li>
                @endforelse
            </ul>
            <h3>Partners and settlement operations</h3>
            <dl>
                <div><dt>Partner registry</dt><dd>{{ $commercialGovernance['partners']['storage_ready'] ? 'Ready' : 'Action needed' }}</dd></div>
                <div><dt>Active Partners</dt><dd>{{ $commercialGovernance['partners']['active_count'] }}</dd></div>
                <div><dt>Partner approvals</dt><dd>{{ $commercialGovernance['partners']['pending_partner_count'] + $commercialGovernance['partners']['pending_destination_count'] }} pending</dd></div>
                <div><dt>Provider payout calls</dt><dd>{{ $commercialGovernance['operations']['live_provider_calls_enabled'] ? 'Explicitly enabled' : 'Disabled' }}</dd></div>
                <div><dt>Scheduled reconciliation</dt><dd>{{ $commercialGovernance['operations']['scheduled_reconciliation_enabled'] ? 'Enabled' : 'Disabled' }}</dd></div>
                <div><dt>Reconciliation queue</dt><dd>{{ $commercialGovernance['operations']['queue'] }}</dd></div>
                <div><dt>Provider-cost review</dt><dd>{{ $commercialGovernance['operations']['provider_cost_review_count'] }} open</dd></div>
                <div><dt>Commission payouts</dt><dd>{{ $commercialGovernance['operations']['open_commission_payout_count'] }} open</dd></div>
            </dl>
            <p class="checked">Operator identities remain private. This checklist exposes readiness counts only.</p>
        </section>
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
