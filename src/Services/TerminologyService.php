<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services;

use Illuminate\Contracts\Translation\Translator;
use LBHurtado\XChange\Contracts\TerminologyServiceContract;

class TerminologyService implements TerminologyServiceContract
{
    public function __construct(
        protected Translator $translator,
    ) {}

    public function term(string $key, ?string $default = null): string
    {
        $configured = config("x-change.terminology.{$key}");

        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        if (is_string($default) && $default !== '') {
            return $default;
        }

        return $key;
    }

    public function message(string $key, array $replace = [], ?string $default = null): string
    {
        $replace = array_merge([
            'voucher' => $this->term('voucher', 'voucher'),
            'voucher_code' => $this->term('voucher_code', 'voucher code'),
            'redeem' => $this->term('redeem', 'redeem'),
            'withdraw' => $this->term('withdraw', 'withdraw'),
            'wallet' => $this->term('wallet', 'wallet'),
            'account' => $this->term('account', 'account'),
        ], $replace);

        $translationKey = "x-change.messages.{$key}";
        $translated = $this->translator->get($translationKey, $replace);

        if (is_string($translated) && $translated !== $translationKey) {
            return $translated;
        }

        if (is_string($default) && $default !== '') {
            return $this->replace($default, $replace);
        }

        return $translationKey;
    }

    /**
     * @param  array<string, string|int|float|bool|null>  $replace
     */
    protected function replace(string $text, array $replace): string
    {
        foreach ($replace as $key => $value) {
            $text = str_replace(':'.$key, (string) $value, $text);
        }

        return $text;
    }
}
