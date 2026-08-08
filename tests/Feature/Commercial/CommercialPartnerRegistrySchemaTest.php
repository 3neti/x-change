<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use LBHurtado\XChange\Enums\CommercialPartnerRevisionStatus;
use LBHurtado\XChange\Enums\CommercialPartnerStatus;
use LBHurtado\XChange\Models\CommercialPartner;
use LBHurtado\XChange\Models\CommercialPartnerDestinationRevision;
use LBHurtado\XChange\Models\CommercialPartnerRevision;

it('installs the additive commercial partner registry schema', function (): void {
    expect(Schema::hasColumns('x_change_commercial_partners', [
        'reference', 'display_name', 'status', 'created_by_type', 'created_by_id',
        'submitted_at', 'activated_at', 'suspended_at', 'retired_at',
    ]))->toBeTrue()
        ->and(Schema::hasColumns('x_change_commercial_partner_revisions', [
            'commercial_partner_id', 'version', 'status', 'attribution_basis',
            'authorization_reference', 'terms', 'snapshot_hash', 'maker_type', 'checker_type',
        ]))->toBeTrue()
        ->and(Schema::hasColumns('x_change_commercial_partner_destination_revisions', [
            'commercial_partner_id', 'commercial_partner_revision_id', 'provider',
            'connection_reference', 'currency', 'destination', 'destination_hash',
            'destination_summary', 'authorization_reference',
        ]))->toBeTrue()
        ->and(Schema::hasColumns('x_change_commercial_partner_legacy_mappings', [
            'legacy_partner_reference', 'commercial_partner_id', 'commercial_partner_revision_id',
            'authorization_reference', 'mapped_at',
        ]))->toBeTrue()
        ->and(Schema::hasColumns('x_change_commercial_allocations', [
            'commercial_partner_id', 'commercial_partner_revision_id', 'legacy_partner_reference',
        ]))->toBeTrue()
        ->and(Schema::hasColumns('x_change_partner_commission_payout_batches', [
            'commercial_partner_id', 'commercial_partner_revision_id',
            'commercial_partner_destination_revision_id',
        ]))->toBeTrue();
});

it('encrypts partner payout destinations and preserves immutable commercial terms', function (): void {
    $operator = actingAsTestUser();
    $partner = CommercialPartner::query()->create([
        'reference' => 'partner:acceptance',
        'display_name' => 'Acceptance Partner',
        'status' => CommercialPartnerStatus::Draft,
        'created_by_type' => $operator->getMorphClass(),
        'created_by_id' => $operator->getKey(),
    ]);
    $revision = CommercialPartnerRevision::query()->create([
        'commercial_partner_id' => $partner->getKey(),
        'version' => 1,
        'status' => CommercialPartnerRevisionStatus::Approved,
        'display_name' => 'Acceptance Partner',
        'legal_name' => 'Acceptance Partner Incorporated',
        'attribution_basis' => 'contractual_referral',
        'authorization_reference' => 'contract:acceptance:2026',
        'terms' => ['commission_basis' => 'fixed'],
        'snapshot_hash' => str_repeat('a', 64),
        'maker_type' => $operator->getMorphClass(),
        'maker_id' => $operator->getKey(),
        'effective_at' => now(),
    ]);
    $destination = CommercialPartnerDestinationRevision::query()->create([
        'commercial_partner_id' => $partner->getKey(),
        'commercial_partner_revision_id' => $revision->getKey(),
        'version' => 1,
        'status' => CommercialPartnerRevisionStatus::Approved,
        'provider' => 'netbank',
        'connection_reference' => 'netbank-primary',
        'currency' => 'PHP',
        'destination' => [
            'bank_code' => 'GXCHPHM2XXX',
            'account_number' => '09171234567',
            'recipient_name' => 'Acceptance Partner',
        ],
        'destination_hash' => str_repeat('b', 64),
        'destination_summary' => 'GCash · ••••4567',
        'maker_type' => $operator->getMorphClass(),
        'maker_id' => $operator->getKey(),
        'authorization_reference' => 'board:destination:acceptance',
        'effective_at' => now(),
    ]);

    $storedDestination = DB::table('x_change_commercial_partner_destination_revisions')
        ->where('id', $destination->getKey())
        ->value('destination');

    expect($destination->fresh()->destination['account_number'])->toBe('09171234567')
        ->and($storedDestination)->not->toContain('09171234567')
        ->and(fn () => $revision->update(['legal_name' => 'Changed Name']))
        ->toThrow(LogicException::class, 'content is immutable');
});
