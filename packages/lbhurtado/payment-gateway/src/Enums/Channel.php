<?php

namespace LBHurtado\PaymentGateway\Enums;

use Illuminate\Support\Facades\Config;

enum Channel: string
{
    case MOBILE = 'mobile';
    case WEBHOOK = 'webhook';

    public function rules(): array
    {
        // Dynamically retrieve rules from the configuration file
        $rules = Config::get('model-channel.rules.' . $this->value);

        // Throw an exception if no rule is defined for the channel
        if (is_null($rules)) {
            throw new \RuntimeException("Validation rules are not defined for the [{$this->value}] channel.");
        }

        return $rules;
    }

//    public function rules(): array
//    {
//        return match ($this) {
//            self::MOBILE => ['required', (new Phone)->country('PH')->type('mobile')],
//            self::WEBHOOK => ['required', 'url'],
//        };
//    }
}
