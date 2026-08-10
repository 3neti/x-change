<?php

declare(strict_types=1);

use LBHurtado\XChange\Contracts\VoucherLifecycleServiceContract;
use LBHurtado\XChange\Data\Cockpit\CockpitReadModelQueryData;
use LBHurtado\XChange\Services\Cockpit\NullCockpitReadModelProvider;
use LBHurtado\XChange\Services\Cockpit\VoucherLifecycleCockpitReadModelProvider;

it('adds read only distribution links to voucher detail read models', function (): void {
    $service = new class implements VoucherLifecycleServiceContract
    {
        public function list(array $filters = []): array
        {
            return [];
        }

        public function show(string $voucher): mixed
        {
            return $this->showByCode($voucher);
        }

        public function showByCode(string $code): mixed
        {
            return [
                'code' => $code,
                'status' => 'issued',
                'display_status' => 'ready',
                'amount' => 500,
                'currency' => 'PHP',
                'claimed' => false,
                'fully_claimed' => false,
            ];
        }

        public function status(string $voucher): mixed
        {
            return [];
        }

        public function cancel(string $voucher, array $payload = []): mixed
        {
            return [];
        }

        public function expire(string $voucher, array $payload = []): mixed
        {
            return [];
        }
    };

    $provider = new VoucherLifecycleCockpitReadModelProvider(
        vouchers: $service,
        fallback: new NullCockpitReadModelProvider,
    );

    $bundle = $provider->forVoucher(new CockpitReadModelQueryData(code: 'pc-wave-54b'));
    $links = $bundle->voucher->distribution_links;

    expect($links)
        ->toMatchArray([
            'schema' => 'x-change.cockpit.distribution-links.v1',
            'status' => 'available',
            'available' => true,
            'read_only' => true,
            'redeem_url' => 'http://localhost/x/claim/PC-WAVE-54B',
            'redeem_path' => '/x/claim/PC-WAVE-54B',
            'source' => 'x-change.claim.show',
            'delivery_enabled' => false,
        ])
        ->and($links['redactions'])
        ->toMatchArray([
            'payloads' => 'distribution-links-only',
            'secret_claim_material_exposed' => false,
            'provider_payloads_exposed' => false,
            'wallet_data_exposed' => false,
            'delivery_payloads_exposed' => false,
        ]);
});
