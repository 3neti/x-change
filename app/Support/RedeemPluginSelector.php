<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;
use LBHurtado\Voucher\Enums\VoucherInputField;
use Illuminate\Support\Facades\Config;
use LBHurtado\Voucher\Models\Voucher;
use Illuminate\Support\Collection;

class RedeemPluginSelector
{
    /**
     * Determine the enabled plugins required by a voucher's inputs.
     *
     * @param  \LBHurtado\Voucher\Models\Voucher  $voucher
     * @return \Illuminate\Support\Collection
     */
    public static function fromVoucher(Voucher $voucher): Collection
    {
        // 🧩 Normalize voucher input fields to string values
        $voucherFieldKeys = collect($voucher->instructions->inputs->fields)
            ->map(fn (VoucherInputField $field) => $field->value)
            ->values()
            ->all();

        // 🧠 Determine enabled plugins with intersecting fields
        $plugins = collect(Config::get('x-change.redeem.plugins', []))
            ->filter(function ($pluginConfig) use ($voucherFieldKeys) {
                $pluginFieldKeys = collect($pluginConfig['fields'] ?? [])
                    ->map(fn ($field) => $field instanceof VoucherInputField ? $field->value : $field)
                    ->all();

                return ($pluginConfig['enabled'] ?? false)
                    && count(array_intersect($pluginFieldKeys, $voucherFieldKeys)) > 0;
            })
            ->keys()
            ->values();

        Log::info('[RedeemPluginSelector] Determined eligible plugins for voucher', [
            'voucher' => $voucher->code,
            'required_fields' => $voucherFieldKeys,
            'resolved_plugins' => $plugins->all(),
        ]);

        return $plugins;
    }
}
