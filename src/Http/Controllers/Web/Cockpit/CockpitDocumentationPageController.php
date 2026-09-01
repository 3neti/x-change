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
                'schema' => 'x-change.cockpit.documentation.v1',
                'sections' => [
                    [
                        'key' => 'use',
                        'title' => 'Use x-change',
                        'description' => 'The shortest paths through the daily operator workspaces.',
                        'links' => [
                            ['label' => 'Cockpit', 'description' => 'Operational horizon and recent activity.', 'href' => route('x-change.cockpit.dashboard')],
                            ['label' => 'Create', 'description' => 'Design and issue one Pay Code.', 'href' => route('x-change.cockpit.quick-generate')],
                            ['label' => 'Funding', 'description' => 'Confirm Account funding through supported rails.', 'href' => route('x-change.cockpit.funding.index')],
                            ['label' => 'Campaigns', 'description' => 'Prepare and authorize payments to many recipients.', 'href' => route('x-change.cockpit.campaigns.index')],
                        ],
                    ],
                    [
                        'key' => 'operate',
                        'title' => 'Operate x-change',
                        'description' => 'Configuration visibility and deployment guidance for operators.',
                        'links' => array_values(array_filter([
                            ['label' => 'Your Account', 'description' => 'Review funds, issuance capacity, and funding destinations.', 'href' => route('x-change.cockpit.accounts.index')],
                            $this->systemReadinessAccess->isVisible()
                                ? ['label' => 'System Readiness', 'description' => 'Inspect deployment and operational readiness.', 'href' => route('x-change.cockpit.diagnostics.runtime-profile')]
                                : null,
                            ['label' => 'Deployment Guide', 'description' => 'Install, commission, and verify a thin host.', 'href' => 'https://github.com/3neti/x-change/blob/main/DEPLOYMENT.md', 'external' => true],
                        ])),
                    ],
                    [
                        'key' => 'build',
                        'title' => 'Build with x-change',
                        'description' => 'Package documentation for developers and implementation partners.',
                        'links' => [
                            ['label' => 'Getting Started', 'description' => 'Adopt x-change in a fresh Laravel application.', 'href' => 'https://github.com/3neti/x-change/blob/main/GETTING_STARTED.md', 'external' => true],
                            ['label' => 'BPLS QR Ph Developer Guide', 'description' => 'Issue payable Pay Codes and render QR Ph payment instructions through the Partner API.', 'href' => 'https://github.com/3neti/x-change/blob/main/docs/partner-api/bpls-qrph-integration-guide.md', 'external' => true],
                            ['label' => 'Package Reference', 'description' => 'Architecture, commands, and package boundaries.', 'href' => 'https://github.com/3neti/x-change', 'external' => true],
                        ],
                    ],
                ],
            ],
        ]);
    }
}
