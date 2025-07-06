<?php

namespace App\Pipelines\RedeemedVoucher;

use Illuminate\Support\Facades\Log;
use Closure;

class PersistInputs
{
    public function handle($voucher, Closure $next)
    {
        $redeemer = $voucher->redeemers->first();

        if (! $redeemer) {
            Log::debug('[PersistInputs] No redeemer found; skipping', [
                'voucher' => $voucher->code,
            ]);
            return $next($voucher);
        }

        $metadata = $redeemer->metadata['redemption'] ?? [];
        $plugins  = config('x-change.redeem.plugins', []);
        $dirty    = false;

        Log::debug('[PersistInputs] Loaded redemption metadata', [
            'voucher'  => $voucher->code,
            'metadata' => $metadata,
        ]);

        foreach ($plugins as $plugin => $config) {
            $sessionKey = $config['session_key'] ?? $plugin;
            $fields     = $config['fields'] ?? [];

            if (count($fields) === 1) {
                // ✍️ Single field value (e.g., signature)
                $field = $fields[0]->value;
                $value = $metadata[$sessionKey] ?? null;

                if (! is_null($value)) {
                    $voucher->{$field} = $value;
                    $dirty = true;
                    Log::info('[PersistInputs] Assigned single input', [
                        'field' => $field,
                        'value' => $value,
                    ]);
                }
            } elseif (count($fields) > 1) {
                // 📥 Multiple fields inside a nested array (e.g., inputs)
                $values = $metadata[$sessionKey] ?? [];

                foreach ($fields as $enum) {
                    $field = $enum->value;

                    if (array_key_exists($field, $values)) {
                        $value = $values[$field];
                        $voucher->{$field} = $value;
                        $dirty = true;

                        Log::info('[PersistInputs] Assigned nested input', [
                            'field' => $field,
                            'value' => $value,
                        ]);
                    }
                }
            }
        }

        if ($dirty) {
            $voucher->save();
            Log::debug('[PersistInputs] Saved voucher with persisted inputs', [
                'voucher' => $voucher->code,
            ]);
        } else {
            Log::debug('[PersistInputs] No changes to persist', [
                'voucher' => $voucher->code,
            ]);
        }

        return $next($voucher);
    }
}
