<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Claim;

use Illuminate\Contracts\Container\Container;
use LBHurtado\XChange\Contracts\VoucherClaimOutcomeHandlerContract;
use LBHurtado\XChange\Exceptions\VoucherClaimOutcomeConflict;

final readonly class VoucherClaimOutcomeRegistry
{
    /**
     * @param  array<string, class-string<VoucherClaimOutcomeHandlerContract>>|null  $handlers
     */
    public function __construct(
        private Container $container,
        private ?array $handlers = null,
    ) {}

    public function handler(string $outcome): VoucherClaimOutcomeHandlerContract
    {
        $handlerClass = $this->handlerMap()[$outcome] ?? null;

        if (! is_string($handlerClass)) {
            throw new VoucherClaimOutcomeConflict(
                "Voucher claim outcome [{$outcome}] is not registered.",
            );
        }

        $handler = $this->container->make($handlerClass);

        if (
            ! $handler instanceof VoucherClaimOutcomeHandlerContract
            || $handler->key() !== $outcome
        ) {
            throw new VoucherClaimOutcomeConflict(
                "Voucher claim outcome handler [{$handlerClass}] is invalid.",
            );
        }

        return $handler;
    }

    /**
     * @return array<string, class-string<VoucherClaimOutcomeHandlerContract>>
     */
    private function handlerMap(): array
    {
        return $this->handlers ?? [
            'provider_disbursement' => ProviderDisbursementClaimOutcomeHandler::class,
            'account_funding' => AccountFundingClaimOutcomeHandler::class,
        ];
    }
}
