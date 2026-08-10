<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Storage;
use LBHurtado\XChange\Actions\Redemption\RecordVoucherClaim;
use LBHurtado\XChange\Data\Redemption\SubmitPayCodeClaimResultData;
use LBHurtado\XJournal\Models\ExecutionJournalEntry;

it('projects authoritative values, readable instructions, and backing without raw payloads', function (): void {
    $owner = actingAsTestUser();
    $voucher = issueVoucher(validVoucherInstructions(20.00, overrides: [
        'inputs' => ['fields' => ['mobile', 'signature']],
        'rider' => ['message' => 'Payroll assistance'],
    ]));
    $voucher->forceFill([
        'metadata' => array_replace_recursive($voucher->metadata, [
            'treasury' => [
                'pay_code_reservation' => [
                    'status' => 'reserved',
                    'amount_minor' => 2_000,
                    'currency' => 'PHP',
                    'provider' => 'netbank',
                    'connection_reference' => 'netbank-primary',
                    'operation_reference' => 'reservation:test-record-workspace',
                ],
            ],
        ]),
    ])->save();

    $response = $this->actingAs($owner)
        ->withHeader('X-Inertia', 'true')
        ->get(route('x-change.cockpit.pay-codes.show', $voucher->code))
        ->assertOk()
        ->assertJsonPath('props.read_model.voucher.overview.schema', 'x-change.cockpit.pay-code-overview.v1')
        ->assertJsonPath('props.read_model.voucher.overview.amounts.1.key', 'reserved_principal')
        ->assertJsonPath('props.read_model.voucher.overview.amounts.1.amount_minor', 2_000)
        ->assertJsonPath('props.read_model.voucher.overview.amounts.1.authority', 'treasury_position')
        ->assertJsonPath('props.read_model.voucher.overview.amounts.1.primary', true)
        ->assertJsonPath('props.read_model.voucher.instructions.schema', 'x-change.cockpit.pay-code-instructions.v1')
        ->assertJsonPath('props.read_model.voucher.instructions.groups.1.key', 'claim')
        ->assertJsonPath('props.read_model.voucher.instructions.groups.1.facts.0.label', 'Required Inputs')
        ->assertJsonPath('props.read_model.voucher.instructions.groups.1.facts.0.value', 'Mobile, Signature')
        ->assertJsonPath('props.read_model.voucher.instructions.raw_payload_exposed', false)
        ->assertJsonPath('props.read_model.voucher.treasury.backing.mode', 'treasury_position')
        ->assertJsonPath('props.read_model.voucher.treasury.backing.cash_entity_present', true)
        ->assertJsonPath('props.read_model.voucher.treasury.provider_calls_on_read', false)
        ->assertJsonMissingPath('props.read_model.voucher.instructions.cash')
        ->assertJsonMissingPath('props.read_model.voucher.settlement.envelope.payload');
});

it('limits a Pay Code record to its owner or the system principal', function (): void {
    $owner = actingAsTestUser();
    $voucher = issueVoucher();
    $systemPrincipal = enableNetbankTreasuryForTests();

    $this->actingAs($systemPrincipal)
        ->withHeader('X-Inertia', 'true')
        ->get(route('x-change.cockpit.pay-codes.show', $voucher->code))
        ->assertOk();

    $otherAccountHolder = actingAsTestUser();

    $this->actingAs($otherAccountHolder)
        ->get(route('x-change.cockpit.pay-codes.show', $voucher->code))
        ->assertNotFound();

    $this->actingAs($owner)
        ->withHeader('X-Inertia', 'true')
        ->get(route('x-change.cockpit.pay-codes.show', $voucher->code))
        ->assertOk();
});

it('serves a versioned sanitized Engineering Preview only to an authorized actor', function (): void {
    $owner = actingAsTestUser();
    $voucher = issueVoucher(validVoucherInstructions(20.00, overrides: [
        'cash' => [
            'validation' => [
                'secret' => 'never-expose-this-secret',
                'mobile' => '09173011987',
            ],
        ],
        'feedback' => [
            'email' => 'private-issuer@example.test',
            'mobile' => '09173011987',
        ],
    ]));
    app(RecordVoucherClaim::class)->handle(
        $voucher,
        new SubmitPayCodeClaimResultData(
            voucher_code: $voucher->code,
            claim_type: 'redeem',
            claimed: true,
            status: 'succeeded',
            requested_amount: 20,
            disbursed_amount: 20,
            currency: 'PHP',
            remaining_balance: 0,
            fully_claimed: true,
            disbursement: [],
            messages: ['Claim completed.'],
        ),
        [
            'mobile' => '09173011987',
            'account_number' => '09173011987',
            'inputs' => [
                'account_number' => '09173011987',
                'name' => 'Private Claimant',
                'location' => ['formatted_address' => 'Private Location'],
            ],
        ],
    );

    $response = $this->actingAs($owner)
        ->getJson(route('x-change.cockpit.pay-codes.engineering-preview.show', [
            'code' => $voucher->code,
        ]))
        ->assertOk()
        ->assertHeader('Cache-Control', 'max-age=0, no-store, private')
        ->assertJsonPath('schema', 'x-change.cockpit.pay-code-engineering-preview.v1')
        ->assertJsonPath('pay_code.code', $voucher->code)
        ->assertJsonPath('instructions.raw_payload_exposed', false)
        ->assertJsonPath('claims.redactions.binary_evidence_in_page_props', false)
        ->assertJsonPath('redactions.binary_evidence', 'excluded')
        ->assertJsonPath('redactions.raw_provider_payloads', 'excluded')
        ->assertJsonPath('redactions.credentials_and_secrets', 'excluded')
        ->assertJsonPath('claims.evidence.0.value', '[redacted]');

    expect($response->getContent())
        ->not->toContain('never-expose-this-secret')
        ->not->toContain('private-issuer@example.test')
        ->not->toContain('09173011987')
        ->not->toContain('Private Claimant')
        ->not->toContain('Private Location');

    $otherAccountHolder = actingAsTestUser();

    $this->actingAs($otherAccountHolder)
        ->getJson(route('x-change.cockpit.pay-codes.engineering-preview.show', [
            'code' => $voucher->code,
        ]))
        ->assertNotFound();
});

it('reveals binary claim evidence only through a no-store audited endpoint', function (): void {
    $owner = actingAsTestUser();
    $voucher = issueVoucher();
    $png = base64_decode(
        'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
        true,
    );
    $payload = 'data:image/png;base64,'.base64_encode($png);

    $voucher->forceSetInput('signature', $payload);
    $input = $voucher->inputs()->where('name', 'signature')->sole();

    $page = $this->actingAs($owner)
        ->withHeader('X-Inertia', 'true')
        ->get(route('x-change.cockpit.pay-codes.show', $voucher->code))
        ->assertOk()
        ->assertJsonPath('props.read_model.voucher.claims.evidence.0.key', 'signature')
        ->assertJsonPath('props.read_model.voucher.claims.evidence.0.value', null)
        ->assertJsonPath('props.read_model.voucher.claims.evidence.0.revealable', true)
        ->assertJsonPath('props.read_model.voucher.claims.redactions.binary_evidence_in_page_props', false);

    expect($page->getContent())->not->toContain(base64_encode($png));

    $this->get(route('x-change.cockpit.pay-codes.evidence.show', [
        'code' => $voucher->code,
        'source' => 'input',
        'evidence' => $input->getKey(),
    ]))
        ->assertOk()
        ->assertHeader('Content-Type', 'image/png')
        ->assertHeader('Cache-Control', 'max-age=0, no-store, private')
        ->assertContent($png);

    $entry = ExecutionJournalEntry::query()
        ->where('event_type', 'pay_code.evidence.viewed')
        ->latest('id')
        ->sole();

    expect(data_get($entry->payload, 'evidence_type'))->toBe('signature')
        ->and(data_get($entry->payload, 'binary_payload_persisted'))->toBeFalse()
        ->and(data_get($entry->metadata, 'sensitive_access'))->toBeTrue();
});

it('projects new evidence per claim and reveals its private content-addressed artifact', function (): void {
    Storage::fake('local');
    $owner = actingAsTestUser();
    $voucher = issueVoucher(validVoucherInstructions(20.00, overrides: [
        'inputs' => ['fields' => ['name', 'selfie', 'signature']],
    ]));
    $png = base64_decode(
        'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
        true,
    );
    $claim = app(RecordVoucherClaim::class)->handle(
        $voucher,
        new SubmitPayCodeClaimResultData(
            voucher_code: $voucher->code,
            claim_type: 'redeem',
            claimed: true,
            status: 'succeeded',
            requested_amount: 20,
            disbursed_amount: 20,
            currency: 'PHP',
            remaining_balance: 0,
            fully_claimed: true,
            disbursement: [],
            messages: ['Claim completed.'],
        ),
        [
            'inputs' => [
                'name' => 'Amelia Hurtado',
                'selfie' => 'data:image/png;base64,'.base64_encode($png),
                'signature' => 'data:image/png;base64,'.base64_encode($png),
            ],
        ],
    );
    $selfie = $claim->evidence()->where('requirement_key', 'selfie')->sole();

    Storage::disk('local')->assertExists((string) $selfie->artifact_path);

    $page = $this->actingAs($owner)
        ->withHeader('X-Inertia', 'true')
        ->get(route('x-change.cockpit.pay-codes.show', $voucher->code))
        ->assertOk()
        ->assertJsonPath('props.read_model.voucher.claims.records.0.evidence.required_count', 3)
        ->assertJsonPath('props.read_model.voucher.claims.records.0.evidence.captured_count', 3)
        ->assertJsonPath('props.read_model.voucher.claims.records.0.evidence.complete', true)
        ->assertJsonPath('props.read_model.voucher.claims.evidence.1.claim_id', $claim->getKey())
        ->assertJsonPath('props.read_model.voucher.claims.evidence.1.group', 'media')
        ->assertJsonPath('props.read_model.voucher.claims.evidence.1.artifact_status', 'available')
        ->assertJsonPath('props.read_model.voucher.claims.evidence.1.revealable', true)
        ->assertJsonPath('props.read_model.voucher.claims.evidence.1.legacy', false);

    expect($page->getContent())->not->toContain(base64_encode($png));

    $this->get(route('x-change.cockpit.pay-codes.evidence.show', [
        'code' => $voucher->code,
        'source' => 'claim',
        'evidence' => $selfie->getKey(),
    ]))
        ->assertOk()
        ->assertHeader('Content-Type', 'image/png')
        ->assertHeader('Cache-Control', 'max-age=0, no-store, private')
        ->assertContent($png);
});

it('retains an evidence record without offering a broken reveal when its private artifact is missing', function (): void {
    Storage::fake('local');
    $owner = actingAsTestUser();
    $voucher = issueVoucher(validVoucherInstructions(20.00, overrides: [
        'inputs' => ['fields' => ['selfie']],
    ]));
    $png = base64_decode(
        'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
        true,
    );
    $claim = app(RecordVoucherClaim::class)->handle(
        $voucher,
        new SubmitPayCodeClaimResultData(
            voucher_code: $voucher->code,
            claim_type: 'redeem',
            claimed: true,
            status: 'succeeded',
            requested_amount: 20,
            disbursed_amount: 20,
            currency: 'PHP',
            remaining_balance: 0,
            fully_claimed: true,
            disbursement: [],
            messages: ['Claim completed.'],
        ),
        [
            'inputs' => [
                'selfie' => 'data:image/png;base64,'.base64_encode($png),
            ],
        ],
    );
    $selfie = $claim->evidence()->where('requirement_key', 'selfie')->sole();

    Storage::disk('local')->delete((string) $selfie->artifact_path);

    $this->actingAs($owner)
        ->withHeader('X-Inertia', 'true')
        ->get(route('x-change.cockpit.pay-codes.show', $voucher->code))
        ->assertOk()
        ->assertJsonPath('props.read_model.voucher.claims.evidence.0.key', 'selfie')
        ->assertJsonPath('props.read_model.voucher.claims.evidence.0.status', 'captured')
        ->assertJsonPath('props.read_model.voucher.claims.evidence.0.artifact_status', 'missing')
        ->assertJsonPath('props.read_model.voucher.claims.evidence.0.revealable', false)
        ->assertJsonPath('props.read_model.voucher.claims.evidence.0.reveal_href', null);

    $this->get(route('x-change.cockpit.pay-codes.evidence.show', [
        'code' => $voucher->code,
        'source' => 'claim',
        'evidence' => $selfie->getKey(),
    ]))->assertNotFound();
});

it('presents captured location evidence with an audited map reveal', function (): void {
    $owner = actingAsTestUser();
    $voucher = issueVoucher();
    $png = base64_decode(
        'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
        true,
    );
    $voucher->inputs()->create([
        'name' => 'location',
        'value' => json_encode([
            'latitude' => 14.5995,
            'longitude' => 121.0288,
            'formatted_address' => 'Makati City',
            'map' => 'data:image/png;base64,'.base64_encode($png),
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
    ]);
    $input = $voucher->inputs()->where('name', 'location')->sole();

    $this->actingAs($owner)
        ->withHeader('X-Inertia', 'true')
        ->get(route('x-change.cockpit.pay-codes.show', $voucher->code))
        ->assertOk()
        ->assertJsonPath('props.read_model.voucher.claims.evidence.0.key', 'location')
        ->assertJsonPath('props.read_model.voucher.claims.evidence.0.value', 'Makati City')
        ->assertJsonPath('props.read_model.voucher.claims.evidence.0.revealable', true);

    $this->get(route('x-change.cockpit.pay-codes.evidence.show', [
        'code' => $voucher->code,
        'source' => 'input',
        'evidence' => $input->getKey(),
    ]))
        ->assertOk()
        ->assertHeader('Content-Type', 'image/png')
        ->assertHeader('Cache-Control', 'max-age=0, no-store, private')
        ->assertContent($png);
});

it('does not reveal another Account holder evidence', function (): void {
    actingAsTestUser();
    $voucher = issueVoucher();
    $voucher->forceSetInput(
        'signature',
        'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
    );
    $input = $voucher->inputs()->where('name', 'signature')->sole();
    $otherAccountHolder = actingAsTestUser();

    $this->actingAs($otherAccountHolder)
        ->get(route('x-change.cockpit.pay-codes.evidence.show', [
            'code' => $voucher->code,
            'source' => 'input',
            'evidence' => $input->getKey(),
        ]))
        ->assertNotFound();

    expect(ExecutionJournalEntry::query()
        ->where('event_type', 'pay_code.evidence.viewed')
        ->count())->toBe(0);
});
