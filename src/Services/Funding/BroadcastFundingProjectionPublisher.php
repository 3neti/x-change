<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Funding;

use Illuminate\Support\Facades\DB;
use LBHurtado\XChange\Contracts\FundingProjectionPublisherContract;
use LBHurtado\XChange\Events\FundingProjectionChanged;

final class BroadcastFundingProjectionPublisher implements FundingProjectionPublisherContract
{
    public function publish(
        string $ownerType,
        string $ownerId,
        string $reference,
        string $occurredAt,
    ): void {
        DB::afterCommit(static fn () => FundingProjectionChanged::dispatch(
            $ownerType,
            $ownerId,
            $reference,
            $occurredAt,
        ));
    }
}
