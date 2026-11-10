<?php

declare(strict_types=1);

use LBHurtado\XChange\Contracts\CommercialComponentEconomicsResolverContract;
use LBHurtado\XChange\Contracts\CommercialOfferingResolverContract;
use LBHurtado\XChange\Enums\CommercialOperatorCapability;
use LBHurtado\XChange\Models\CommercialOfferingActivation;
use LBHurtado\XChange\Models\CommercialOperatorAuthorization;
use LBHurtado\XChange\Services\Commercial\CommissionDocumentPricing;
use LBHurtado\XChange\Services\Commercial\ProvisionCommercialBaselines;
use LBHurtado\XJournal\Models\ExecutionJournalEntry;

beforeEach(function (): void {
    config()->set('x-change.commercial.legal_trace.legal_entity_reference', 'legal-entity:x-change:test');
    config()->set('x-change.commercial.legal_trace.profile_version', 'test-v1');
    $catalog = config('x-commerce.catalogs.pay_code');
    $documentItem = $catalog['items']['inputs.fields.documents'];
    unset($catalog['items']['inputs.fields.documents']);
    $catalog['version'] = 4;
    config()->set('x-commerce.catalogs.pay_code', $catalog);
    app(ProvisionCommercialBaselines::class)->provision('commissioning-manifest:document-test');
    $catalog['items']['inputs.fields.documents'] = $documentItem;
    $catalog['version'] = 5;
    config()->set('x-commerce.catalogs.pay_code', $catalog);
});

it('requires a designated operator to commission the document fee', function (): void {
    $operator = actingAsTestUser();
    expect(fn () => app(CommissionDocumentPricing::class)->execute($operator, 'owner-request:test'))
        ->toThrow(DomainException::class);
});

it('rejects an unauthorized operator through the commissioning command', function (): void {
    $operator = actingAsTestUser();
    $this->artisan('x-change:commercial:commission-document-pricing', [
        'operator' => $operator->getKey(), '--authorization-reference' => 'owner-request:test',
    ])->assertFailed();
});

it('rejects an authorization reference that does not belong to the operator', function (): void {
    $operator = actingAsTestUser();
    CommercialOperatorAuthorization::query()->create([
        'operator_type' => $operator->getMorphClass(), 'operator_id' => $operator->getKey(),
        'capability' => CommercialOperatorCapability::CommissionDocumentPricing->value,
        'authorization_reference' => 'owner-request:real', 'valid_from' => now()->subMinute(),
    ]);
    expect(fn () => app(CommissionDocumentPricing::class)->execute($operator, 'owner-request:other'))
        ->toThrow(DomainException::class);
});

it('does not change the active catalog when the commissioned allocation policy differs', function (): void {
    $operator = actingAsTestUser();
    CommercialOperatorAuthorization::query()->create([
        'operator_type' => $operator->getMorphClass(), 'operator_id' => $operator->getKey(),
        'capability' => CommercialOperatorCapability::CommissionDocumentPricing->value,
        'authorization_reference' => 'owner-request:test', 'valid_from' => now()->subMinute(),
    ]);
    $before = CommercialOfferingActivation::query()->where('profile', 'pay_code')->whereNull('deactivated_at')->sole();
    config()->set('x-change.commercial.component_economics.bootstrap.recipient_reference', 'counterparty:unexpected');

    expect(fn () => app(CommissionDocumentPricing::class)->execute($operator, 'owner-request:test'))
        ->toThrow(DomainException::class, 'Existing component economics differ');
    expect(CommercialOfferingActivation::query()->where('profile', 'pay_code')->whereNull('deactivated_at')->sole()->id)
        ->toBe($before->id);
});

it('commissions the fee once while preserving existing prices and economics', function (): void {
    $operator = actingAsTestUser();
    CommercialOperatorAuthorization::query()->create([
        'operator_type' => $operator->getMorphClass(), 'operator_id' => $operator->getKey(),
        'capability' => CommercialOperatorCapability::CommissionDocumentPricing->value,
        'authorization_reference' => 'owner-request:test', 'valid_from' => now(),
    ]);
    $before = app(CommercialOfferingResolverContract::class)->resolve('pay_code');
    $oldEconomics = app(CommercialComponentEconomicsResolverContract::class)->resolve('pay_code');
    $activation = app(CommissionDocumentPricing::class)->execute($operator, 'owner-request:test');
    $after = app(CommercialOfferingResolverContract::class)->resolve('pay_code');
    $economics = app(CommercialComponentEconomicsResolverContract::class)->resolve('pay_code');

    expect(collect($after->catalog->items)->firstWhere('reference', 'inputs.fields.documents')->unitPriceMinor)->toBe(200)
        ->and(count($after->catalog->items))->toBe(count($before->catalog->items) + 1)
        ->and(collect($economics->components)->firstWhere('componentReference', 'cash.amount')->toArray())
        ->toBe(collect($oldEconomics->components)->firstWhere('componentReference', 'cash.amount')->toArray())
        ->and(app(CommissionDocumentPricing::class)->execute($operator, 'owner-request:test')->id)->toBe($activation->id)
        ->and($activation->offering->authorization_reference)->toBe('owner-request:test');

    $event = ExecutionJournalEntry::query()->where('event_type', 'commercial.offering.single_operator_authorized')->sole();
    expect(data_get($event->actor, 'id'))->toBe((string) $operator->getKey())
        ->and(data_get($event->references, 'external_reference'))->toBe('owner-request:test');
});
