<?php

namespace App\Pipelines\RedeemedVoucher;

use Illuminate\Support\Facades\Log;
use Closure;

/**
 * PersistInputs is a pipeline stage that inspects the redemption metadata
 * stored on a voucher redeemer and maps configured plugin input fields
 * to the corresponding attributes on the Voucher model.
 *
 * It supports both scalar (string) and associative array (object-like) formats
 * based on what was captured in the plugin's session.
 *
 * The structure is inferred at runtime, making it flexible even if the metadata
 * comes in an unexpected shape.
 */
class PersistInputs
{
    /**
     * Handle the persistence of dynamic plugin input values to the Voucher model.
     *
     * This method does the following:
     *  - Retrieves the first redeemer of the voucher.
     *  - Loads the redemption metadata stored on the redeemer.
     *  - Iterates over configured plugins and checks if the session_key exists in the metadata.
     *  - Based on the data type:
     *      - If it's a string and only one field is expected, assigns it.
     *      - If it's an array, assigns values per configured field map.
     *  - Tracks any dirty changes and persists them if needed.
     *
     * @param  \LBHurtado\Voucher\Models\Voucher  $voucher
     * @param  \Closure  $next
     * @return mixed
     */
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

            $data = $metadata[$sessionKey] ?? null;

            if (is_null($data)) {
                Log::debug('[PersistInputs] No session data found', [
                    'plugin' => $plugin,
                    'session_key' => $sessionKey,
                ]);
                continue;
            }

            if (is_string($data)) {
                // ✍️ Scalar input (e.g., single string value like a signature)
                if (count($fields) === 1) {
                    $field = $fields[0]->value;
                    $voucher->{$field} = $data;
                    $dirty = true;

                    Log::warning('[PersistInputs] Expected object but got scalar; treating as scalar anyway', [
                        'plugin' => $plugin,
                        'field' => $field,
                        'value' => $data,
                    ]);
                } else {
                    Log::error('[PersistInputs] Mismatch: multiple fields expected but got scalar', [
                        'plugin' => $plugin,
                        'expected_fields' => array_map(fn($e) => $e->value, $fields),
                        'value' => $data,
                    ]);
                }
            } elseif (is_array($data)) {
                // 📥 Object-like input (e.g., associative array of inputs)
                foreach ($fields as $enum) {
                    $field = $enum->value;

                    if (array_key_exists($field, $data)) {
                        $voucher->{$field} = $data[$field];
                        $dirty = true;

                        Log::info('[PersistInputs] Assigned object field', [
                            'plugin' => $plugin,
                            'field'  => $field,
                            'value'  => $data[$field],
                        ]);
                    }
                }
            } else {
                // 🚨 Unexpected format
                Log::error('[PersistInputs] Unknown data type for plugin session data', [
                    'plugin' => $plugin,
                    'data_type' => gettype($data),
                    'value' => $data,
                ]);
            }
        }

        if ($dirty) {
            $voucher->save();
            Log::debug('[PersistInputs] Saved voucher with updated inputs', [
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
