<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use LBHurtado\XChange\Enums\CommercialBillableEventStatus;
use LBHurtado\XChange\Exceptions\CommercialSaleConflict;
use LBHurtado\XChange\Models\CommercialBillableEvent;
use LBHurtado\XChange\Models\CommercialRecognitionPolicy;
use LBHurtado\XChange\Models\CommercialSale;
use LBHurtado\XChange\Services\Commercial\CommercialBillableEventRecorder;
use LBHurtado\XChange\Services\Commercial\PayCodeCommercialQuoteService;
use LBHurtado\XChange\Services\Commercial\ProvisionCommercialBaselines;
use LBHurtado\XCommerce\Services\DeterministicCommercialSaleFactory;

beforeEach(function (): void {
    config()->set('x-change.commercial.legal_trace.legal_entity_reference', 'legal-entity:x-change:test');
    config()->set('x-change.commercial.legal_trace.profile_version', 'test-v1');
    app(ProvisionCommercialBaselines::class)->provision('commissioning-manifest:billable-event');
});

it('records governed component events exactly once and rejects changed replay evidence', function (): void {
    $quote = app(PayCodeCommercialQuoteService::class)->quote(
        validVoucherInstructions(100, 'INSTAPAY'),
        'pay-code-generation:voucher:1001',
    );
    $snapshot = (new DeterministicCommercialSaleFactory)->accept(
        quote: $quote,
        acceptanceEventReference: 'acceptance:billable-event:1',
        buyerReference: 'principal:test:1',
        acceptedAt: now()->toRfc3339String(),
    );
    $sale = CommercialSale::query()->create([
        'reference' => 'sale:billable-event:1',
        'acceptance_event_reference' => 'acceptance:billable-event:1',
        'source_commercial_event_reference' => 'pay-code-generation:voucher:1001',
        'buyer_reference' => 'principal:test:1',
        'quote_reference' => $quote->reference,
        'catalog_reference' => $quote->catalogSnapshot->reference,
        'catalog_version' => $quote->catalogSnapshot->version,
        'waterfall_policy_reference' => $quote->waterfallPolicySnapshot->reference,
        'waterfall_policy_version' => $quote->waterfallPolicySnapshot->version,
        'attribution_reference' => $quote->attributionSnapshot->reference,
        'attribution_version' => $quote->attributionSnapshot->version,
        'currency' => $quote->currency,
        'total_price_minor' => $quote->totalPriceMinor,
        'snapshot_hash' => str_repeat('a', 64),
        'snapshot' => [],
        'source_client_funds_position_reference' => 'position:client-funds',
        'commercial_clearing_position_reference' => 'position:commercial-clearing',
        'status' => 'accepted',
        'accepted_at' => now(),
    ]);
    $recorder = app(CommercialBillableEventRecorder::class);

    $first = $recorder->recordForSale($sale, $snapshot);
    $replay = $recorder->recordForSale($sale, $snapshot);

    expect($first)->toHaveCount(count($quote->lines))
        ->and($replay->pluck('id')->all())->toBe($first->pluck('id')->all())
        ->and(CommercialBillableEvent::query()->count())->toBe(count($quote->lines))
        ->and(CommercialRecognitionPolicy::query()->count())->toBe(1)
        ->and($first->every(
            static fn (CommercialBillableEvent $event): bool => $event->status === CommercialBillableEventStatus::Received,
        ))->toBeTrue()
        ->and($first->every(
            static fn (CommercialBillableEvent $event): bool => $event->recognition_policy_version === 1
                && $event->commercial_recognition_policy_id !== null
                && $event->recognition_policy_snapshot === [
                    'reference' => 'recognition:pay-code-issuance:v1',
                    'version' => 1,
                    'billable_event_references' => ['pay_code.issued_with_component'],
                    'trigger' => 'commercial_sale.accepted',
                    'timing' => 'immediate',
                ]
                && preg_match('/^[a-f0-9]{64}$/', (string) $event->recognition_policy_hash) === 1,
        ))->toBeTrue()
        ->and((int) $first->sum('total_amount_minor'))->toBe($quote->totalPriceMinor);

    $recorder->markPostedForSale($sale);
    $recorder->markReversedForSale($sale, 'administrative-void:billable-event');
    $recorder->markReversedForSale($sale, 'administrative-void:billable-event');

    expect(CommercialBillableEvent::query()
        ->where('status', CommercialBillableEventStatus::Reversed->value)
        ->where('reversal_reference', 'administrative-void:billable-event')
        ->count())->toBe(count($quote->lines))
        ->and(fn () => $recorder->markReversedForSale($sale, 'administrative-void:different'))
        ->toThrow(CommercialSaleConflict::class);

    $originalPolicies = config('x-change.commercial.recognition_policies.policies');
    config()->set(
        'x-change.commercial.recognition_policies.policies.recognition:pay-code-issuance:v1.billable_event_references',
        ['pay_code.issued_with_component', 'pay_code.issued_with_another_component'],
    );

    expect(fn () => $recorder->recordForSale($sale, $snapshot))
        ->toThrow(CommercialSaleConflict::class, 'changed without a new version');

    config()->set('x-change.commercial.recognition_policies.policies', $originalPolicies);

    DB::table('x_change_commercial_billable_events')
        ->where('id', $first->firstOrFail()->getKey())
        ->update(['payload_hash' => str_repeat('b', 64)]);

    expect(fn () => $recorder->recordForSale($sale, $snapshot))
        ->toThrow(CommercialSaleConflict::class);
});

it('fails closed when recognition authority is missing mismatched or deferred', function (
    array $policies,
    string $message,
): void {
    $quote = app(PayCodeCommercialQuoteService::class)->quote(
        validVoucherInstructions(100, 'INSTAPAY'),
        'pay-code-generation:recognition-policy-rejection',
    );
    $snapshot = (new DeterministicCommercialSaleFactory)->accept(
        quote: $quote,
        acceptanceEventReference: 'acceptance:recognition-policy-rejection',
        buyerReference: 'principal:test:recognition-policy-rejection',
        acceptedAt: now()->toRfc3339String(),
    );
    $sale = CommercialSale::query()->create([
        'reference' => 'sale:recognition-policy-rejection',
        'acceptance_event_reference' => 'acceptance:recognition-policy-rejection',
        'source_commercial_event_reference' => 'pay-code-generation:recognition-policy-rejection',
        'buyer_reference' => 'principal:test:recognition-policy-rejection',
        'quote_reference' => $quote->reference,
        'catalog_reference' => $quote->catalogSnapshot->reference,
        'catalog_version' => $quote->catalogSnapshot->version,
        'waterfall_policy_reference' => $quote->waterfallPolicySnapshot->reference,
        'waterfall_policy_version' => $quote->waterfallPolicySnapshot->version,
        'attribution_reference' => $quote->attributionSnapshot->reference,
        'attribution_version' => $quote->attributionSnapshot->version,
        'currency' => $quote->currency,
        'total_price_minor' => $quote->totalPriceMinor,
        'snapshot_hash' => str_repeat('c', 64),
        'snapshot' => [],
        'source_client_funds_position_reference' => 'position:client-funds',
        'commercial_clearing_position_reference' => 'position:commercial-clearing',
        'status' => 'accepted',
        'accepted_at' => now(),
    ]);

    config()->set('x-change.commercial.recognition_policies.policies', $policies);

    expect(fn () => app(CommercialBillableEventRecorder::class)->recordForSale($sale, $snapshot))
        ->toThrow(CommercialSaleConflict::class, $message)
        ->and(CommercialBillableEvent::query()->count())->toBe(0);
})->with([
    'unknown policy' => [[], 'not governed or active'],
    'mismatched event' => [[
        'recognition:pay-code-issuance:v1' => [
            'version' => 1,
            'billable_event_references' => ['otp.delivered'],
            'trigger' => 'commercial_sale.accepted',
            'timing' => 'immediate',
        ],
    ], 'does not authorize Billable Event'],
    'deferred policy' => [[
        'recognition:pay-code-issuance:v1' => [
            'version' => 1,
            'billable_event_references' => ['pay_code.issued_with_component'],
            'trigger' => 'provider.service.completed',
            'timing' => 'deferred',
        ],
    ], 'not supported by immediate Commercial Sale posting'],
]);
