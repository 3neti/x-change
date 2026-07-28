<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Claim;

use Illuminate\Support\Str;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Contracts\RiderStampRecipientResolverContract;
use LBHurtado\XChange\Data\Claim\RiderStampRecipientData;

final class DefaultRiderStampRecipientResolver implements RiderStampRecipientResolverContract
{
    public function resolve(Voucher $voucher): RiderStampRecipientData
    {
        if (! config('x-change.claim.share.recipient.enabled', true)) {
            return new RiderStampRecipientData(
                eyebrow: '',
                label: '',
                visible: false,
            );
        }

        $reference = collect([
            data_get($voucher, 'instructions.feedback.mobile'),
            data_get($voucher, 'instructions.cash.validation.mobile'),
            data_get($voucher, 'instructions.cash.validation.payable'),
        ])
            ->filter(
                static fn (mixed $value): bool => is_scalar($value)
                    && filled($value)
                    && mb_strtolower(trim((string) $value)) !== 'required',
            )
            ->map(static fn (mixed $value): string => trim((string) $value))
            ->first() ?? '';
        $digits = Str::of($reference)
            ->replaceMatches('/\D+/', '')
            ->toString();
        $isMobile = preg_match('/^(?:\+|09|639|63)/', $reference) === 1
            && mb_strlen($digits) >= 4;
        $label = match (true) {
            $reference === '', mb_strtoupper($reference) === 'CASH' => (string) config(
                'x-change.claim.share.recipient.fallback_label',
                'Anyone with this Pay Code',
            ),
            $isMobile => 'Mobile ending '.mb_substr($digits, -4),
            default => Str::of($reference)
                ->stripTags()
                ->squish()
                ->limit(80)
                ->toString(),
        };

        return new RiderStampRecipientData(
            eyebrow: (string) config(
                'x-change.claim.share.recipient.eyebrow',
                'Prepared for',
            ),
            label: $label,
            visible: true,
        );
    }
}
