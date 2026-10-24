<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Enums;

enum ApplicationJourneyKind: string
{
    case IssueSingle = 'issue_single';
    case IssueBatch = 'issue_batch';
    case FundAndIssue = 'fund_and_issue';
    case Claim = 'claim';
    case Collect = 'collect';
    case CaptureAndQualify = 'capture_and_qualify';
    case Enroll = 'enroll';
    case Inspect = 'inspect';
    case ResolveException = 'resolve_exception';

    public function instructionContract(): ApplicationInstructionContract
    {
        return match ($this) {
            self::IssueSingle, self::IssueBatch, self::FundAndIssue, self::Claim => ApplicationInstructionContract::Voucher,
            self::Collect => ApplicationInstructionContract::StoredValueCollection,
            self::CaptureAndQualify, self::Enroll => ApplicationInstructionContract::Record,
            self::Inspect, self::ResolveException => ApplicationInstructionContract::None,
        };
    }
}
