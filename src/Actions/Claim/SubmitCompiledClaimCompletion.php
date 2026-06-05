<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Claim;

/**
 * Legacy/supporting action for building the compiled-form handoff payload.
 *
 * The active compiled-form redemption path goes through SubmitCompiledFormClaim,
 * which builds the payload, syncs evidence, and delegates to SubmitPayCodeClaim.
 */
final class SubmitCompiledClaimCompletion
{
    public function __construct(
        private readonly BuildCompiledClaimCompletionPayload $buildPayload,
    ) {}

    public function handle(bool $forget = false): ?array
    {
        $payload = $this->buildPayload->handle(forget: $forget);

        if ($payload === null) {
            return null;
        }

        return $payload;
    }
}
