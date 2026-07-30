<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Campaigns;

use Illuminate\Support\Arr;
use LBHurtado\Voucher\Enums\VoucherInputField;

final class CampaignVoucherInstructionBlueprintSanitizer
{
    public const SCHEMA = 'x-change.campaign-voucher-blueprint.v1';

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function sanitize(array $input): array
    {
        $rider = Arr::only(
            is_array($input['rider'] ?? null) ? $input['rider'] : [],
            [
                'message',
                'url',
                'redirect_timeout',
                'splash',
                'splash_timeout',
                'splash_meta',
                'og_source',
                'message_format',
                'splash_format',
                'stamp',
            ],
        );

        $fields = collect(data_get($input, 'inputs.fields', []))
            ->filter(fn (mixed $field): bool => is_string($field))
            ->intersect(VoucherInputField::values())
            ->unique()
            ->values()
            ->all();

        $channels = collect(data_get($input, 'feedback.channels', []))
            ->filter(fn (mixed $channel): bool => is_string($channel))
            ->intersect(['email', 'mobile'])
            ->unique()
            ->values()
            ->all();

        $validation = Arr::only(
            is_array($input['validation'] ?? null) ? $input['validation'] : [],
            ['signature', 'selfie', 'location', 'otp', 'face_match', 'time'],
        );

        $claim = Arr::only(
            is_array($input['claim'] ?? null) ? $input['claim'] : [],
            ['onboarding'],
        );

        return [
            'inputs' => ['fields' => $fields],
            'feedback' => ['channels' => $channels],
            'rider' => $rider,
            'validation' => $validation,
            'claim' => $claim,
            'expiry_days' => max(1, min(365, (int) ($input['expiry_days'] ?? 7))),
        ];
    }
}
