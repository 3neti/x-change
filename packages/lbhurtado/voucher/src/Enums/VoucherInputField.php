<?php

namespace LBHurtado\Voucher\Enums;

enum VoucherInputField: string
{
    case EMAIL = 'email';
    case MOBILE = 'mobile';
    case REFERENCE_CODE = 'reference_code';
    case SIGNATURE = 'signature';
    case KYC = 'kyc';
    case NAME = 'name';
    case ADDRESS = 'address';
    case BIRTH_DATE = 'birth_date';
    case GROSS_MONTHLY_INCOME = 'gross_monthly_income';
}
