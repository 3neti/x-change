<?php

declare(strict_types=1);

use LBHurtado\XChange\Contracts\ClaimUrlQrRendererContract;
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
                'operational_status' => [
                    'can_claim' => true,
                ],
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

it('does not expose a claim link for a terminal voucher', function (): void {
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
                'status' => 'cancelled',
                'display_status' => 'cancelled',
                'operational_status' => ['can_claim' => false],
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

    $links = $provider
        ->forVoucher(new CockpitReadModelQueryData(code: 'pc-cancelled'))
        ->voucher
        ->distribution_links;

    expect($links)->toMatchArray([
        'status' => 'unavailable',
        'available' => false,
        'reason' => 'pay-code-not-claimable',
    ])->not->toHaveKey('redeem_url')
        ->not->toHaveKey('claim_qr');
});

it('exposes a canonical claim QR rendered from the exact same URL exposed as redeem_url', function (): void {
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
                'operational_status' => [
                    'can_claim' => true,
                ],
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

    $qrRenderer = new class implements ClaimUrlQrRendererContract
    {
        /** @var array<int, string> */
        public array $renderedUrls = [];

        public function render(string $claimUrl): string
        {
            $this->renderedUrls[] = $claimUrl;

            return 'data:image/png;base64,FAKE-QR-PAYLOAD';
        }
    };

    $provider = new VoucherLifecycleCockpitReadModelProvider(
        vouchers: $service,
        fallback: new NullCockpitReadModelProvider,
        qrRenderer: $qrRenderer,
    );

    $links = $provider
        ->forVoucher(new CockpitReadModelQueryData(code: 'pc-wave-55'))
        ->voucher
        ->distribution_links;

    expect($links['redeem_url'])->toBe('http://localhost/x/claim/PC-WAVE-55')
        ->and($links['claim_qr'])->toBe('data:image/png;base64,FAKE-QR-PAYLOAD')
        // The renderer must receive exactly the same canonical URL exposed
        // to the client as redeem_url — nothing constructed separately.
        ->and($qrRenderer->renderedUrls)->toBe([$links['redeem_url']])
        // No delivery, provider, or financial capability is enabled by
        // this read model.
        ->and($links['delivery_enabled'])->toBeFalse()
        ->and($links)->not->toHaveKey('provider')
        ->not->toHaveKey('financial');
});

it('never renders or exposes a claim QR for a non-claimable Pay Code', function (): void {
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
                'status' => 'redeemed',
                'display_status' => 'redeemed',
                'operational_status' => ['can_claim' => false],
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

    $qrRenderer = new class implements ClaimUrlQrRendererContract
    {
        /** @var array<int, string> */
        public array $renderedUrls = [];

        public function render(string $claimUrl): string
        {
            $this->renderedUrls[] = $claimUrl;

            return 'data:image/png;base64,MUST-NOT-BE-CALLED';
        }
    };

    $provider = new VoucherLifecycleCockpitReadModelProvider(
        vouchers: $service,
        fallback: new NullCockpitReadModelProvider,
        qrRenderer: $qrRenderer,
    );

    $links = $provider
        ->forVoucher(new CockpitReadModelQueryData(code: 'pc-redeemed-01'))
        ->voucher
        ->distribution_links;

    expect($links)->not->toHaveKey('redeem_url')
        ->not->toHaveKey('claim_qr');
    expect($qrRenderer->renderedUrls)->toBe([]);
});

it('omits the claim QR without disturbing the rest of the read-only detail page when rendering fails', function (): void {
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
                'operational_status' => ['can_claim' => true],
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

    $qrRenderer = new class implements ClaimUrlQrRendererContract
    {
        public function render(string $claimUrl): string
        {
            throw new RuntimeException('QR rendering is unavailable.');
        }
    };

    $provider = new VoucherLifecycleCockpitReadModelProvider(
        vouchers: $service,
        fallback: new NullCockpitReadModelProvider,
        qrRenderer: $qrRenderer,
    );

    $links = $provider
        ->forVoucher(new CockpitReadModelQueryData(code: 'pc-wave-57'))
        ->voucher
        ->distribution_links;

    expect($links['available'])->toBeTrue()
        ->and($links['redeem_url'])->toBe('http://localhost/x/claim/PC-WAVE-57')
        ->and($links)->not->toHaveKey('claim_qr');
});
