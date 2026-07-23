<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Enums;

enum FundingIntentStatus: string
{
    case PendingInstructions = 'pending_instructions';
    case AwaitingFunds = 'awaiting_funds';
    case EvidenceReceived = 'evidence_received';
    case Verifying = 'verifying';
    case Settled = 'settled';
    case Suspense = 'suspense';
    case Expired = 'expired';
    case Cancelled = 'cancelled';
    case Reversed = 'reversed';

    /**
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::PendingInstructions => [self::AwaitingFunds, self::Suspense, self::Expired, self::Cancelled],
            self::AwaitingFunds => [self::EvidenceReceived, self::Suspense, self::Expired, self::Cancelled],
            self::EvidenceReceived => [self::Verifying, self::Suspense, self::Expired],
            self::Verifying => [self::AwaitingFunds, self::Settled, self::Suspense, self::Expired],
            self::Settled => [self::Reversed],
            self::Suspense => [self::AwaitingFunds, self::EvidenceReceived, self::Verifying, self::Expired, self::Cancelled],
            self::Expired, self::Cancelled => [self::Suspense],
            self::Reversed => [],
        };
    }

    public function canTransitionTo(self $status): bool
    {
        return in_array($status, $this->allowedTransitions(), true);
    }
}
