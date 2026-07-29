<?php

declare(strict_types=1);

namespace LBHurtado\XChange\ClaimWalkthrough;

use LBHurtado\Voucher\Data\VoucherInstructionsData;

final class ClaimPreviewScenarioFactory
{
    /**
     * @return array<string, mixed>
     */
    public function fromInstructions(VoucherInstructionsData $instructions): array
    {
        $payload = $instructions->toArray();
        $fixture = [
            'amount' => (string) data_get($payload, 'cash.amount', '0.00'),
            'money_movement' => false,
            'form_flow_default_splash' => (bool) data_get($payload, 'metadata.claim_preview.form_flow_default_splash', true),
            'handlers' => [
                'kyc' => $this->hasHandler($payload, 'kyc'),
                'location' => $this->hasHandler($payload, 'location'),
                'otp' => $this->hasHandler($payload, 'otp'),
                'selfie' => $this->hasHandler($payload, 'selfie'),
                'signature' => $this->hasHandler($payload, 'signature'),
            ],
            'rider_splash' => filled(data_get($payload, 'rider.splash')),
            'rider_redirect' => filled(data_get($payload, 'rider.url')),
            'rider' => [
                'message' => data_get($payload, 'rider.message'),
                'url' => data_get($payload, 'rider.url'),
                'redirect_timeout' => data_get($payload, 'rider.redirect_timeout'),
                'splash' => data_get($payload, 'rider.splash'),
                'splash_timeout' => data_get($payload, 'rider.splash_timeout'),
                'og_source' => data_get($payload, 'rider.og_source'),
            ],
            'feedback' => $this->hasFeedback($payload),
            'slices' => data_get($payload, 'metadata.custom.named_slices')
                ?? data_get($payload, 'metadata.slices')
                ?? data_get($payload, 'slices'),
            'instructions' => $payload,
        ];
        $fixture['og_preview'] = (new RiderOgPreviewPayloadFactory)->make($fixture);
        $fixture['stamp_preview'] = (new RiderStampPreviewPayloadFactory)->make($fixture);

        return [
            'key' => 'claim_instructions_preview',
            'version' => 1,
            'label' => 'Claim experience preview',
            'description' => 'Issuer preview generated from the current VoucherInstructionsData contract.',
            'fixture' => $fixture,
            'checkpoints' => $this->checkpoints($fixture),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function hasHandler(array $payload, string $handler): bool
    {
        return (bool) data_get($payload, "metadata.handlers.{$handler}", false)
            || (bool) data_get($payload, "metadata.claim_experience.handlers.{$handler}", false);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function hasFeedback(array $payload): bool
    {
        return filled(data_get($payload, 'feedback.email'))
            || filled(data_get($payload, 'feedback.mobile'))
            || filled(data_get($payload, 'feedback.webhook'));
    }

    /**
     * @param  array<string, mixed>  $fixture
     * @return array<int, array<string, mixed>>
     */
    private function checkpoints(array $fixture): array
    {
        $checkpoints = [
            [
                'key' => 'og-social-preview',
                'title' => 'Social / OG preview',
                'route' => 'og-meta://pay-code/{code}',
                'actor' => 'issuer',
                'expected' => 'Issuer sees the generated social preview before sharing the Pay Code.',
                'screenshot' => 'screenshots/00-og-social-preview.png',
            ],
            [
                'key' => 'claim-entry-empty',
                'title' => 'Claim entry',
                'route' => '/x/claim',
                'actor' => 'redeemer',
                'expected' => 'Redeemer lands on the Pay Code entry screen.',
                'screenshot' => 'screenshots/01-claim-entry-empty.png',
            ],
            [
                'key' => 'xray-preview',
                'title' => 'Pay Code x-ray preview',
                'route' => '/x/claim',
                'actor' => 'redeemer',
                'expected' => 'Entering the Pay Code reveals the claim preview before proceeding.',
                'screenshot' => 'screenshots/02-xray-preview.png',
            ],
        ];

        if ((bool) ($fixture['rider_splash'] ?? false)) {
            $checkpoints[] = [
                'key' => 'pre-claim-rider-splash',
                'title' => 'Rider splash',
                'route' => '/x/claim',
                'actor' => 'redeemer',
                'expected' => 'The issuer rider splash appears before payout details are collected.',
                'screenshot' => 'screenshots/03-pre-claim-rider-splash.png',
            ];
        }

        $checkpoints[] = [
            'key' => 'generic-payout-form',
            'title' => 'Generic payout form',
            'route' => '/form-flow/{flow_id}',
            'actor' => 'redeemer',
            'expected' => 'Redeemer enters mobile number, bank or wallet, and account number.',
            'screenshot' => 'screenshots/04-generic-payout-form.png',
        ];
        $checkpoints[] = [
            'key' => 'confirmation',
            'title' => 'Claim confirmation',
            'route' => '/x/claim/{code}/confirm',
            'actor' => 'redeemer',
            'expected' => 'Redeemer reviews collected details and confirms the claim.',
            'screenshot' => 'screenshots/05-confirmation.png',
        ];
        $checkpoints[] = [
            'key' => 'claim-success-rider-message',
            'title' => 'Claim success',
            'route' => '/x/claim/{code}/success',
            'actor' => 'redeemer',
            'expected' => 'Redeemer sees the completed claim state with any issuer rider message.',
            'screenshot' => 'screenshots/06-claim-success-rider-message.png',
        ];

        if ((bool) ($fixture['rider_redirect'] ?? false)) {
            $checkpoints[] = [
                'key' => 'rider-redirect-countdown',
                'title' => 'Rider redirect handoff',
                'route' => '/x/claim/{code}/success',
                'actor' => 'redeemer',
                'expected' => 'The success page shows an intentional rider redirect handoff.',
                'screenshot' => 'screenshots/07-rider-redirect-countdown.png',
            ];
            $checkpoints[] = [
                'key' => 'rider-url',
                'title' => 'Rider URL',
                'route' => '{rider_url}',
                'actor' => 'redeemer',
                'expected' => 'The browser reaches the configured rider URL.',
                'screenshot' => 'screenshots/08-rider-url.png',
            ];
        }

        return $checkpoints;
    }
}
