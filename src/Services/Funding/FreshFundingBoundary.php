<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Funding;

use Carbon\CarbonImmutable;
use LBHurtado\XChange\Exceptions\FundingDestinationUnavailable;
use LBHurtado\XChange\Models\FundingIntent;
use LBHurtado\XChange\Models\PaymentAttempt;
use LBHurtado\XChange\Models\ProviderAccountLink;
use Throwable;

final class FreshFundingBoundary
{
    public function assertEligible(FundingIntent $intent): void
    {
        $this->assertSourceEligible($intent);
    }

    public function assertPaymentAttemptEligible(PaymentAttempt $attempt): void
    {
        $this->assertSourceEligible($attempt);
    }

    private function assertSourceEligible(FundingIntent|PaymentAttempt $intent): void
    {
        $snapshot = $intent->destination_snapshot_ciphertext;
        if ($intent->provider_code !== 'netbank' || data_get($snapshot, 'mode') !== 'dedicated') {
            return;
        }

        $linkId = data_get($snapshot, 'metadata.assigned_bank_account_link_id');
        $link = is_int($linkId) ? ProviderAccountLink::query()->find($linkId) : null;
        if ($link === null || data_get($link->metadata, 'funding_assignment.scope') !== 'fresh_intents_only') {
            return;
        }

        try {
            $since = data_get($link->metadata, 'funding_assignment.fresh_since');
            if (! is_string($since) || trim($since) === '' || $intent->created_at === null
                || $intent->created_at->lessThanOrEqualTo(CarbonImmutable::parse($since))) {
                throw new \InvalidArgumentException;
            }
        } catch (Throwable) {
            throw new FundingDestinationUnavailable('Only new funding requests are available for this account.');
        }
    }
}
