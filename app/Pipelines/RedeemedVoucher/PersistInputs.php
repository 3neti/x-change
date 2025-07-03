<?php

namespace App\Pipelines\RedeemedVoucher;

use LBHurtado\ModelInput\Enums\InputType;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Arr;
use Closure;

/**
 * Pipeline step: persist any “input” values collected
 * during voucher redemption (e.g. mobile, signature, bank_account).
 *
 * It looks for them under the redeemer’s metadata at
 *     redemption.{inputName}
 * and—if present—mass-assigns them on the Voucher via its
 * magic setter (so they end up in your inputs table),
 * then saves once at the end.
 */
class PersistInputs
{
    /**
     * @param  \FrittenKeeZ\Vouchers\Models\Voucher  $voucher
     * @param  Closure                                $next
     * @return mixed
     */
    public function handle($voucher, Closure $next)
    {
        // 1) Grab the redeemer metadata
        $redeemer = $voucher->redeemers->first();
        if (! $redeemer) {
            Log::debug('[PersistInputs] No redeemer found; skipping', [
                'voucher' => $voucher->code,
            ]);
            return $next($voucher);
        }

        $metadata = $redeemer->metadata['redemption'] ?? [];
        Log::debug('[PersistInputs] Redemption metadata', [
            'voucher'  => $voucher->code,
            'metadata' => $metadata,
        ]);

        $dirty = false;

        // 2) For each InputType, see if there’s a value in metadata
        foreach (InputType::cases() as $inputType) {
            $field = $inputType->value; // e.g. 'mobile', 'signature', 'bank_account'
            if (Arr::has($metadata, $field)) {
                $value = Arr::get($metadata, $field);
                Log::info('[PersistInputs] Found input to persist', [
                    'voucher' => $voucher->code,
                    'field'   => $field,
                    'value'   => $value,
                ]);

                // magic __set will create an Input record if needed
                $voucher->{$field} = $value;
                $dirty = true;
            }
        }

        // 3) If we actually assigned anything, persist once
        if ($dirty) {
            $voucher->save();
            Log::debug('[PersistInputs] Saved voucher with new inputs', [
                'voucher' => $voucher->code,
            ]);
        } else {
            Log::debug('[PersistInputs] No redemption inputs to persist', [
                'voucher' => $voucher->code,
            ]);
        }

        return $next($voucher);
    }
}
