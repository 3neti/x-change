<?php

namespace LBHurtado\Voucher\Enums;

enum CashStatus: string
{
    // Initial States
    case MINTED = 'minted';
    case SUSPENDED = 'suspended';
    case NULLIFIED = 'nullified';
    case EXPIRED = 'expired';
    case DISBURSED = 'disbursed';
    case SENT = 'sent';
    case RECEIVED = 'received';
    case REFUNDED = 'refunded';
    case CANCELLED = 'cancelled';
    case REJECTED = 'rejected';

    // Reversed States
    case REVERSED = 'reversed';
    case REFUNDED_REVERSED = 'refunded_reversed';
    case REVERSED_REFUNDED = 'reversed_refunded';
    case REVERSED_CANCELLED = 'reversed_cancelled';
    case REVERSED_REJECTED = 'reversed_rejected';
    case REVERSED_SENT = 'reversed_sent';
    case REVERSED_RECEIVED = 'reversed_received';
    case REVERSED_DISBURSED = 'reversed_disbursed';

    // Reversed Refunded States
    case REVERSED_REFUNDED_REVERSED = 'reversed_refunded_reversed';
    case REVERSED_REFUNDED_CANCELLED = 'reversed_refunded_cancelled';
    case REVERSED_REFUNDED_REJECTED = 'reversed_refunded_rejected';
    case REVERSED_REFUNDED_SENT = 'reversed_refunded_sent';
    case REVERSED_REFUNDED_RECEIVED = 'reversed_refunded_received';
    case REVERSED_REFUNDED_DISBURSED = 'reversed_refunded_disbursed';

    // Reversed Specific States
    case REVERSED_NULLIFIED = 'reversed_nullified';
    case REVERSED_SUSPENDED = 'reversed_suspended';
    case REVERSED_EXPIRED = 'reversed_expired';
    case REVERSED_DISBURSED_NULLIFIED = 'reversed_disbursed_nullified';
    case REVERSED_DISBURSED_SUSPENDED = 'reversed_disbursed_suspended';
    case REVERSED_DISBURSED_EXPIRED = 'reversed_disbursed_expired';
    case REVERSED_NULLIFIED_SUSPENDED = 'reversed_nullified_suspended';

    /**
     * Check if the current status is a reversed state.
     */
    public function isReversed(): bool
    {
        return str_starts_with($this->value, 'reversed');
    }

    /**
     * Check if the status is related to refund.
     */
    public function isRefunded(): bool
    {
        return str_contains($this->value, 'refunded');
    }

    /**
     * Retrieve all initial statuses (non-reversed).
     */
    public static function initialStatuses(): array
    {
        return array_map(
            fn(self $status) => $status->value,
            array_filter(self::cases(), fn(self $status) => !$status->isReversed())
        );
    }

    /**
     * Retrieve all reversed statuses.
     */
    public static function reversedStatuses(): array
    {
        return array_map(
            fn(self $status) => $status->value,
            array_filter(self::cases(), fn(self $status) => $status->isReversed())
        );
    }
}
