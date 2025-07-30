<?php

namespace LBHurtado\ModelInput\Enums;

use Illuminate\Support\Facades\Config;

enum InputType: string
{
    case MOBILE = 'mobile';
    case SIGNATURE = 'signature';
    case BANK_ACCOUNT = 'bank_account';
    case NAME = 'name';
    case ADDRESS = 'address';
    case BIRTH_DATE = 'birth_date';
    case EMAIL = 'email';
    case GROSS_MONTHLY_INCOME = 'gross_monthly_income';
    case LOCATION = 'location';
    case REFERENCE_CODE = 'reference_code';
    public function rules(): array
    {
        // Dynamically retrieve rules from the configuration file
        $rules = Config::get('model-input.rules.' . $this->value);

        // Throw an exception if no rule is defined for the channel
        if (is_null($rules)) {
            throw new \RuntimeException("Validation rules are not defined for the [{$this->value}] input.");
        }

        return $rules;
    }
}
