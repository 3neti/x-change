<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Claim;

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

        session()->flash('compiled_claim_completion_payload', $payload);

        return $payload;
    }
}
