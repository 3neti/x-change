<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Support\Claim;

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Route;
use LBHurtado\Voucher\Models\Voucher;

final class CompiledClaimResultRedirector
{
    public function redirect(Voucher $voucher, mixed $result): RedirectResponse
    {
        $status = (string) data_get($result, 'status', '');

        if (in_array($status, ['success', 'completed'], true)) {
            return redirect()->route('x-change.claim.success', [
                'code' => $voucher->code,
            ]);
        }

        if ($status === 'pending') {
            if (Route::has('x-change.claim.approval')) {
                return redirect()->route('x-change.claim.approval', [
                    'code' => $voucher->code,
                ]);
            }

            return redirect("/x/claim/{$voucher->code}/approval");
        }

        throw new \RuntimeException("Unsupported compiled claim result status [{$status}].");
    }
}
