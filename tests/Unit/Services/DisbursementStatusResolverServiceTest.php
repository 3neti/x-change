<?php

declare(strict_types=1);

use LBHurtado\EmiCore\Enums\PayoutStatus;
use LBHurtado\XChange\Services\DefaultDisbursementStatusResolverService;

it('maps payout status success to succeeded', function () {
    $service = new DefaultDisbursementStatusResolverService;

    $response = new class
    {
        public $status;

        public function __construct()
        {
            $this->status = PayoutStatus::COMPLETED;
        }
    };

    expect($service->resolveFromGatewayResponse($response))->toBe('succeeded');
});

it('maps payout status pending to pending', function () {
    $service = new DefaultDisbursementStatusResolverService;

    $response = new class
    {
        public $status = 'pending';
    };

    expect($service->resolveFromGatewayResponse($response))->toBe('pending');
});

it('maps timeout exception to unknown', function () {
    $service = new DefaultDisbursementStatusResolverService;

    $e = new RuntimeException('Gateway timeout while processing payout.');

    expect($service->resolveFromGatewayException($e))->toBe('unknown');
});

it('maps normal exception to failed', function () {
    $service = new DefaultDisbursementStatusResolverService;

    $e = new RuntimeException('Bank account is invalid.');

    expect($service->resolveFromGatewayException($e))->toBe('failed');
});
