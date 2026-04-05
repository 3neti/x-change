<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\PayCode;

use LBHurtado\Voucher\Data\VoucherInstructionsData;
use LBHurtado\XChange\Contracts\PricingServiceContract;

class EstimatePayCodeCost
{
    public function __construct(
        protected PricingServiceContract $pricing,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     * @return array{
     *     currency:string,
     *     base_fee:float,
     *     components:array<string,float>,
     *     total:float
     * }
     */
    public function handle(array $input): array
    {
        $instructions = VoucherInstructionsData::from($input);

        $estimate = $this->pricing->estimate($instructions);

        return [
            'currency' => (string) $estimate['currency'],
            'base_fee' => (float) $estimate['base_fee'],
            'components' => (array) $estimate['components'],
            'total' => (float) $estimate['total'],
        ];
    }
}
