<?php

declare(strict_types=1);

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
