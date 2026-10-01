<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Enums;

enum QrArtifactKind: string
{
    case ClaimEntry = 'claim_entry';
    case PayCode = 'pay_code';
    case CampaignEndpoint = 'campaign_endpoint';
    case QrPhPayment = 'qrph_payment';
}
