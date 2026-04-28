<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services;

use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Contracts\PayCodePresentationResolverContract;
use LBHurtado\XChange\Contracts\VoucherFlowCapabilityResolverContract;

class DefaultPayCodePresentationResolver implements PayCodePresentationResolverContract
{
    public function __construct(
        protected VoucherFlowCapabilityResolverContract $flowResolver,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function resolve(Voucher $voucher): array
    {
        $capabilities = $this->flowResolver->resolve($voucher);

        $routeKey = $capabilities->pay_code_route;

        return [
            'voucher_code' => (string) $voucher->code,
            'flow_type' => $capabilities->type->value,
            'label' => $capabilities->label,
            'direction' => $capabilities->direction,
            'route_key' => $routeKey,
            'url' => url('/'.$routeKey.'/'.$voucher->code),
            'qr_type' => $capabilities->qr_type,
            'capabilities' => [
                'can_disburse' => $capabilities->can_disburse,
                'can_collect' => $capabilities->can_collect,
                'can_settle' => $capabilities->can_settle,
                'supports_open_slices' => $capabilities->supports_open_slices,
                'supports_delegated_spend' => $capabilities->supports_delegated_spend,
                'requires_envelope' => $capabilities->requires_envelope,
            ],
        ];
    }
}
