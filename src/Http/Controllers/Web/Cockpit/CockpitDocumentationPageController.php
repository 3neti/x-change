<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Cockpit;

use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;
use LBHurtado\XChange\Services\Cockpit\CockpitSystemReadinessAccess;

final class CockpitDocumentationPageController extends Controller
{
    public function __construct(
        private readonly CockpitSystemReadinessAccess $systemReadinessAccess,
    ) {}

    public function __invoke(): Response
    {
        return Inertia::render('x-change/cockpit/Documentation', [
            'documentation' => [
                'schema' => 'x-change.cockpit.documentation.v2',
                'hero' => [
                    'eyebrow' => 'Beta Playbook',
                    'title' => 'Run X-Change with confidence',
                    'description' => 'How to fund, issue, collect, pay, monitor, and recover Pay Codes safely during beta testing.',
                    'primary_action' => ['label' => 'Issue a Pay Code', 'href' => route('x-change.cockpit.quick-generate')],
                    'secondary_action' => ['label' => 'Review claimed vouchers', 'href' => route('x-change.cockpit.pay-codes.index', ['status' => 'redeemed'])],
                ],
                'start_here' => [
                    [
                        'key' => 'pay-code',
                        'title' => 'Pay Codes carry intent',
                        'description' => 'A Pay Code can disburse funds, collect payment, settle a value flow, or invite someone into the workspace.',
                        'href' => route('x-change.cockpit.pay-codes.index'),
                    ],
                    [
                        'key' => 'fund-first',
                        'title' => 'Funding is the first safety gate',
                        'description' => 'Client Funds, Outstanding Pay Codes, and Issuance Capacity tell operators what can safely be issued.',
                        'href' => route('x-change.cockpit.funding.index'),
                    ],
                    [
                        'key' => 'claimed-center-stage',
                        'title' => 'Claimed vouchers matter most',
                        'description' => 'After redemption, claimed date, amount, recipient, evidence, and journal events become more important than expiration.',
                        'href' => route('x-change.cockpit.dashboard'),
                    ],
                ],
                'playbooks' => [
                    [
                        'key' => 'daily-operator',
                        'title' => 'Daily Operator Workflows',
                        'description' => 'The common actions Amelia should be able to run without needing engineering help.',
                        'links' => [
                            ['label' => 'Add or confirm funds', 'description' => 'Use QR Ph funding, suspense review, and provider-verified recovery before relying on capacity.', 'href' => route('x-change.cockpit.funding.index')],
                            ['label' => 'Issue one Pay Code', 'description' => 'Enter Amount first, then pick Disburse, Collect, or Settle from the Order card.', 'href' => route('x-change.cockpit.quick-generate')],
                            ['label' => 'Run POS mode', 'description' => 'Create payable QR Ph vouchers with canonical sale references and merchant identity.', 'href' => route('x-change.cockpit.quick-generate', ['surface' => 'pos'])],
                            ['label' => 'Inspect Pay Codes', 'description' => 'Find status, availability, claim facts, slices, evidence, and terminal actions.', 'href' => route('x-change.cockpit.pay-codes.index')],
                        ],
                    ],
                    [
                        'key' => 'campaigns',
                        'title' => 'Campaigns, Payroll, and Ayuda',
                        'description' => 'Batch payout operations with maker/checker review, row monitoring, and recovery.',
                        'links' => [
                            ['label' => 'Prepare a payroll run', 'description' => 'Import name, mobile, bank, account number, and amount for direct transfer batches.', 'href' => route('x-change.cockpit.campaigns.index')],
                            ['label' => 'Prepare an ayuda batch', 'description' => 'Import beneficiary name, mobile, and amount for Pay Code distribution.', 'href' => route('x-change.cockpit.campaigns.index')],
                            ['label' => 'Approve and monitor rows', 'description' => 'Track pending, provider dispatched, paid, failed, recovery ready, SMS sent, and claimed.', 'href' => route('x-change.cockpit.campaigns.index')],
                            ['label' => 'Recover failed transfers', 'description' => 'When provider payout fails, notify the recipient to claim the same-code recovery voucher.', 'href' => route('x-change.cockpit.campaigns.index')],
                        ],
                    ],
                    [
                        'key' => 'evidence',
                        'title' => 'Evidence and Safety',
                        'description' => 'What operators should check before deciding money moved correctly.',
                        'links' => array_values(array_filter([
                            ['label' => 'Claim & Evidence', 'description' => 'Use Pay Code detail to see when, where, how much, and by whom a voucher was claimed.', 'href' => route('x-change.cockpit.pay-codes.index')],
                            ['label' => 'Account position', 'description' => 'Review wallet position, funding destinations, collection wallet authority, and merchant QR profile.', 'href' => route('x-change.cockpit.accounts.index')],
                            ['label' => 'Overview', 'description' => 'Watch Client Funds, risk signals, and recently claimed vouchers in one place.', 'href' => route('x-change.cockpit.dashboard')],
                            $this->systemReadinessAccess->isVisible()
                                ? ['label' => 'System Readiness', 'description' => 'Inspect deployment and operational readiness when the workspace exposes diagnostics.', 'href' => route('x-change.cockpit.diagnostics.runtime-profile')]
                                : null,
                        ])),
                    ],
                ],
                'lifecycle' => [
                    ['key' => 'issued', 'label' => 'Issued', 'description' => 'The instruction exists and can be distributed, claimed, paid, or monitored.'],
                    ['key' => 'open', 'label' => 'Open claim / Awaiting payment', 'description' => 'A recipient or payer can still act; show remaining capacity, slices, or payment instructions.'],
                    ['key' => 'claimed', 'label' => 'Claimed / Paid / Redeemed', 'description' => 'Move claimed facts to center stage: amount, time, place, recipient, and evidence. Hide expiration as secondary history.'],
                    ['key' => 'failed', 'label' => 'Failed / Recovery ready', 'description' => 'Provider payout failed or needs operator review; recovery notification can reopen the claim path.'],
                    ['key' => 'closed', 'label' => 'Cancelled / Expired', 'description' => 'The code is no longer actionable; keep details for audit and reconciliation.'],
                ],
                'safety_notes' => [
                    ['key' => 'funding', 'title' => 'Provider evidence beats assumptions', 'description' => 'Do not trust local balances without provider-verified funding, settlement, or recovery evidence.'],
                    ['key' => 'privacy', 'title' => 'Mask recipient information', 'description' => 'Guides should expose safe summaries only: masked mobile numbers, labels, timestamps, and evidence links.'],
                    ['key' => 'queues', 'title' => 'Use exact-job feedback safety', 'description' => 'For SMS recovery tests, run the exact queued job when needed so stale messages do not reach recipients.'],
                    ['key' => 'journal', 'title' => 'Journal every material event', 'description' => 'CSV staged/applied, approval, issuance, provider success/failure, SMS, recovery, and claims should be traceable.'],
                ],
                'builder_links' => array_values(array_filter([
                    ['label' => 'Getting Started', 'description' => 'Adopt x-change in a fresh Laravel application.', 'href' => 'https://github.com/3neti/x-change/blob/main/GETTING_STARTED.md', 'external' => true],
                    ['label' => 'Deployment Guide', 'description' => 'Install, commission, and verify a thin host.', 'href' => 'https://github.com/3neti/x-change/blob/main/DEPLOYMENT.md', 'external' => true],
                    ['label' => 'Package Reference', 'description' => 'Architecture, commands, and package boundaries.', 'href' => 'https://github.com/3neti/x-change', 'external' => true],
                    $this->systemReadinessAccess->isVisible()
                        ? ['label' => 'System Readiness', 'description' => 'Deployment diagnostics for enabled workspaces.', 'href' => route('x-change.cockpit.diagnostics.runtime-profile')]
                        : null,
                ])),
            ],
        ]);
    }
}
