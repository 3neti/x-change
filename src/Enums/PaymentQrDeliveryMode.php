<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Enums;

enum PaymentQrDeliveryMode: string
{
    case SellerDisplay = 'seller_display';
    case PayerPage = 'payer_page';
    case Downloadable = 'downloadable';
    case ApiPayload = 'api_payload';
}
