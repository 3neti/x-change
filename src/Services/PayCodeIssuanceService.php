<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Auth;
use LBHurtado\Voucher\Actions\GenerateVouchers;
use LBHurtado\Voucher\Data\VoucherInstructionsData;
use LBHurtado\XChange\Contracts\PayCodeIssuanceContract;
use LBHurtado\XChange\Exceptions\PayCodeIssuanceFailed;

class PayCodeIssuanceService implements PayCodeIssuanceContract
{
    public function issue(mixed $issuer, array $input): array
    {
        if (! $issuer instanceof Authenticatable) {
            throw new PayCodeIssuanceFailed('Pay Code issuance requires a valid authenticatable issuer.');
        }

        $instructions = VoucherInstructionsData::createFromAttribs($input);

        /** @var \Illuminate\Contracts\Auth\Authenticatable|null $previousUser */
        $previousUser = Auth::user();

        try {
            Auth::setUser($issuer);

            $issued = GenerateVouchers::run($instructions)->first();

            if (! $issued) {
                throw new PayCodeIssuanceFailed('Pay Code issuance did not return a voucher.');
            }

            $code = (string) $issued->code;
            $redeemPath = $this->redeemPath($code);

            return [
                'voucher_id' => $issued->id,
                'code' => $code,
                'amount' => data_get($instructions->toArray(), 'cash.amount'),
                'currency' => data_get($instructions->toArray(), 'cash.currency'),
                'links' => [
                    'redeem' => $this->redeemUrl($redeemPath),
                    'redeem_path' => $redeemPath,
                ],
                'metadata' => $issued->metadata ?? null,
            ];
        } catch (\Throwable $e) {
            dump($e::class);
            dump($e->getMessage());
            dump($e->getTraceAsString());
            throw $e;
        } finally {
            if ($previousUser instanceof Authenticatable) {
                Auth::setUser($previousUser);
            } else {
                Auth::forgetGuards();
            }
        }
    }

    protected function redeemPath(string $code): string
    {
        $path = trim((string) config('x-change.routes.paths.redeem', 'disburse'), '/');

        return '/'.$path.'?code='.urlencode($code);
    }

    protected function redeemUrl(string $path): string
    {
        $base = rtrim((string) config('app.url', ''), '/');

        return $base === '' ? $path : $base.$path;
    }
}
