<?php

namespace LBHurtado\Voucher\Enums;

enum VoucherInputField: string
{
    case EMAIL = 'email';
    case MOBILE = 'mobile';
    case REFERENCE_CODE = 'reference_code';
    case SIGNATURE = 'signature';
    case KYC = 'kyc';
}
