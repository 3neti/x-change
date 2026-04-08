<?php

declare(strict_types=1);

use LBHurtado\Voucher\Data\RedemptionContext;
use LBHurtado\XChange\Services\DefaultRedemptionProcessorService;

it('prepares redemption metadata shape correctly through processor', function () {
    $service = new class extends DefaultRedemptionProcessorService
    {
        /**
         * @return array<string, mixed>
         */
        public function exposeMetadata(RedemptionContext $context): array
        {
            return $this->prepareMetadata($context);
        }
    };

    $context = new RedemptionContext(
        mobile: '09171234567',
        secret: '1234',
        vendorAlias: null,
        inputs: [
            'name' => 'Juan Dela Cruz',
            'otp' => '123456',
        ],
        bankAccount: [
            'bank_code' => 'GXCHPHM2XXX',
            'account_number' => '09171234567',
        ],
    );

    $meta = $service->exposeMetadata($context);

    expect($meta)->toBe([
        'inputs' => [
            'name' => 'Juan Dela Cruz',
            'otp' => '123456',
        ],
        'bank_account' => 'GXCHPHM2XXX:09171234567',
    ]);
});
