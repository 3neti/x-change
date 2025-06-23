<?php

namespace LBHurtado\ModelInput\Enums;

use Illuminate\Support\Facades\Config;

enum Input: string
{
    case MOBILE = 'mobile';
    case SIGNATURE = 'signature';

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
