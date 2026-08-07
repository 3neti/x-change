<?php

declare(strict_types=1);

namespace LBHurtado\XChange\ClaimWalkthrough;

use Carbon\CarbonInterval;
use FrittenKeeZ\Vouchers\Facades\Vouchers;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use LBHurtado\Voucher\Data\VoucherInstructionsData;
use LBHurtado\XChange\Exceptions\PayCodeIssuanceFailed;
use LBHurtado\XChange\Services\VoucherIssuancePayloadNormalizer;

final class ClaimPreviewVoucherIssuer
{
    /**
     * Create the short-lived routing fixture without entering the normal
     * VouchersGenerated pipeline. Preview vouchers must never mint Cash,
     * debit an Account, reserve Treasury value, or dispatch delivery work.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function issue(Authenticatable $issuer, array $payload): array
    {
        $payload = app(VoucherIssuancePayloadNormalizer::class)->normalize($payload);
        $instructions = VoucherInstructionsData::createFromAttribs($payload);
        $instructionPayload = $instructions->toCleanArray();
        $customMetadata = data_get($payload, 'metadata.custom');

        if (is_array($customMetadata) && $customMetadata !== []) {
            data_set($instructionPayload, 'metadata.custom', $customMetadata);
        }

        /** @var Authenticatable|null $previousUser */
        $previousUser = Auth::user();

        try {
            Auth::setUser($issuer);

            return DB::transaction(function () use ($issuer, $instructions, $instructionPayload): array {
                $builder = Vouchers::withPrefix($instructions->prefix ?? 'PV')
                    ->withMask($instructions->mask ?? '****')
                    ->withMetadata(['instructions' => $instructionPayload])
                    ->withOwner($issuer);

                if ($instructions->starts_at !== null) {
                    $builder->withStartTime($instructions->starts_at);
                }

                if ($instructions->expires_at !== null) {
                    $builder->withExpireTime($instructions->expires_at);
                } elseif ($instructions->ttl !== null) {
                    $builder->withExpireTimeIn($instructions->ttl);
                } else {
                    $builder->withExpireTimeIn(CarbonInterval::minutes(30));
                }

                $created = $builder->create(1);
                $voucher = collect(is_array($created) ? $created : [$created])->first();

                if ($voucher === null) {
                    throw new PayCodeIssuanceFailed('Claim preview voucher creation did not return a voucher.');
                }

                return [
                    'voucher_id' => $voucher->getKey(),
                    'code' => (string) $voucher->code,
                    'amount' => data_get($instructionPayload, 'cash.amount'),
                    'currency' => data_get($instructionPayload, 'cash.currency'),
                    'metadata' => $voucher->metadata ?? null,
                ];
            });
        } finally {
            if ($previousUser instanceof Authenticatable) {
                Auth::setUser($previousUser);
            } else {
                Auth::forgetGuards();
            }
        }
    }
}
