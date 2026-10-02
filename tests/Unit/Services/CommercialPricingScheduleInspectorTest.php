<?php

declare(strict_types=1);

use LBHurtado\XChange\Exceptions\PayCodeIssuanceFailed;
use LBHurtado\XChange\Services\Commercial\CommercialCustomerChargingGuard;
use LBHurtado\XChange\Services\Commercial\CommercialPricingScheduleInspector;

it('reconciles the approved beta schedule while withholding customer charging', function (): void {
    $status = app(CommercialPricingScheduleInspector::class)->inspect();

    expect($status)->toMatchArray([
        'reference' => 'beta-pricing-schedule-v1',
        'version' => 1,
        'status' => 'approved',
        'effective_at' => '2026-09-27T00:00:00+08:00',
        'currency' => 'PHP',
        'schedule_ready' => true,
        'customer_charging_ready' => false,
        'customer_charging_authorized' => false,
    ])->and($status['catalog'])->toMatchArray([
        'reference' => 'pay-code',
        'version' => 3,
        'matches_approved_schedule' => true,
    ])->and($status['principal'])->toMatchArray([
        'treatment' => 'excluded',
        'excluded_from_charges_and_revenue' => true,
    ])->and($status['customer_authorization'])->toMatchArray([
        'mode' => 'explicit_quote_acceptance',
        'explicit' => true,
    ])->and($status['tax'])->toMatchArray([
        'treatment' => 'review_required',
        'resolved' => false,
    ])->and($status['invoicing_authority'])->toMatchArray([
        'status' => 'unconfigured',
        'ready' => false,
        'tin_configured' => false,
        'registered_address_configured' => false,
    ])->and($status['receipt_reporting'])->toMatchArray([
        'status' => 'verified',
        'verified' => true,
        'document_kind' => 'commercial_charge_confirmation',
    ]);
});

it('detects drift from the approved catalog snapshot', function (): void {
    $catalog = config('x-commerce.catalogs.pay_code');
    data_set($catalog, 'items.cash.amount.unit_price_minor', 1_501);
    config()->set('x-commerce.catalogs.pay_code', $catalog);

    $status = app(CommercialPricingScheduleInspector::class)->inspect();

    expect($status['schedule_ready'])->toBeFalse()
        ->and($status['customer_charging_ready'])->toBeFalse()
        ->and(data_get($status, 'catalog.matches_approved_schedule'))->toBeFalse();
});

it('rejects a waterfall that classifies principal as revenue', function (): void {
    $rules = config('x-change.commercial.pay_code.waterfall.rules');
    $rules[] = [
        'reference' => 'forbidden-principal-revenue',
        'sequence' => 99,
        'line_type' => 'allocation',
        'category' => 'principal',
        'recipient_reference' => 'operator:x-change',
        'fixed_amount_minor' => 100,
    ];
    config()->set('x-change.commercial.pay_code.waterfall.rules', $rules);

    $status = app(CommercialPricingScheduleInspector::class)->inspect();

    expect($status['schedule_ready'])->toBeFalse()
        ->and(data_get($status, 'principal.excluded_from_charges_and_revenue'))->toBeFalse();
});

it('requires resolved tax treatment invoicing authority and explicit authorization before charging', function (): void {
    config()->set('x-change.commercial.pricing_schedule.tax_treatment', 'resolved');
    config()->set('x-change.commercial.pricing_schedule.customer_charging_authorized', true);

    $status = app(CommercialPricingScheduleInspector::class)->inspect();

    expect($status['schedule_ready'])->toBeTrue()
        ->and($status['customer_charging_ready'])->toBeFalse()
        ->and(data_get($status, 'invoicing_authority.ready'))->toBeFalse();
});

it('accepts a complete approved Philippine invoicing authority profile', function (): void {
    config()->set('x-change.commercial.pricing_schedule.tax_treatment', 'resolved');
    config()->set('x-change.commercial.pricing_schedule.customer_charging_authorized', true);
    configureCommercialInvoicingAuthority();

    $status = app(CommercialPricingScheduleInspector::class)->inspect();

    expect($status['customer_charging_ready'])->toBeTrue()
        ->and($status['invoicing_authority'])->toMatchArray([
            'status' => 'approved',
            'ready' => true,
            'tax_registration' => 'vat',
            'document_type' => 'vat_invoice',
            'invoice_every_charge' => true,
        ])
        ->and(data_get($status, 'invoicing_authority.issuer_reference_hash'))->toHaveLength(64);
});

it('rejects an invoice document that conflicts with the registered tax status', function (): void {
    config()->set('x-change.commercial.pricing_schedule.tax_treatment', 'resolved');
    config()->set('x-change.commercial.pricing_schedule.customer_charging_authorized', true);
    configureCommercialInvoicingAuthority();
    config()->set('x-change.commercial.invoicing_authority.document_type', 'non_vat_invoice');

    $status = app(CommercialPricingScheduleInspector::class)->inspect();

    expect($status['customer_charging_ready'])->toBeFalse()
        ->and(data_get($status, 'invoicing_authority.document_matches_registration'))->toBeFalse();
});

it('blocks a positive production charge while tax treatment is unresolved', function (): void {
    config()->set('x-change.deployment.runtime_tier', 'production');

    expect(fn () => app(CommercialCustomerChargingGuard::class)->ensureAuthorized(1_500))
        ->toThrow(
            PayCodeIssuanceFailed::class,
            'Customer charging is not authorized until the approved pricing schedule has resolved tax and invoicing authority.',
        );
});

it('allows an explicitly authorized beta charge while preserving the principal boundary', function (): void {
    config()->set('x-change.deployment.runtime_tier', 'production');
    config()->set('x-change.commercial.beta_customer_charging_exception', [
        'enabled' => true,
        'authorization_reference' => 'production-beta:2026-10-02:lester-hurtado',
    ]);

    app(CommercialCustomerChargingGuard::class)->ensureAuthorized(1_500);

    expect(true)->toBeTrue();
});

it('rejects a beta charging exception without a durable authorization reference', function (): void {
    config()->set('x-change.deployment.runtime_tier', 'production');
    config()->set('x-change.commercial.beta_customer_charging_exception', [
        'enabled' => true,
        'authorization_reference' => null,
    ]);

    expect(fn () => app(CommercialCustomerChargingGuard::class)->ensureAuthorized(1_500))
        ->toThrow(PayCodeIssuanceFailed::class);
});

it('rejects a beta charging exception when the principal boundary is invalid', function (): void {
    config()->set('x-change.deployment.runtime_tier', 'production');
    config()->set('x-change.commercial.beta_customer_charging_exception', [
        'enabled' => true,
        'authorization_reference' => 'production-beta:2026-10-02:lester-hurtado',
    ]);
    config()->set('x-change.commercial.pricing_schedule.principal_treatment', 'revenue');

    expect(fn () => app(CommercialCustomerChargingGuard::class)->ensureAuthorized(1_500))
        ->toThrow(PayCodeIssuanceFailed::class);
});

it('allows zero charges and non-production characterization', function (): void {
    $guard = app(CommercialCustomerChargingGuard::class);

    config()->set('x-change.deployment.runtime_tier', 'production');
    $guard->ensureAuthorized(0);

    config()->set('x-change.deployment.runtime_tier', 'local');
    $guard->ensureAuthorized(1_500);

    expect(true)->toBeTrue();
});

function configureCommercialInvoicingAuthority(): void
{
    config()->set('x-change.commercial.invoicing_authority', [
        'status' => 'approved',
        'jurisdiction' => 'PH',
        'issuer_legal_name' => 'Example Institution Inc.',
        'issuer_tin' => '000-000-000-000',
        'issuer_registered_address' => 'Example registered address',
        'tax_registration' => 'vat',
        'document_type' => 'vat_invoice',
        'authority_reference' => 'bir-authority:test-only',
        'tax_profile_reference' => 'tax-profile:test-only',
        'effective_at' => '2026-09-27T00:00:00+08:00',
        'invoice_every_charge' => true,
    ]);
}
