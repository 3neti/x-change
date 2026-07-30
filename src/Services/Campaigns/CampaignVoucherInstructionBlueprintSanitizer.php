<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Campaigns;

use Illuminate\Support\Arr;
use LBHurtado\Voucher\Enums\VoucherInputField;
use LBHurtado\XRider\Support\RiderHtmlSanitizer;

final class CampaignVoucherInstructionBlueprintSanitizer
{
    public const SCHEMA = 'x-change.campaign-voucher-blueprint.v1';

    public function __construct(
        private readonly RiderHtmlSanitizer $htmlSanitizer,
    ) {}

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
        $rider['stamp'] = Arr::only(
            is_array($rider['stamp'] ?? null) ? $rider['stamp'] : [],
            [
                'source',
                'title',
                'description',
                'fit',
                'position',
                'scrim',
                'theme',
                'version',
                'artwork_source',
                'artwork_treatment',
                'copy_source',
                'show_logo',
                'show_tagline',
                'claim_marker',
                'claim_marker_position',
            ],
        );

        if (
            filled($rider['splash'] ?? null)
            && ! in_array($rider['splash_format'] ?? null, ['plain', 'markdown'], true)
        ) {
            $rider['splash'] = $this->htmlSanitizer->sanitizeSplash((string) $rider['splash']);
            $rider['splash_meta'] = [
                'sanitized' => true,
                'html_profile' => 'rider_splash',
            ];
        } else {
            $rider['splash_meta'] = Arr::only(
                is_array($rider['splash_meta'] ?? null) ? $rider['splash_meta'] : [],
                ['sanitized', 'html_profile'],
            );
        }

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

        $blueprint = [
            'inputs' => ['fields' => $fields],
            'feedback' => ['channels' => $channels],
            'rider' => $rider,
            'validation' => $validation,
            'expiry_days' => max(1, min(365, (int) ($input['expiry_days'] ?? 7))),
        ];

        if (array_key_exists('onboarding', $input)) {
            $blueprint['onboarding'] = $input['onboarding'] === true;
        } else {
            $blueprint['claim'] = $claim;
        }

        return $blueprint;
    }
}
