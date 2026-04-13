<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services;

use LBHurtado\Instruction\Actions\EvaluateInstructionCharges;
use LBHurtado\Instruction\Support\ArrayChargeableCustomer;
use LBHurtado\Instruction\Support\ArrayInstructionSource;
use LBHurtado\Voucher\Data\VoucherInstructionsData;
use LBHurtado\XChange\Contracts\PricingServiceContract;

class InstructionBackedPricingService implements PricingServiceContract
{
    public function __construct(
        protected EvaluateInstructionCharges $evaluator,
    ) {}

    public function estimate(VoucherInstructionsData $instructions): array
    {
        $metadata = (array) data_get($instructions, 'metadata', []);

        $customer = new ArrayChargeableCustomer([
            'id' => data_get($metadata, 'issuer_id'),
            'name' => data_get($metadata, 'issuer_name'),
            'email' => data_get($metadata, 'issuer_email'),
        ]);

        $source = new ArrayInstructionSource($this->normalizeInstructionSource($instructions));

        $result = $this->evaluator->handle($customer, $source);

        return $this->normalizeEstimateResult($result);
    }

    /**
     * @return array<string, mixed>
     */
    protected function normalizeInstructionSource(VoucherInstructionsData $instructions): array
    {
        $payload = $instructions->toArray();

        // instruction package expects count, and your factory already maps quantity -> count
        $payload['count'] ??= 1;

        // Ensure feedback/rider/inputs always exist
        $payload['inputs'] ??= ['fields' => []];
        $payload['feedback'] ??= [
            'email' => null,
            'mobile' => null,
            'webhook' => null,
        ];
        $payload['rider'] ??= [
            'message' => null,
            'url' => null,
            'redirect_timeout' => null,
            'splash' => null,
            'splash_timeout' => null,
            'og_source' => null,
        ];

        return $payload;
    }

    /**
     * @return array{
     *     currency:string,
     *     base_fee:float,
     *     components:array<string,float>,
     *     total:float
     * }
     */
    protected function normalizeEstimateResult(mixed $result): array
    {
        $components = [
            'cash' => 0.0,
            'kyc' => 0.0,
            'otp' => 0.0,
            'selfie' => 0.0,
            'signature' => 0.0,
            'location' => 0.0,
            'webhook' => 0.0,
            'email_feedback' => 0.0,
            'sms_feedback' => 0.0,
        ];

        $charges = collect(data_get($result, 'charges', []));

        foreach ($charges as $charge) {
            $index = (string) data_get($charge, 'index', '');
            $price = (float) data_get($charge, 'price', 0);

            if ($index === '') {
                continue;
            }

            $component = $this->mapInstructionIndexToEstimateComponent($index);

            if ($component === null) {
                continue;
            }

            $components[$component] += $price;
        }

        return [
            'currency' => (string) (data_get($result, 'currency') ?? config('x-change.pricing.currency', 'PHP')),
            'base_fee' => 0.0,
            'components' => $components,
            'total' => (float) (data_get($result, 'total_amount') ?? $charges->sum('price')),
        ];
    }

    protected function mapInstructionIndexToEstimateComponent(string $index): ?string
    {
        return match ($index) {
            'cash.amount' => 'cash',

//            'inputs.fields.government_id',
//            'inputs.fields.id_card' => 'kyc',

            'inputs.fields.selfie' => 'selfie',
            'inputs.fields.signature' => 'signature',
            'inputs.fields.kyc' => 'kyc',
            'inputs.fields.otp' => 'otp',
            'inputs.fields.location' => 'location',


            'cash.validation.location',
            'cash.validation.radius' => 'location',

            'cash.validation.payable' => 'otp',

            'feedback.webhook' => 'webhook',
            'feedback.email' => 'email_feedback',
            'feedback.mobile' => 'sms_feedback',

            default => null,
        };
    }
}
