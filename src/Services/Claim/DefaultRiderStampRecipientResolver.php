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

        $mobile = (string) data_get(
            $voucher,
            'instructions.feedback.mobile',
            '',
        );
        $digits = Str::of($mobile)
            ->replaceMatches('/\D+/', '')
            ->toString();
        $label = mb_strlen($digits) >= 4
            ? 'Mobile ending '.mb_substr($digits, -4)
            : (string) config(
                'x-change.claim.share.recipient.fallback_label',
                'Anyone with this Pay Code',
            );

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
