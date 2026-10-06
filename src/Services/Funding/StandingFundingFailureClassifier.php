<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Funding;

use Illuminate\Database\QueryException;
use LBHurtado\XChange\Enums\StandingFundingFailureClassification;
use Throwable;

final class StandingFundingFailureClassifier
{
    public function classify(Throwable $failure, bool $providerCallStarted = false): StandingFundingFailureClassification
    {
        $message = strtolower($failure->getMessage());
        $sqlState = $failure instanceof QueryException ? (string) ($failure->errorInfo[0] ?? '') : '';

        if ($sqlState === '53200' || str_contains($message, 'out of memory')) {
            return StandingFundingFailureClassification::DatabaseResourceExhausted;
        }

        if ($failure instanceof QueryException || str_contains($message, 'connection refused')) {
            return StandingFundingFailureClassification::DatabaseUnavailable;
        }

        if (str_contains($message, '429') || str_contains($message, 'rate limit')) {
            return StandingFundingFailureClassification::ProviderThrottled;
        }

        if ($providerCallStarted && (str_contains($message, 'timeout') || str_contains($message, 'timed out'))) {
            return StandingFundingFailureClassification::AmbiguousAfterProviderCall;
        }

        if (str_contains($message, 'configuration') || str_contains($message, 'not configured')) {
            return StandingFundingFailureClassification::ConfigurationPermanent;
        }

        if (str_contains($message, 'timeout') || str_contains($message, 'temporar')) {
            return StandingFundingFailureClassification::ProviderTransient;
        }

        return StandingFundingFailureClassification::Unexpected;
    }
}
