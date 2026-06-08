<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Support\Claim;

final class CompiledClaimSuccessPayload
{
    public function pull(): ?array
    {
        return app(CompiledClaimResultSession::class)->pull();
    }
}
