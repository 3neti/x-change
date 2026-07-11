<?php

declare(strict_types=1);

use LBHurtado\XChange\Contracts\CockpitReadModelProviderContract;
use LBHurtado\XChange\Contracts\VoucherLifecycleServiceContract;
use LBHurtado\XChange\Data\Cockpit\CockpitCampaignReadModelData;
use LBHurtado\XChange\Data\Cockpit\CockpitReadModelQueryData;
use LBHurtado\XChange\Exceptions\VoucherNotFound;
use LBHurtado\XChange\Services\Cockpit\NullCockpitReadModelProvider;
use LBHurtado\XChange\Services\Cockpit\OptionalCockpitIntegrationReadModels;
use LBHurtado\XChange\Services\Cockpit\VoucherLifecycleCockpitReadModelProvider;
use LBHurtado\XChange\Support\Cockpit\DefaultCockpitRedactor;

it('builds a cockpit read model query without implying side effects', function () {
    $query = new CockpitReadModelQueryData(
        code: 'PC-READY-001',
        operatorId: 'operator-1',
        include: ['voucher', 'execution', 'journal', 'actions', 'feedback'],
        correlationId: 'corr-1',
    );

    expect($query->code)->toBe('PC-READY-001')
        ->and($query->operatorId)->toBe('operator-1')
        ->and($query->include)->toBe(['voucher', 'execution', 'journal', 'actions', 'feedback'])
        ->and($query->correlationId)->toBe('corr-1');
});

it('returns an empty not wired cockpit read model bundle by default', function () {
    $provider = new NullCockpitReadModelProvider;

    $bundle = $provider->forVoucher(new CockpitReadModelQueryData(
        code: 'PC-READY-001',
        include: ['voucher', 'execution', 'journal', 'actions', 'feedback'],
    ));

    expect($bundle->code)->toBe('PC-READY-001')
        ->and($bundle->voucher->status)->toBe('not_wired')
        ->and($bundle->voucher->authorized)->toBeFalse()
        ->and($bundle->voucher->summary)->toBe([])
        ->and($bundle->execution->status)->toBe('not_wired')
        ->and($bundle->execution->events)->toBe([])
        ->and($bundle->journal->status)->toBe('not_wired')
        ->and($bundle->journal->entries)->toBe([])
        ->and($bundle->actions->status)->toBe('not_wired')
        ->and($bundle->actions->actions)->toBe([])
        ->and($bundle->feedback->status)->toBe('not_wired')
        ->and($bundle->feedback->deliveries)->toBe([]);
});

it('serializes cockpit read model placeholders without broad payload exposure', function () {
    $bundle = (new NullCockpitReadModelProvider)->forVoucher(new CockpitReadModelQueryData(
        code: 'PC-READY-001',
    ));

    expect($bundle->toArray())->toBe([
        'code' => 'PC-READY-001',
        'voucher' => [
            'code' => 'PC-READY-001',
            'status' => 'not_wired',
            'summary' => [],
            'evidence_summary' => [],
            'redactions' => ['payloads' => 'not-loaded'],
            'authorized' => false,
        ],
        'execution' => [
            'execution_id' => null,
            'status' => 'not_wired',
            'driver' => null,
            'events' => [],
            'metadata' => [],
            'redactions' => ['payloads' => 'not-loaded'],
            'authorized' => false,
        ],
        'journal' => [
            'status' => 'not_wired',
            'entries' => [],
            'redactions' => ['payloads' => 'not-loaded'],
            'authorized' => false,
        ],
        'actions' => [
            'status' => 'not_wired',
            'actions' => [],
            'diagnostics' => [],
            'redactions' => ['payloads' => 'not-loaded'],
            'authorized' => false,
        ],
        'feedback' => [
            'status' => 'not_wired',
            'deliveries' => [],
            'redactions' => ['payloads' => 'not-loaded'],
            'authorized' => false,
        ],
    ]);
});

it('returns a not wired campaign cockpit read model contract by default', function () {
    $readModel = (new NullCockpitReadModelProvider)
        ->forCampaignAdoption(new CockpitReadModelQueryData(
            operatorId: 'operator-1',
            include: ['campaigns', 'audiences', 'imports', 'attachments'],
            correlationId: 'corr-1',
        ));

    expect($readModel)
        ->toBeInstanceOf(CockpitCampaignReadModelData::class)
        ->and($readModel->schema)->toBe('x-change.cockpit.campaign-adoption.v1')
        ->and($readModel->status)->toBe('not_wired')
        ->and($readModel->authorized)->toBeFalse()
        ->and($readModel->source)->toBe('null-campaign-cockpit-read-model-provider')
        ->and($readModel->surfaces)->toBe([
            [
                'key' => 'campaign_dashboard',
                'status' => 'not_wired',
                'enabled' => false,
                'read_only' => true,
                'reason' => 'x-campaign-adapter-not-configured',
            ],
            [
                'key' => 'campaign_explorer',
                'status' => 'not_wired',
                'enabled' => false,
                'read_only' => true,
                'reason' => 'x-campaign-adapter-not-configured',
            ],
            [
                'key' => 'audience_import_workspace',
                'status' => 'not_wired',
                'enabled' => false,
                'read_only' => true,
                'reason' => 'x-campaign-adapter-not-configured',
            ],
            [
                'key' => 'attachment_operator_workspace',
                'status' => 'not_wired',
                'enabled' => false,
                'read_only' => true,
                'reason' => 'x-campaign-adapter-not-configured',
            ],
            [
                'key' => 'campaign_api_descriptors',
                'status' => 'not_wired',
                'enabled' => false,
                'read_only' => true,
                'reason' => 'x-campaign-adapter-not-configured',
            ],
        ])
        ->and($readModel->mutation)->toBe([
            'enabled' => false,
            'status' => 'blocked',
            'reason' => 'campaign-mutations-not-authorized',
        ])
        ->and($readModel->redactions)->toBe(['payloads' => 'not-loaded'])
        ->and($readModel->toArray())->toBe([
            'schema' => 'x-change.cockpit.campaign-adoption.v1',
            'status' => 'not_wired',
            'authorized' => false,
            'source' => 'null-campaign-cockpit-read-model-provider',
            'surfaces' => [
                [
                    'key' => 'campaign_dashboard',
                    'status' => 'not_wired',
                    'enabled' => false,
                    'read_only' => true,
                    'reason' => 'x-campaign-adapter-not-configured',
                ],
                [
                    'key' => 'campaign_explorer',
                    'status' => 'not_wired',
                    'enabled' => false,
                    'read_only' => true,
                    'reason' => 'x-campaign-adapter-not-configured',
                ],
                [
                    'key' => 'audience_import_workspace',
                    'status' => 'not_wired',
                    'enabled' => false,
                    'read_only' => true,
                    'reason' => 'x-campaign-adapter-not-configured',
                ],
                [
                    'key' => 'attachment_operator_workspace',
                    'status' => 'not_wired',
                    'enabled' => false,
                    'read_only' => true,
                    'reason' => 'x-campaign-adapter-not-configured',
                ],
                [
                    'key' => 'campaign_api_descriptors',
                    'status' => 'not_wired',
                    'enabled' => false,
                    'read_only' => true,
                    'reason' => 'x-campaign-adapter-not-configured',
                ],
            ],
            'facts' => [],
            'mutation' => [
                'enabled' => false,
                'status' => 'blocked',
                'reason' => 'campaign-mutations-not-authorized',
            ],
            'redactions' => ['payloads' => 'not-loaded'],
        ])
        ->and($readModel->toArray())->not->toHaveKeys([
            'provider_payload',
            'raw_payload',
            'wallet',
            'campaign_mutation_endpoint',
            'pay_code_generation_payload',
            'delivery_dispatch_payload',
        ]);
});

it('hydrates optional campaign cockpit adoption facts through a configured read-only adapter seam', function () {
    $service = new class
    {
        /**
         * @var array<string, mixed>
         */
        public array $received = [];

        /**
         * @param  array<string, mixed>  $metadata
         * @return array<string, mixed>
         */
        public function summary(
            string $planningKey,
            string $executionId,
            string $operatorId,
            string $channel = 'sms',
            ?string $correlationId = null,
            array $metadata = [],
        ): array {
            $this->received = compact('planningKey', 'executionId', 'operatorId', 'channel', 'correlationId', 'metadata');

            return [
                'status' => 'ready',
                'planning_key' => $planningKey,
                'execution_id' => $executionId,
                'operator_id' => $operatorId,
                'cards' => [
                    'campaign' => [
                        'name' => 'Food Aid July',
                        'recipient_count' => 250,
                        'secret' => 'do-not-show',
                    ],
                ],
                'panels' => [
                    'audience_import_workspace' => ['status' => 'ready'],
                    'attachment_operator_workspace' => ['status' => 'ready'],
                ],
                'actions' => [
                    'review_campaign' => ['enabled' => true],
                    'generate_pay_codes' => ['enabled' => false],
                ],
                'blockers' => [],
                'effects' => [
                    'persists' => false,
                    'uses_database' => false,
                    'queues_jobs' => false,
                    'issues_pay_codes' => false,
                    'sends_feedback' => false,
                    'writes_journal' => false,
                    'moves_money' => false,
                ],
                'metadata' => [
                    'source' => 'fake-x-campaign',
                    'read_only' => true,
                    'token' => 'sensitive-token',
                ],
            ];
        }
    };

    config(['x-change.cockpit.integrations.campaign.cockpit' => 'fake.campaign.cockpit']);
    app()->instance('fake.campaign.cockpit', $service);

    $readModel = (new VoucherLifecycleCockpitReadModelProvider(
        vouchers: new class implements VoucherLifecycleServiceContract
        {
            public function list(array $filters = []): array
            {
                return [];
            }

            public function show(string $voucher): mixed
            {
                return null;
            }

            public function showByCode(string $code): mixed
            {
                return null;
            }

            public function status(string $voucher): mixed
            {
                return null;
            }

            public function cancel(string $voucher, array $payload = []): mixed
            {
                return [];
            }
        },
        integrations: app(OptionalCockpitIntegrationReadModels::class),
    ))->forCampaignAdoption(new CockpitReadModelQueryData(
        code: 'campaign-plan-1',
        operatorId: 'operator-1',
        include: ['campaigns', 'audiences', 'imports', 'attachments'],
        correlationId: 'execution-1',
    ));

    expect($service->received)->toMatchArray([
        'planningKey' => 'campaign-plan-1',
        'executionId' => 'execution-1',
        'operatorId' => 'operator-1',
        'channel' => 'cockpit',
        'correlationId' => 'execution-1',
        'metadata' => [
            'source' => 'x-change.cockpit',
            'read_only' => true,
            'integration' => 'campaign.cockpit',
        ],
    ])
        ->and($readModel->status)->toBe('available')
        ->and($readModel->authorized)->toBeTrue()
        ->and($readModel->source)->toBe('x-campaign')
        ->and($readModel->facts)->toBe([
            'planning_key' => 'campaign-plan-1',
            'execution_id' => 'execution-1',
            'operator_id' => 'operator-1',
            'cards' => [
                'campaign' => [
                    'name' => 'Food Aid July',
                    'recipient_count' => 250,
                    'secret' => '[redacted]',
                ],
            ],
            'panels' => [
                'audience_import_workspace' => ['status' => 'ready'],
                'attachment_operator_workspace' => ['status' => 'ready'],
            ],
            'actions' => [
                'review_campaign' => ['enabled' => true],
                'generate_pay_codes' => ['enabled' => false],
            ],
            'blockers' => [],
            'metadata' => [
                'source' => 'fake-x-campaign',
                'read_only' => true,
                'token' => '[redacted]',
            ],
        ])
        ->and($readModel->surfaces)->each->toMatchArray([
            'status' => 'available',
            'enabled' => true,
            'read_only' => true,
            'reason' => 'x-campaign-read-model-available',
        ])
        ->and($readModel->mutation)->toBe([
            'enabled' => false,
            'status' => 'blocked',
            'reason' => 'campaign-mutations-not-authorized',
        ])
        ->and($readModel->redactions)->toBe([
            'payloads' => 'campaign-cockpit-summary-only',
            'source' => 'x-campaign',
            'read_only' => true,
            'routes_registered' => false,
            'controllers_registered' => false,
            'mutates_campaigns' => false,
            'issues_pay_codes' => false,
            'sends_feedback' => false,
            'writes_journal' => false,
            'moves_money' => false,
            'effects' => [
                'persists' => false,
                'uses_database' => false,
                'queues_jobs' => false,
                'issues_pay_codes' => false,
                'sends_feedback' => false,
                'writes_journal' => false,
                'moves_money' => false,
            ],
        ])
        ->and($readModel->toArray())->not->toHaveKeys([
            'provider_payload',
            'raw_payload',
            'wallet',
            'campaign_mutation_endpoint',
            'pay_code_generation_payload',
            'delivery_dispatch_payload',
        ]);
});

it('degrades optional campaign cockpit adoption safely when the configured adapter fails', function () {
    config(['x-change.cockpit.integrations.campaign.cockpit' => 'fake.failing.campaign.cockpit']);
    app()->instance('fake.failing.campaign.cockpit', new class
    {
        /**
         * @param  array<string, mixed>  $metadata
         */
        public function summary(
            string $planningKey,
            string $executionId,
            string $operatorId,
            string $channel = 'sms',
            ?string $correlationId = null,
            array $metadata = [],
        ): never {
            throw new RuntimeException('Package failure should not leak.');
        }
    });

    $readModel = app(OptionalCockpitIntegrationReadModels::class)
        ->campaignAdoption(new CockpitReadModelQueryData(
            code: 'campaign-plan-1',
            operatorId: 'operator-1',
            correlationId: 'execution-1',
        ));

    expect($readModel->status)->toBe('unavailable')
        ->and($readModel->authorized)->toBeFalse()
        ->and($readModel->source)->toBe('x-campaign')
        ->and($readModel->facts)->toBe([])
        ->and($readModel->mutation)->toBe([
            'enabled' => false,
            'status' => 'blocked',
            'reason' => 'campaign-mutations-not-authorized',
        ])
        ->and($readModel->redactions)->toMatchArray([
            'payloads' => 'not-loaded',
            'source' => 'x-campaign',
            'reason' => 'read-model-unavailable',
            'exception' => 'RuntimeException',
            'exception_message_exposed' => false,
        ]);
});

it('adapts voucher lifecycle details into a sanitized cockpit voucher summary', function () {
    $lifecycle = new class implements VoucherLifecycleServiceContract
    {
        public string $requestedCode = '';

        public function list(array $filters = []): array
        {
            return [];
        }

        public function show(string $voucher): mixed
        {
            return null;
        }

        public function showByCode(string $code): mixed
        {
            $this->requestedCode = $code;

            return [
                'id' => 123,
                'voucher_id' => 123,
                'code' => $code,
                'amount' => 1500.75,
                'currency' => 'PHP',
                'status' => 'issued',
                'display_status' => 'ready',
                'issuer_id' => 45,
                'claimed' => false,
                'fully_claimed' => false,
                'created_at' => '2026-07-03T10:00:00+08:00',
                'starts_at' => '2026-07-03T11:00:00+08:00',
                'expires_at' => '2026-07-10T11:00:00+08:00',
                'redeemed_at' => null,
                'instructions' => ['provider_payload' => ['secret' => 'should-not-leak']],
                'claims' => [['mobile' => '+639171234567']],
                'approval' => ['reference_id' => 'approval-reference'],
                'provider_payload' => ['token' => 'provider-token'],
                'raw_payload' => ['secret' => 'raw-secret'],
                'wallet' => ['account_number' => '000123'],
                'provider' => 'paynamics',
            ];
        }

        public function status(string $voucher): mixed
        {
            return null;
        }

        public function cancel(string $voucher, array $payload = []): mixed
        {
            return [];
        }
    };

    $bundle = (new VoucherLifecycleCockpitReadModelProvider($lifecycle))
        ->forVoucher(new CockpitReadModelQueryData(
            code: ' pc-ready-001 ',
            include: ['voucher', 'execution', 'journal', 'actions', 'feedback'],
        ));

    expect($lifecycle->requestedCode)->toBe('PC-READY-001')
        ->and($bundle->code)->toBe('PC-READY-001')
        ->and($bundle->voucher->code)->toBe('PC-READY-001')
        ->and($bundle->voucher->status)->toBe('issued')
        ->and($bundle->voucher->authorized)->toBeTrue()
        ->and($bundle->voucher->summary)->toBe([
            'code' => 'PC-READY-001',
            'status' => 'issued',
            'display_status' => 'ready',
            'amount' => 1500.75,
            'currency' => 'PHP',
            'claimed' => false,
            'fully_claimed' => false,
            'created_at' => '2026-07-03T10:00:00+08:00',
            'starts_at' => '2026-07-03T11:00:00+08:00',
            'expires_at' => '2026-07-10T11:00:00+08:00',
            'redeemed_at' => null,
        ])
        ->and($bundle->voucher->redactions)->toBe([
            'payloads' => 'sanitized-summary-only',
            'excluded' => [
                'id',
                'voucher_id',
                'issuer_id',
                'instructions',
                'claims',
                'approval',
                'provider_payload',
                'raw_payload',
                'wallet',
                'provider',
            ],
        ])
        ->and($bundle->execution->status)->toBe('not_wired')
        ->and($bundle->journal->status)->toBe('not_wired')
        ->and($bundle->actions->status)->toBe('not_wired')
        ->and($bundle->feedback->status)->toBe('not_wired')
        ->and($bundle->toArray())->not->toHaveKeys([
            'provider_payload',
            'raw_payload',
            'wallet',
            'provider',
        ])
        ->and($bundle->voucher->summary)->not->toHaveKeys([
            'id',
            'voucher_id',
            'issuer_id',
            'instructions',
            'claims',
            'approval',
            'provider_payload',
            'raw_payload',
            'wallet',
            'provider',
        ]);
});

it('falls back to the not wired bundle when voucher lifecycle lookup misses', function () {
    $lifecycle = new class implements VoucherLifecycleServiceContract
    {
        public function list(array $filters = []): array
        {
            return [];
        }

        public function show(string $voucher): mixed
        {
            return null;
        }

        public function showByCode(string $code): mixed
        {
            throw new VoucherNotFound($code);
        }

        public function status(string $voucher): mixed
        {
            return null;
        }

        public function cancel(string $voucher, array $payload = []): mixed
        {
            return [];
        }
    };

    $bundle = (new VoucherLifecycleCockpitReadModelProvider($lifecycle))
        ->forVoucher(new CockpitReadModelQueryData(code: 'PC-MISSING-001'));

    expect($bundle->code)->toBe('PC-MISSING-001')
        ->and($bundle->voucher->status)->toBe('not_wired')
        ->and($bundle->voucher->authorized)->toBeFalse()
        ->and($bundle->voucher->summary)->toBe([])
        ->and($bundle->execution->status)->toBe('not_wired')
        ->and($bundle->journal->status)->toBe('not_wired')
        ->and($bundle->actions->status)->toBe('not_wired')
        ->and($bundle->feedback->status)->toBe('not_wired');
});

it('hydrates optional journal action and feedback read models when integration services are bound', function () {
    config()->set('x-change.cockpit.integrations.journal.reader', 'fake.cockpit.journal');
    config()->set('x-change.cockpit.integrations.action.composer', 'fake.cockpit.actions');
    config()->set('x-change.cockpit.integrations.feedback.console', 'fake.cockpit.feedback');

    app()->instance('fake.cockpit.journal', new class
    {
        public function read(mixed $query): array
        {
            return [
                'entries' => [
                    [
                        'reference_number' => 'ERN-000000001',
                        'event_type' => 'execution.completed',
                        'payload' => [
                            'status' => 'completed',
                            'secret' => 'journal-secret',
                        ],
                    ],
                ],
                'metadata' => [
                    'pagination' => [
                        'limit_semantics' => 'visible_entries',
                    ],
                ],
                'query_type' => is_array($query) ? 'array' : $query::class,
            ];
        }
    });

    app()->instance('fake.cockpit.actions', new class
    {
        public function compose(
            string $eventOrState,
            mixed $subject,
            mixed $context,
            ?string $correlationId = null,
            ?string $causationId = null,
            bool $includeDiagnostics = false,
        ): array {
            return [
                'event_or_state' => $eventOrState,
                'actions' => [
                    [
                        'action' => ['key' => 'voucher.inspect'],
                        'run' => [
                            'run_id' => 'presentation-run-1',
                            'secret' => 'action-secret',
                        ],
                        'meta' => [
                            'run_semantics' => [
                                'presentation_run' => true,
                                'durable' => false,
                            ],
                        ],
                    ],
                ],
                'diagnostics' => [
                    [
                        'provider' => 'Internal\\Provider',
                        'details' => ['secret' => 'raw-diagnostic-secret'],
                    ],
                ],
                'meta' => [
                    'safe_diagnostics' => [
                        [
                            'action_key' => 'voucher.inspect',
                            'status' => 'included',
                            'reason' => 'included',
                        ],
                    ],
                ],
            ];
        }
    });

    app()->instance('fake.cockpit.feedback', new class
    {
        public function history(array $filters = []): array
        {
            return [
                'total' => 1,
                'records' => [
                    [
                        'delivery_id' => 'delivery-1',
                        'status' => 'delivered',
                        'channel' => 'sms',
                        'provider_response' => ['token' => 'feedback-secret'],
                    ],
                ],
                'filters' => $filters,
            ];
        }
    });

    $lifecycle = new class implements VoucherLifecycleServiceContract
    {
        public function list(array $filters = []): array
        {
            return [];
        }

        public function show(string $voucher): mixed
        {
            return null;
        }

        public function showByCode(string $code): mixed
        {
            return [
                'code' => $code,
                'status' => 'issued',
                'display_status' => 'ready',
            ];
        }

        public function status(string $voucher): mixed
        {
            return null;
        }

        public function cancel(string $voucher, array $payload = []): mixed
        {
            return [];
        }
    };

    $bundle = (new VoucherLifecycleCockpitReadModelProvider(
        vouchers: $lifecycle,
        integrations: new OptionalCockpitIntegrationReadModels(app(), new DefaultCockpitRedactor),
    ))->forVoucher(new CockpitReadModelQueryData(
        code: 'PC-READY-001',
        operatorId: 'operator-1',
        include: ['voucher', 'journal', 'actions', 'feedback'],
        correlationId: 'corr-1',
    ));

    expect($bundle->journal->status)->toBe('available')
        ->and($bundle->journal->authorized)->toBeTrue()
        ->and($bundle->journal->entries[0]['payload']['secret'])->toBe('[redacted]')
        ->and($bundle->journal->redactions['source'])->toBe('x-journal')
        ->and($bundle->journal->redactions['evidence_only'])->toBeTrue()
        ->and($bundle->journal->redactions['writes_journal_entries'])->toBeFalse()
        ->and($bundle->actions->status)->toBe('available')
        ->and($bundle->actions->actions[0]['run']['secret'])->toBe('[redacted]')
        ->and($bundle->actions->diagnostics)->toBe([[
            'action_key' => 'voucher.inspect',
            'status' => 'included',
            'reason' => 'included',
        ]])
        ->and($bundle->actions->redactions['presentation_only'])->toBeTrue()
        ->and($bundle->actions->redactions['executes_action'])->toBeFalse()
        ->and($bundle->actions->redactions['raw_diagnostics_exposed'])->toBeFalse()
        ->and($bundle->feedback->status)->toBe('available')
        ->and($bundle->feedback->deliveries[0]['provider_response'])->toBe('[redacted]')
        ->and($bundle->feedback->redactions['source'])->toBe('x-feedback')
        ->and($bundle->feedback->redactions['sends_feedback'])->toBeFalse()
        ->and($bundle->feedback->redactions['calls_providers'])->toBeFalse();
});

it('degrades optional cockpit integrations safely when services throw', function () {
    config()->set('x-change.cockpit.integrations.journal.reader', 'fake.throwing.journal');
    config()->set('x-change.cockpit.integrations.action.composer', 'fake.throwing.actions');
    config()->set('x-change.cockpit.integrations.feedback.console', 'fake.throwing.feedback');

    app()->instance('fake.throwing.journal', new class
    {
        public function read(mixed $query): array
        {
            throw new RuntimeException('journal secret details');
        }
    });

    app()->instance('fake.throwing.actions', new class
    {
        public function compose(): array
        {
            throw new RuntimeException('action secret details');
        }
    });

    app()->instance('fake.throwing.feedback', new class
    {
        public function history(array $filters = []): array
        {
            throw new RuntimeException('feedback secret details');
        }
    });

    $integrations = new OptionalCockpitIntegrationReadModels(app(), new DefaultCockpitRedactor);
    $query = new CockpitReadModelQueryData(code: 'PC-READY-001');

    expect($integrations->journal($query)->toArray())->toMatchArray([
        'status' => 'unavailable',
        'authorized' => false,
        'redactions' => [
            'payloads' => 'not-loaded',
            'source' => 'x-journal',
            'reason' => 'read-model-unavailable',
            'exception' => 'RuntimeException',
            'exception_message_exposed' => false,
        ],
    ])
        ->and($integrations->actions($query)->toArray())->toMatchArray([
            'status' => 'unavailable',
            'authorized' => false,
            'redactions' => [
                'payloads' => 'not-loaded',
                'source' => 'x-action',
                'reason' => 'read-model-unavailable',
                'exception' => 'RuntimeException',
                'exception_message_exposed' => false,
            ],
        ])
        ->and($integrations->feedback($query)->toArray())->toMatchArray([
            'status' => 'unavailable',
            'authorized' => false,
            'redactions' => [
                'payloads' => 'not-loaded',
                'source' => 'x-feedback',
                'reason' => 'read-model-unavailable',
                'exception' => 'RuntimeException',
                'exception_message_exposed' => false,
            ],
        ]);
});

it('degrades optional cockpit integrations safely when service resolution fails', function () {
    config()->set('x-change.cockpit.integrations.journal.reader', 'fake.unresolvable.journal');
    config()->set('x-change.cockpit.integrations.action.composer', 'fake.unresolvable.actions');
    config()->set('x-change.cockpit.integrations.feedback.console', 'fake.unresolvable.feedback');

    app()->bind('fake.unresolvable.journal', fn (): never => throw new RuntimeException('journal construction secret'));
    app()->bind('fake.unresolvable.actions', fn (): never => throw new RuntimeException('action construction secret'));
    app()->bind('fake.unresolvable.feedback', fn (): never => throw new RuntimeException('feedback construction secret'));

    $integrations = new OptionalCockpitIntegrationReadModels(app(), new DefaultCockpitRedactor);
    $query = new CockpitReadModelQueryData(code: 'PC-READY-001');

    expect($integrations->journal($query)->toArray())->toMatchArray([
        'status' => 'unavailable',
        'authorized' => false,
        'redactions' => [
            'payloads' => 'not-loaded',
            'source' => 'x-journal',
            'reason' => 'package-not-installed',
            'exception' => null,
            'exception_message_exposed' => false,
        ],
    ])
        ->and($integrations->actions($query)->toArray())->toMatchArray([
            'status' => 'unavailable',
            'authorized' => false,
            'redactions' => [
                'payloads' => 'not-loaded',
                'source' => 'x-action',
                'reason' => 'package-not-installed',
                'exception' => null,
                'exception_message_exposed' => false,
            ],
        ])
        ->and($integrations->feedback($query)->toArray())->toMatchArray([
            'status' => 'unavailable',
            'authorized' => false,
            'redactions' => [
                'payloads' => 'not-loaded',
                'source' => 'x-feedback',
                'reason' => 'package-not-installed',
                'exception' => null,
                'exception_message_exposed' => false,
            ],
        ]);
});

it('returns an empty not wired pay code list read model by default', function () {
    $readModel = (new NullCockpitReadModelProvider)
        ->forPayCodeList(new CockpitReadModelQueryData);

    expect($readModel->toArray())->toBe([
        'status' => 'not_wired',
        'authorized' => false,
        'query' => null,
        'status_filter' => null,
        'stats' => [
            'total' => 0,
            'active' => 0,
            'awaiting_approval' => 0,
            'redeemed' => 0,
            'expired' => 0,
            'pending' => 0,
            'failed' => 0,
            'filtered' => 0,
        ],
        'filters' => [],
        'records' => [],
        'redactions' => ['payloads' => 'not-loaded'],
    ]);
});

it('adapts voucher lifecycle list rows into sanitized cockpit pay code rows', function () {
    $lifecycle = new class implements VoucherLifecycleServiceContract
    {
        public array $requestedFilters = [];

        public function list(array $filters = []): array
        {
            $this->requestedFilters = $filters;

            return [
                [
                    'id' => 123,
                    'voucher_id' => 123,
                    'code' => ' pc-list-001 ',
                    'template' => 'Emergency Cash',
                    'amount' => 2500.5,
                    'currency' => 'PHP',
                    'status' => 'issued',
                    'display_status' => 'ready',
                    'owner' => 'Operations',
                    'last_activity' => '2026-07-03T10:00:00+08:00',
                    'issuer_id' => 45,
                    'approval' => ['reference_id' => 'approval-reference'],
                    'provider_payload' => ['token' => 'provider-token'],
                    'raw_payload' => ['secret' => 'raw-secret'],
                    'wallet' => ['account_number' => '000123'],
                    'provider' => 'paynamics',
                ],
                [
                    'code' => 'pc-list-002',
                    'amount' => '99.95',
                    'currency' => 'PHP',
                    'status' => 'redeemed',
                    'created_at' => '2026-07-02T10:00:00+08:00',
                    'issuer_id' => 99,
                ],
                [
                    'code' => '',
                    'status' => 'issued',
                    'provider_payload' => ['token' => 'must-not-render'],
                ],
            ];
        }

        public function show(string $voucher): mixed
        {
            return null;
        }

        public function showByCode(string $code): mixed
        {
            return null;
        }

        public function status(string $voucher): mixed
        {
            return null;
        }

        public function cancel(string $voucher, array $payload = []): mixed
        {
            return [];
        }
    };

    $readModel = (new VoucherLifecycleCockpitReadModelProvider($lifecycle))
        ->forPayCodeList(new CockpitReadModelQueryData(
            include: ['voucher'],
            correlationId: 'corr-1',
        ));

    expect($lifecycle->requestedFilters)->toBe([])
        ->and($readModel->toArray())->toBe([
            'status' => 'available',
            'authorized' => true,
            'query' => null,
            'status_filter' => null,
            'stats' => [
                'total' => 2,
                'active' => 0,
                'awaiting_approval' => 0,
                'redeemed' => 1,
                'expired' => 0,
                'pending' => 0,
                'failed' => 0,
                'filtered' => 2,
            ],
            'filters' => [
                [
                    'key' => 'search',
                    'label' => 'Search',
                    'value' => '',
                    'active' => false,
                    'read_only' => true,
                ],
                [
                    'key' => 'status',
                    'label' => 'All',
                    'value' => 'all',
                    'active' => true,
                    'read_only' => true,
                ],
                [
                    'key' => 'status',
                    'label' => 'Awaiting Approval',
                    'value' => 'awaiting_approval',
                    'active' => false,
                    'read_only' => true,
                ],
                [
                    'key' => 'status',
                    'label' => 'Active',
                    'value' => 'active',
                    'active' => false,
                    'read_only' => true,
                ],
                [
                    'key' => 'status',
                    'label' => 'Redeemed',
                    'value' => 'redeemed',
                    'active' => false,
                    'read_only' => true,
                ],
                [
                    'key' => 'status',
                    'label' => 'Expired',
                    'value' => 'expired',
                    'active' => false,
                    'read_only' => true,
                ],
                [
                    'key' => 'status',
                    'label' => 'Pending',
                    'value' => 'pending',
                    'active' => false,
                    'read_only' => true,
                ],
                [
                    'key' => 'status',
                    'label' => 'Failed',
                    'value' => 'failed',
                    'active' => false,
                    'read_only' => true,
                ],
            ],
            'records' => [
                [
                    'code' => 'PC-LIST-001',
                    'template' => 'Emergency Cash',
                    'amount' => 2500.5,
                    'currency' => 'PHP',
                    'status' => 'ready',
                    'display_status' => 'ready',
                    'owner' => 'Operations',
                    'last_activity' => '2026-07-03T10:00:00+08:00',
                    'actions' => [
                        [
                            'key' => 'detail',
                            'label' => 'View details',
                            'enabled' => true,
                            'read_only' => true,
                            'href' => '/x/cockpit/pay-codes/PC-LIST-001',
                            'reason' => 'Read-only Cockpit voucher detail route.',
                        ],
                        [
                            'key' => 'distribution',
                            'label' => 'Distribution',
                            'enabled' => true,
                            'read_only' => true,
                            'href' => '/x/cockpit/pay-codes/PC-LIST-001/distribution',
                            'reason' => 'Read-only Cockpit distribution workspace route.',
                        ],
                        [
                            'key' => 'timeline',
                            'label' => 'Open timeline',
                            'enabled' => false,
                            'read_only' => true,
                            'href' => null,
                            'reason' => 'Timeline requires journal visibility and redaction wiring.',
                        ],
                        [
                            'key' => 'notify',
                            'label' => 'Notify recipient',
                            'enabled' => false,
                            'read_only' => true,
                            'href' => null,
                            'reason' => 'Feedback delivery remains separately gated through x-feedback.',
                        ],
                    ],
                ],
                [
                    'code' => 'PC-LIST-002',
                    'template' => 'Pay Code',
                    'amount' => '99.95',
                    'currency' => 'PHP',
                    'status' => 'redeemed',
                    'display_status' => 'redeemed',
                    'owner' => 'Redacted',
                    'last_activity' => '2026-07-02T10:00:00+08:00',
                    'actions' => [
                        [
                            'key' => 'detail',
                            'label' => 'View details',
                            'enabled' => true,
                            'read_only' => true,
                            'href' => '/x/cockpit/pay-codes/PC-LIST-002',
                            'reason' => 'Read-only Cockpit voucher detail route.',
                        ],
                        [
                            'key' => 'distribution',
                            'label' => 'Distribution',
                            'enabled' => true,
                            'read_only' => true,
                            'href' => '/x/cockpit/pay-codes/PC-LIST-002/distribution',
                            'reason' => 'Read-only Cockpit distribution workspace route.',
                        ],
                        [
                            'key' => 'timeline',
                            'label' => 'Open timeline',
                            'enabled' => false,
                            'read_only' => true,
                            'href' => null,
                            'reason' => 'Timeline requires journal visibility and redaction wiring.',
                        ],
                        [
                            'key' => 'notify',
                            'label' => 'Notify recipient',
                            'enabled' => false,
                            'read_only' => true,
                            'href' => null,
                            'reason' => 'Feedback delivery remains separately gated through x-feedback.',
                        ],
                    ],
                ],
            ],
            'redactions' => [
                'payloads' => 'sanitized-list-summary-only',
                'excluded' => [
                    'id',
                    'voucher_id',
                    'issuer_id',
                    'instructions',
                    'claims',
                    'approval',
                    'provider_payload',
                    'raw_payload',
                    'wallet',
                    'provider',
                ],
            ],
        ]);
});

it('returns an empty not wired dashboard read model by default', function () {
    $readModel = (new NullCockpitReadModelProvider)
        ->forDashboard(new CockpitReadModelQueryData);

    expect($readModel->toArray())->toBe([
        'status' => 'not_wired',
        'authorized' => false,
        'metrics' => [],
        'pipeline' => [],
        'risk_signals' => [],
        'activity' => [],
        'redactions' => ['payloads' => 'not-loaded'],
    ]);
});

it('returns an empty not wired quick generate read model by default', function () {
    $readModel = (new NullCockpitReadModelProvider)
        ->forQuickGenerate(new CockpitReadModelQueryData);

    expect($readModel->toArray())->toBe([
        'status' => 'not_wired',
        'authorized' => false,
        'templates' => [],
        'runtime_inputs' => [],
        'pricing_summaries' => [],
        'pricing_gate' => [
            'status' => 'not_wired',
            'checks' => [],
            'redactions' => ['payloads' => 'not-loaded'],
        ],
        'funding_gate' => [
            'status' => 'not_wired',
            'checks' => [],
            'redactions' => ['payloads' => 'not-loaded'],
        ],
        'idempotency_gate' => [
            'status' => 'not_wired',
            'checks' => [],
            'redactions' => ['payloads' => 'not-loaded'],
        ],
        'validation_redaction_gate' => [
            'status' => 'not_wired',
            'checks' => [],
            'redactions' => ['payloads' => 'not-loaded'],
        ],
        'mutation_handoff_plan' => [
            'status' => 'not_wired',
            'steps' => [],
            'redactions' => ['payloads' => 'not-loaded'],
        ],
        'mutation_preconditions_review' => [
            'status' => 'not_wired',
            'recommendation' => 'not-loaded',
            'items' => [],
            'redactions' => ['payloads' => 'not-loaded'],
        ],
        'mutation_authorization_decision' => [
            'status' => 'not_wired',
            'decision' => 'not-loaded',
            'required_approval' => 'not-loaded',
            'rationale' => 'not-loaded',
            'next_step' => 'not-loaded',
            'redactions' => ['payloads' => 'not-loaded'],
        ],
        'mutation_contract' => [
            'schema' => 'x-change.cockpit.quick-generate-mutation.v1',
            'status' => 'not_wired',
            'authorization' => 'not-loaded',
            'route' => 'not-loaded',
            'route_url' => null,
            'request_adapter' => 'not-loaded',
            'issuance_owner' => 'not-loaded',
            'idempotency' => 'not-loaded',
            'response_contract' => 'not-loaded',
            'runtime_enabled' => false,
            'gates' => [],
            'allowed_methods' => ['GET'],
            'redactions' => ['payloads' => 'not-loaded'],
        ],
        'draft_contract' => [
            'schema' => 'x-change.cockpit.quick-generate-draft.v1',
            'status' => 'not_wired',
            'template_key' => null,
            'amount' => null,
            'currency' => null,
            'recipient_reference' => null,
            'purpose' => null,
            'idempotency_key' => null,
            'redactions' => ['payloads' => 'not-loaded'],
        ],
        'authorization' => [
            'status' => 'not_wired',
            'gates' => [],
            'redactions' => ['payloads' => 'not-loaded'],
        ],
        'action' => [
            'enabled' => false,
            'reason' => 'not-loaded',
        ],
        'redactions' => ['payloads' => 'not-loaded'],
    ]);
});

it('adapts voucher lifecycle list rows into sanitized cockpit dashboard facts', function () {
    $lifecycle = new class implements VoucherLifecycleServiceContract
    {
        public function list(array $filters = []): array
        {
            return [
                [
                    'id' => 1,
                    'code' => 'pc-issued-001',
                    'status' => 'issued',
                    'display_status' => 'ready',
                    'amount' => 1000.00,
                    'currency' => 'PHP',
                    'created_at' => '2026-07-03T10:00:00+08:00',
                    'issuer_id' => 10,
                    'provider_payload' => ['token' => 'must-not-leak'],
                    'wallet' => ['account_number' => '000123'],
                ],
                [
                    'id' => 2,
                    'code' => 'pc-redeemed-001',
                    'status' => 'redeemed',
                    'display_status' => 'redeemed',
                    'amount' => 500.00,
                    'currency' => 'PHP',
                    'redeemed_at' => '2026-07-03T11:00:00+08:00',
                    'approval' => ['reference_id' => 'must-not-leak'],
                ],
                [
                    'id' => 3,
                    'code' => 'pc-expired-001',
                    'status' => 'expired',
                    'display_status' => 'expired',
                    'amount' => 250.00,
                    'currency' => 'PHP',
                    'expires_at' => '2026-07-03T12:00:00+08:00',
                    'raw_payload' => ['secret' => 'must-not-leak'],
                ],
                [
                    'id' => 4,
                    'code' => 'pc-awaiting-001',
                    'status' => 'redeemed',
                    'display_status' => 'awaiting_approval',
                    'amount' => 200.00,
                    'currency' => 'PHP',
                    'updated_at' => '2026-07-03T13:00:00+08:00',
                    'claims' => [['mobile' => '+639171234567']],
                ],
            ];
        }

        public function show(string $voucher): mixed
        {
            return null;
        }

        public function showByCode(string $code): mixed
        {
            return null;
        }

        public function status(string $voucher): mixed
        {
            return null;
        }

        public function cancel(string $voucher, array $payload = []): mixed
        {
            return [];
        }
    };

    $readModel = (new VoucherLifecycleCockpitReadModelProvider($lifecycle))
        ->forDashboard(new CockpitReadModelQueryData(include: ['voucher']));

    expect($readModel->toArray())->toBe([
        'status' => 'available',
        'authorized' => true,
        'metrics' => [
            [
                'key' => 'pay-codes-visible',
                'label' => 'Pay Codes Visible',
                'value' => '4',
                'helper' => 'Sanitized voucher lifecycle list rows',
                'tone' => 'neutral',
            ],
            [
                'key' => 'issued-pay-codes',
                'label' => 'Issued',
                'value' => '1',
                'helper' => 'Read-only lifecycle summary',
                'tone' => 'healthy',
            ],
            [
                'key' => 'redeemed-pay-codes',
                'label' => 'Redeemed',
                'value' => '2',
                'helper' => 'Includes awaiting approval display states',
                'tone' => 'healthy',
            ],
            [
                'key' => 'attention-pay-codes',
                'label' => 'Needs Attention',
                'value' => '2',
                'helper' => 'Expired or awaiting approval summaries only',
                'tone' => 'warning',
            ],
        ],
        'pipeline' => [
            ['key' => 'issued', 'label' => 'Issued', 'value' => '1', 'tone' => 'neutral'],
            ['key' => 'redeemed', 'label' => 'Redeemed', 'value' => '2', 'tone' => 'healthy'],
            ['key' => 'expired', 'label' => 'Expired', 'value' => '1', 'tone' => 'warning'],
            ['key' => 'awaiting-approval', 'label' => 'Awaiting Approval', 'value' => '1', 'tone' => 'warning'],
        ],
        'risk_signals' => [
            [
                'key' => 'expired-pay-codes',
                'label' => 'Expired Pay Codes',
                'value' => '1 sanitized summaries',
                'severity' => 'warning',
            ],
            [
                'key' => 'awaiting-approval',
                'label' => 'Awaiting Approval',
                'value' => '1 sanitized summaries',
                'severity' => 'watch',
            ],
        ],
        'activity' => [
            [
                'id' => 'PC-AWAITING-001',
                'label' => 'PC-AWAITING-001',
                'description' => 'Status: awaiting_approval',
                'timestamp' => '2026-07-03T13:00:00+08:00',
                'source' => 'system',
            ],
            [
                'id' => 'PC-EXPIRED-001',
                'label' => 'PC-EXPIRED-001',
                'description' => 'Status: expired',
                'timestamp' => '2026-07-03T12:00:00+08:00',
                'source' => 'system',
            ],
            [
                'id' => 'PC-REDEEMED-001',
                'label' => 'PC-REDEEMED-001',
                'description' => 'Status: redeemed',
                'timestamp' => '2026-07-03T11:00:00+08:00',
                'source' => 'system',
            ],
        ],
        'redactions' => [
            'payloads' => 'sanitized-dashboard-summary-only',
            'excluded' => [
                'id',
                'voucher_id',
                'issuer_id',
                'instructions',
                'claims',
                'approval',
                'provider_payload',
                'raw_payload',
                'wallet',
                'provider',
            ],
        ],
    ]);
});

it('adapts safe quick generate catalog facts without invoking voucher lifecycle reads', function () {
    $lifecycle = new class implements VoucherLifecycleServiceContract
    {
        public int $readCalls = 0;

        public function list(array $filters = []): array
        {
            $this->readCalls++;

            return [];
        }

        public function show(string $voucher): mixed
        {
            $this->readCalls++;

            return null;
        }

        public function showByCode(string $code): mixed
        {
            $this->readCalls++;

            return null;
        }

        public function status(string $voucher): mixed
        {
            $this->readCalls++;

            return null;
        }

        public function cancel(string $voucher, array $payload = []): mixed
        {
            return [];
        }
    };

    $readModel = (new VoucherLifecycleCockpitReadModelProvider($lifecycle))
        ->forQuickGenerate(new CockpitReadModelQueryData(include: ['templates', 'pricing']));

    expect($lifecycle->readCalls)->toBe(0)
        ->and($readModel->toArray())->toBe([
            'status' => 'available',
            'authorized' => true,
            'templates' => [
                [
                    'key' => 'money-changer',
                    'name' => 'Money Changer',
                    'description' => 'Fast cash-out Pay Code for branch counter operations.',
                    'profile' => 'branch',
                    'estimated_time' => 'Under 5 seconds',
                    'disabled' => false,
                ],
                [
                    'key' => 'ofw-remittance',
                    'name' => 'OFW Remittance',
                    'description' => 'Template-first remittance issuance with recipient details.',
                    'profile' => 'operations',
                    'estimated_time' => 'Pending runtime inputs',
                    'disabled' => false,
                ],
                [
                    'key' => 'settlement-envelope',
                    'name' => 'Settlement Envelope',
                    'description' => 'Complex settlement issuance remains deferred to later slices.',
                    'profile' => 'settlement',
                    'estimated_time' => 'Deferred',
                    'disabled' => true,
                ],
            ],
            'runtime_inputs' => [
                [
                    'key' => 'amount',
                    'label' => 'Amount',
                    'value' => 'Use the Quick Generate form',
                    'helper' => 'Pricing and funding preflights appear after a successful form submit.',
                ],
                [
                    'key' => 'recipient',
                    'label' => 'Recipient',
                    'value' => 'Use the Quick Generate form',
                    'helper' => 'Recipient reference is submitted through the existing issuance handoff.',
                ],
                [
                    'key' => 'purpose',
                    'label' => 'Purpose',
                    'value' => 'Optional form note',
                    'helper' => 'Purpose/message is passed as operator-safe issuance context.',
                ],
            ],
            'pricing_summaries' => [
                [
                    'key' => 'pricing',
                    'label' => 'Pricing Estimate',
                    'value' => 'Shown after submit',
                    'helper' => 'The result panel displays the operator-safe pricing preflight returned by the existing runtime.',
                ],
                [
                    'key' => 'funding',
                    'label' => 'Funding Impact',
                    'value' => 'Shown after submit',
                    'helper' => 'The result panel displays the operator-safe funding preflight; reservation and money movement remain behind existing issuance services.',
                ],
                [
                    'key' => 'execution',
                    'label' => 'Execution Summary',
                    'value' => 'Existing handoff',
                    'helper' => 'Quick Generate compiles a draft and hands off to GeneratePayCode; execution semantics stay voucher-owned.',
                ],
            ],
            'pricing_gate' => [
                'status' => 'runtime-informational',
                'checks' => [
                    [
                        'key' => 'template-selected',
                        'label' => 'Template Selected',
                        'status' => 'passed',
                        'reason' => 'The Money Changer template is selected by default for the current Quick Generate runtime.',
                    ],
                    [
                        'key' => 'amount-input-present',
                        'label' => 'Amount Input Present',
                        'status' => 'passed',
                        'reason' => 'The Quick Generate form accepts an operator amount and submits it to the existing issuance handoff.',
                    ],
                    [
                        'key' => 'pricing-service-wired',
                        'label' => 'Pricing Service Wired',
                        'status' => 'passed',
                        'reason' => 'The mutation result exposes an operator-safe pricing preflight after GeneratePayCode completes.',
                    ],
                    [
                        'key' => 'funding-source-selected',
                        'label' => 'Funding Source Selected',
                        'status' => 'runtime-diagnostic',
                        'reason' => 'Funding source details remain redacted; the operator sees only the safe funding preflight result after submit.',
                    ],
                    [
                        'key' => 'funds-reservation',
                        'label' => 'Funds Reservation',
                        'status' => 'blocked',
                        'reason' => 'Cockpit does not reserve, debit, or hold funds directly; those effects remain behind the existing issuance services.',
                    ],
                    [
                        'key' => 'provider-fee-quote',
                        'label' => 'Provider Fee Quote',
                        'status' => 'blocked',
                        'reason' => 'Cockpit does not call provider quote APIs directly.',
                    ],
                ],
                'redactions' => [
                    'payloads' => 'pricing-gates-only',
                    'excluded' => [
                        'pricing_breakdown',
                        'funding_source',
                        'wallet',
                        'balance',
                        'account_number',
                        'provider_payload',
                        'raw_payload',
                    ],
                ],
            ],
            'funding_gate' => [
                'status' => 'runtime-informational',
                'checks' => [
                    [
                        'key' => 'funding-policy-known',
                        'label' => 'Funding Policy Known',
                        'status' => 'passed',
                        'reason' => 'Funding preflight is represented as an operator-safe result after Quick Generate submits.',
                    ],
                    [
                        'key' => 'issuer-wallet-identified',
                        'label' => 'Issuer Wallet Identified',
                        'status' => 'runtime-diagnostic',
                        'reason' => 'Issuer funding details are evaluated by the existing issuance path and redacted from the Cockpit read model.',
                    ],
                    [
                        'key' => 'wallet-balance-available',
                        'label' => 'Wallet Balance Available',
                        'status' => 'runtime-diagnostic',
                        'reason' => 'The operator sees only the safe balance/funding preflight summary returned by the issuance runtime.',
                    ],
                    [
                        'key' => 'sufficient-funds',
                        'label' => 'Sufficient Funds',
                        'status' => 'runtime-diagnostic',
                        'reason' => 'Sufficiency is reported as an operator-safe preflight after submit; raw wallet data remains hidden.',
                    ],
                    [
                        'key' => 'funds-reservation-ready',
                        'label' => 'Funds Reservation Ready',
                        'status' => 'blocked',
                        'reason' => 'Cockpit does not reserve, hold, debit, or transfer funds directly.',
                    ],
                    [
                        'key' => 'provider-funding-ready',
                        'label' => 'Provider Funding Ready',
                        'status' => 'blocked',
                        'reason' => 'Cockpit does not call provider funding or account-readiness services directly.',
                    ],
                ],
                'redactions' => [
                    'payloads' => 'funding-gates-only',
                    'excluded' => [
                        'funding_source',
                        'wallet',
                        'balance',
                        'available_balance',
                        'account_number',
                        'provider_wallet',
                        'provider_payload',
                        'raw_payload',
                    ],
                ],
            ],
            'idempotency_gate' => [
                'status' => 'backend-ready',
                'checks' => [
                    [
                        'key' => 'idempotency-policy-known',
                        'label' => 'Idempotency Policy Known',
                        'status' => 'passed',
                        'reason' => 'Cockpit uses the existing x-change idempotency policy for Quick Generate mutation requests.',
                    ],
                    [
                        'key' => 'idempotency-key-source-defined',
                        'label' => 'Idempotency Key Source Defined',
                        'status' => 'passed',
                        'reason' => 'Cockpit accepts the configured Idempotency-Key header on the Quick Generate mutation route.',
                    ],
                    [
                        'key' => 'payload-fingerprint-defined',
                        'label' => 'Payload Fingerprint Defined',
                        'status' => 'passed',
                        'reason' => 'Cockpit delegates payload fingerprinting to the existing IdempotencyService.',
                    ],
                    [
                        'key' => 'replay-lookup-ready',
                        'label' => 'Replay Lookup Ready',
                        'status' => 'passed',
                        'reason' => 'Cockpit replays stored redacted operator responses for matching keys and payloads.',
                    ],
                    [
                        'key' => 'conflict-response-ready',
                        'label' => 'Conflict Response Ready',
                        'status' => 'passed',
                        'reason' => 'Cockpit returns the existing idempotency conflict response before a second issuance action call.',
                    ],
                    [
                        'key' => 'ttl-policy-ready',
                        'label' => 'TTL Policy Ready',
                        'status' => 'passed',
                        'reason' => 'Cockpit uses the existing IdempotencyService TTL configuration.',
                    ],
                ],
                'redactions' => [
                    'payloads' => 'idempotency-gates-only',
                    'excluded' => [
                        'idempotency_key',
                        'request_payload',
                        'payload_fingerprint',
                        'stored_response',
                        'replay_payload',
                        'cache_key',
                        'raw_payload',
                    ],
                ],
            ],
            'validation_redaction_gate' => [
                'status' => 'backend-ready',
                'checks' => [
                    [
                        'key' => 'request-schema-known',
                        'label' => 'Request Schema Known',
                        'status' => 'passed',
                        'reason' => 'The Quick Generate mutation request shape is known and handled by the existing handoff route.',
                    ],
                    [
                        'key' => 'required-fields-defined',
                        'label' => 'Required Fields Defined',
                        'status' => 'passed',
                        'reason' => 'The Quick Generate form submits the required issuance fields to the existing GeneratePayCode request path.',
                    ],
                    [
                        'key' => 'validation-rules-wired',
                        'label' => 'Validation Rules Wired',
                        'status' => 'passed',
                        'reason' => 'The Cockpit handoff route uses GeneratePayCodeRequest-compatible validation.',
                    ],
                    [
                        'key' => 'sensitive-fields-redacted',
                        'label' => 'Sensitive Fields Redacted',
                        'status' => 'passed',
                        'reason' => 'Operator responses exclude raw payloads, provider payloads, wallet details, and idempotency internals.',
                    ],
                    [
                        'key' => 'sanitized-preview-ready',
                        'label' => 'Sanitized Preview Ready',
                        'status' => 'passed',
                        'reason' => 'The result panel renders sanitized generated facts and preflight summaries only.',
                    ],
                    [
                        'key' => 'validation-error-contract-ready',
                        'label' => 'Validation Error Contract Ready',
                        'status' => 'passed',
                        'reason' => 'Validation errors remain on the Quick Generate form through the Inertia handoff route.',
                    ],
                ],
                'redactions' => [
                    'payloads' => 'validation-redaction-gates-only',
                    'excluded' => [
                        'request_payload',
                        'validated_payload',
                        'validation_errors',
                        'mobile',
                        'email',
                        'recipient_reference',
                        'account_number',
                        'raw_payload',
                    ],
                ],
            ],
            'mutation_handoff_plan' => [
                'status' => 'backend-handoff-wired',
                'steps' => [
                    [
                        'key' => 'existing-issuance-owner-identified',
                        'label' => 'Existing Issuance Owner Identified',
                        'status' => 'passed',
                        'reason' => 'Quick Generate must hand off to the existing x-change issuance owner instead of inventing Cockpit generation behavior.',
                    ],
                    [
                        'key' => 'generate-pay-code-action-handoff',
                        'label' => 'GeneratePayCode Action Handoff',
                        'status' => 'passed',
                        'reason' => 'Cockpit POST route calls the existing GeneratePayCode action in Wave 1C.',
                    ],
                    [
                        'key' => 'generate-pay-code-controller-handoff',
                        'label' => 'GeneratePayCodeController Handoff',
                        'status' => 'confirmed',
                        'reason' => 'The public API route remains owned by GeneratePayCodeController while Cockpit shares the action directly.',
                    ],
                    [
                        'key' => 'preconditions-green',
                        'label' => 'Preconditions Green',
                        'status' => 'blocked',
                        'reason' => 'Provider, journal, action, and feedback side effects remain separately gated.',
                    ],
                    [
                        'key' => 'side-effect-boundary-confirmed',
                        'label' => 'Side Effect Boundary Confirmed',
                        'status' => 'passed',
                        'reason' => 'Cockpit does not call providers, wallets, journal, action, or feedback directly; issuance side effects remain behind GeneratePayCode.',
                    ],
                    [
                        'key' => 'operator-response-contract-ready',
                        'label' => 'Operator Response Contract Ready',
                        'status' => 'passed',
                        'reason' => 'Cockpit returns only operator-safe generated facts from the existing issuance action.',
                    ],
                ],
                'redactions' => [
                    'payloads' => 'mutation-handoff-plan-only',
                    'excluded' => [
                        'request_payload',
                        'validated_payload',
                        'mutation_payload',
                        'issued_voucher',
                        'generated_pay_code',
                        'provider_payload',
                        'wallet',
                        'journal_payload',
                        'action_payload',
                        'feedback_payload',
                        'side_effect_result',
                        'raw_payload',
                    ],
                ],
            ],
            'mutation_preconditions_review' => [
                'status' => 'existing-handoff-ready',
                'recommendation' => 'use-existing-issuance-handoff',
                'items' => [
                    [
                        'key' => 'authorization-ready',
                        'label' => 'Authorization Ready',
                        'status' => 'passed',
                        'reason' => 'The authenticated Cockpit route may submit through the approved GeneratePayCode handoff.',
                    ],
                    [
                        'key' => 'pricing-ready',
                        'label' => 'Pricing Ready',
                        'status' => 'runtime-informational',
                        'reason' => 'Pricing preflight is available in the operator-safe result panel after submit.',
                    ],
                    [
                        'key' => 'funding-ready',
                        'label' => 'Funding Ready',
                        'status' => 'runtime-informational',
                        'reason' => 'Funding preflight is available in the operator-safe result panel after submit; raw wallet details remain redacted.',
                    ],
                    [
                        'key' => 'idempotency-ready',
                        'label' => 'Idempotency Ready',
                        'status' => 'passed',
                        'reason' => 'Wave 1D wires idempotency key extraction, payload fingerprinting, replay lookup, conflict response, and TTL policy through the existing IdempotencyService.',
                    ],
                    [
                        'key' => 'validation-redaction-ready',
                        'label' => 'Validation and Redaction Ready',
                        'status' => 'passed',
                        'reason' => 'GeneratePayCodeRequest-compatible validation and operator-safe response redaction are wired.',
                    ],
                    [
                        'key' => 'handoff-ready',
                        'label' => 'Handoff Ready',
                        'status' => 'passed',
                        'reason' => 'Wave 1C wires the GeneratePayCode action handoff and confirms the public GeneratePayCodeController route remains unchanged.',
                    ],
                    [
                        'key' => 'operator-response-ready',
                        'label' => 'Operator Response Ready',
                        'status' => 'passed',
                        'reason' => 'Cockpit returns a redacted operator result with generated Pay Code, preflights, and activity runtime diagnostics.',
                    ],
                ],
                'redactions' => [
                    'payloads' => 'mutation-preconditions-review-only',
                    'excluded' => [
                        'request_payload',
                        'validated_payload',
                        'precondition_payload',
                        'mutation_approval',
                        'mutation_route',
                        'issued_voucher',
                        'generated_pay_code',
                        'provider_payload',
                        'wallet',
                        'side_effect_result',
                        'raw_payload',
                    ],
                ],
            ],
            'mutation_authorization_decision' => [
                'status' => 'approved-handoff',
                'decision' => 'authorized_existing_handoff',
                'required_approval' => 'completed-for-existing-generate-pay-code-handoff',
                'rationale' => 'Cockpit may submit Quick Generate through the existing GeneratePayCode action without inventing a parallel issuance runtime.',
                'next_step' => 'keep-provider-journal-action-feedback-mutations-separately-gated',
                'redactions' => [
                    'payloads' => 'mutation-authorization-decision-only',
                    'excluded' => [
                        'request_payload',
                        'validated_payload',
                        'mutation_payload',
                        'approval_payload',
                        'route_definition',
                        'issued_voucher',
                        'generated_pay_code',
                        'provider_payload',
                        'wallet',
                        'side_effect_result',
                        'raw_payload',
                    ],
                ],
            ],
            'mutation_contract' => [
                'schema' => 'x-change.cockpit.quick-generate-mutation.v1',
                'status' => 'existing_issuance_handoff_registered',
                'authorization' => 'operator-authenticated-handoff-route',
                'route' => 'x-change.cockpit.quick-generate.store',
                'route_url' => '/x/cockpit/quick-generate',
                'request_adapter' => 'GeneratePayCodeRequest-compatible-adapter',
                'issuance_owner' => 'GeneratePayCode',
                'idempotency' => 'replay-safe-route-registered',
                'response_contract' => 'operator-safe-redacted-result',
                'runtime_enabled' => true,
                'gates' => [
                    [
                        'key' => 'route-contract-defined',
                        'label' => 'Route Contract Defined',
                        'status' => 'passed',
                        'decision' => 'POST route is registered under the reserved route name.',
                        'reason' => 'Wave 1C reuses the Wave 1B route shell for the existing issuance handoff.',
                    ],
                    [
                        'key' => 'request-adapter-defined',
                        'label' => 'Request Adapter Defined',
                        'status' => 'passed',
                        'decision' => 'Cockpit route uses GeneratePayCodeRequest validation.',
                        'reason' => 'Cockpit does not invent a second issuance validation language.',
                    ],
                    [
                        'key' => 'issuance-owner-confirmed',
                        'label' => 'Issuance Owner Confirmed',
                        'status' => 'passed',
                        'decision' => 'GeneratePayCode remains the issuance owner.',
                        'reason' => 'Cockpit is an operator shell and must hand off to existing x-change issuance behavior.',
                    ],
                    [
                        'key' => 'idempotency-required',
                        'label' => 'Idempotency Required',
                        'status' => 'passed',
                        'decision' => 'Idempotency key and replay handling are wired through the existing IdempotencyService.',
                        'reason' => 'Repeated operator submits with the same key and payload replay the stored operator response without duplicate issuance.',
                    ],
                    [
                        'key' => 'operator-response-redacted',
                        'label' => 'Operator Response Redacted',
                        'status' => 'passed',
                        'decision' => 'Response exposes operator-safe generated facts only.',
                        'reason' => 'Provider payloads, wallet data, raw voucher payloads, secrets, and internal IDs remain excluded.',
                    ],
                    [
                        'key' => 'ui-submit-disabled',
                        'label' => 'UI Submit Enabled',
                        'status' => 'passed',
                        'decision' => 'Cockpit UI may submit only to the idempotency-protected route URL from the read model.',
                        'reason' => 'Wave 1E enables a guarded submit control while keeping refresh, redirect, and optimistic UI deferred.',
                    ],
                ],
                'allowed_methods' => ['GET', 'POST'],
                'redactions' => [
                    'payloads' => 'mutation-contract-only',
                    'excluded' => [
                        'request_payload',
                        'validated_payload',
                        'idempotency_key',
                        'payload_fingerprint',
                        'issued_voucher',
                        'generated_pay_code',
                        'provider_payload',
                        'wallet',
                        'balance',
                        'funding_source',
                        'journal_payload',
                        'action_payload',
                        'feedback_payload',
                        'raw_payload',
                    ],
                ],
            ],
            'draft_contract' => [
                'schema' => 'x-change.cockpit.quick-generate-draft.v1',
                'status' => 'draft_only',
                'template_key' => 'money-changer',
                'amount' => null,
                'currency' => 'PHP',
                'recipient_reference' => null,
                'purpose' => null,
                'idempotency_key' => null,
                'redactions' => [
                    'payloads' => 'draft-shape-only',
                    'excluded' => [
                        'mobile',
                        'email',
                        'wallet',
                        'balance',
                        'provider_payload',
                        'raw_payload',
                        'account_number',
                        'pricing_breakdown',
                        'funding_source',
                        'issuer_id',
                    ],
                ],
            ],
            'authorization' => [
                'status' => 'runtime-ready',
                'gates' => [
                    [
                        'key' => 'operator-authenticated',
                        'label' => 'Operator Authenticated',
                        'status' => 'passed',
                        'reason' => 'Authenticated Cockpit GET route resolved.',
                    ],
                    [
                        'key' => 'can-view-cockpit',
                        'label' => 'Can View Cockpit',
                        'status' => 'passed',
                        'reason' => 'Read-only Cockpit access is available.',
                    ],
                    [
                        'key' => 'can-generate-pay-code',
                        'label' => 'Can Generate Pay Code',
                        'status' => 'passed',
                        'reason' => 'The approved Cockpit Quick Generate mutation route submits through the existing GeneratePayCode action.',
                    ],
                    [
                        'key' => 'can-call-providers',
                        'label' => 'Can Call Providers',
                        'status' => 'blocked',
                        'reason' => 'Provider calls are outside the Slice 19 boundary.',
                    ],
                    [
                        'key' => 'can-move-money',
                        'label' => 'Can Move Money',
                        'status' => 'blocked',
                        'reason' => 'Money movement remains disabled in Cockpit.',
                    ],
                ],
                'redactions' => [
                    'payloads' => 'authorization-gates-only',
                    'excluded' => [
                        'roles',
                        'permissions',
                        'policy_payload',
                        'tenant_payload',
                        'provider_payload',
                        'raw_payload',
                    ],
                ],
            ],
            'action' => [
                'enabled' => true,
                'reason' => 'existing-issuance-handoff-enabled',
            ],
            'redactions' => [
                'payloads' => 'sanitized-quick-generate-catalog-only',
                'excluded' => [
                    'wallet',
                    'balance',
                    'provider_payload',
                    'raw_payload',
                    'account_number',
                    'pricing_breakdown',
                    'funding_source',
                    'issuer_id',
                ],
            ],
        ]);
});

it('binds the cockpit read model provider contract to the voucher lifecycle adapter baseline', function () {
    expect(app(CockpitReadModelProviderContract::class))
        ->toBeInstanceOf(VoucherLifecycleCockpitReadModelProvider::class);
});

it('keeps the cockpit read model baseline free of direct integration package dependencies', function () {
    $files = collect([
        ...glob(__DIR__.'/../../../src/Data/Cockpit/*ReadModel*.php'),
        ...glob(__DIR__.'/../../../src/Services/Cockpit/*ReadModel*.php'),
        __DIR__.'/../../../src/Services/Cockpit/OptionalCockpitIntegrationReadModels.php',
        __DIR__.'/../../../src/Services/Cockpit/VoucherLifecycleCockpitReadModelProvider.php',
        __DIR__.'/../../../src/Services/Cockpit/NullCockpitReadModelProvider.php',
        ...glob(__DIR__.'/../../../src/Contracts/CockpitReadModelProviderContract.php'),
    ])->filter(fn (string $file): bool => file_exists($file));

    $contents = $files
        ->map(fn (string $file): string => file_get_contents($file) ?: '')
        ->implode("\n");

    expect($contents)
        ->not->toContain('LBHurtado\\XJournal')
        ->not->toContain('LBHurtado\\XAction')
        ->not->toContain('LBHurtado\\XFeedback')
        ->not->toContain('LBHurtado\\XCampaign')
        ->not->toContain('FrittenKeeZ\\Vouchers\\Models\\Voucher');
});
