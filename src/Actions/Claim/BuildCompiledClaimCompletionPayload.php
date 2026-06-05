<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Claim;

/**
 * Builds the legacy compiled-form handoff payload from prepared session state.
 *
 * This does not execute voucher redemption.
 */
final class BuildCompiledClaimCompletionPayload
{
    public function __construct(
        private readonly ReadPreparedCompiledClaim $readPreparedClaim,
        private readonly InjectPreparedCompiledClaimInputs $injectInputs,
    ) {}

    public function handle(bool $forget = false): ?array
    {
        $prepared = $this->readPreparedClaim->handle(forget: $forget);

        if (! $prepared) {
            return null;
        }

        return $this->injectInputs->handle($prepared, [
            'source' => 'compiled_form',
        ]);
    }
}
