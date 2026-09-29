<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Enums;

enum PayCodeIssuanceFundingOrderStatus: string
{
    case AwaitingPayment = 'awaiting_payment';
    case PayerAcknowledged = 'payer_acknowledged';
    case Verifying = 'verifying';
    case Funded = 'funded';
    case Issuing = 'issuing';
    case Issued = 'issued';
    case Underfunded = 'underfunded';
    case PaymentAmbiguous = 'payment_ambiguous';
    case Cancelled = 'cancelled';
    case Expired = 'expired';
    case IssuanceAttention = 'issuance_attention';
}
