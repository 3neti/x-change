<?php

declare(strict_types=1);

namespace LBHurtado\XChange\ClaimWalkthrough;

use LBHurtado\Voucher\Data\VoucherInstructionsData;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Services\Claim\ClaimExperienceCompiler;

final class ClaimPreviewScenarioFactory
{
    public function __construct(
        private readonly ClaimExperienceCompiler $claimExperience,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function fromInstructions(VoucherInstructionsData $instructions): array
    {
        $payload = $instructions->toArray();
        $experience = $this->claimExperience
            ->compile((new Voucher)->forceFill([
                'code' => 'PREVIEW',
                'metadata' => [
                    'instructions' => $payload,
                ],
            ]))
            ->toArray();
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
            'claim_experience' => $experience,
        ];
        $fixture['og_preview'] = (new RiderOgPreviewPayloadFactory)->make($fixture);
        $fixture['stamp_preview'] = (new RiderStampPreviewPayloadFactory)->make($fixture);

        return [
            'key' => 'claim_instructions_preview',
            'version' => 1,
            'label' => 'Claim experience preview',
            'description' => 'Issuer preview generated from the current VoucherInstructionsData contract.',
            'fixture' => $fixture,
            'checkpoints' => $this->checkpoints($fixture, $experience),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function hasHandler(array $payload, string $handler): bool
    {
        return (bool) data_get($payload, "metadata.handlers.{$handler}", false)
            || (bool) data_get($payload, "metadata.claim_experience.handlers.{$handler}", false)
            || (bool) data_get($payload, "metadata.custom.handlers.{$handler}", false)
            || (bool) data_get($payload, "metadata.custom.claim_experience.handlers.{$handler}", false)
            || in_array($handler, (array) data_get($payload, 'inputs.requirements', []), true)
            || (bool) data_get($payload, "validation.{$handler}.required", false);
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
    private function checkpoints(array $fixture, array $experience): array
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
                'key' => 'claim-entry',
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

        if ($this->hasNamedSliceSelector($experience)) {
            $checkpoints[] = [
                'key' => 'named-slice-selection',
                'title' => 'Choose claim portions',
                'route' => '/x/claim',
                'actor' => 'redeemer',
                'expected' => 'Redeemer selects the available scheduled portions to claim.',
                'screenshot' => 'screenshots/04-named-slice-selection.png',
            ];
        }

        $isAccountFunding = data_get(
            $fixture,
            'instructions.claim.default_outcome',
        ) === 'account_funding';
        $checkpoints[] = [
            'key' => $isAccountFunding ? 'account-funding-details' : 'generic-payout-form',
            'title' => $isAccountFunding ? 'Account funding details' : 'Claim details',
            'route' => '/form-flow/{flow_id}',
            'actor' => 'redeemer',
            'expected' => $isAccountFunding
                ? 'Redeemer reviews the Account that will receive the claimed value.'
                : 'Redeemer enters the required recipient and payout details.',
            'screenshot' => 'screenshots/04-generic-payout-form.png',
        ];

        foreach ($this->validationCheckpoints($fixture) as $checkpoint) {
            $checkpoints[] = $checkpoint;
        }

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

    /**
     * @param  array<string, mixed>  $experience
     */
    private function hasNamedSliceSelector(array $experience): bool
    {
        return collect(data_get($experience, 'phases', []))
            ->where('key', 'form_flow')
            ->flatMap(fn (array $phase): array => $phase['fields'] ?? [])
            ->contains(fn (array $field): bool => ($field['type'] ?? null) === 'slice_selector');
    }

    /**
     * @param  array<string, mixed>  $fixture
     * @return array<int, array<string, mixed>>
     */
    private function validationCheckpoints(array $fixture): array
    {
        $instructions = (array) ($fixture['instructions'] ?? []);
        $requirements = collect(data_get($instructions, 'inputs.requirements', []))
            ->filter(fn (mixed $requirement): bool => is_string($requirement))
            ->map(fn (string $requirement): string => strtolower(trim($requirement)));
        $structured = collect(array_keys(
            (array) data_get($instructions, 'validation', [])
        ));
        $cashValidation = collect(array_keys(
            (array) data_get($instructions, 'cash.validation', [])
        ));
        $keys = $requirements
            ->merge($structured)
            ->merge($cashValidation->map(
                fn (string $key): string => $key === 'mobile_verification' ? 'otp' : $key
            ))
            ->merge(
                collect((array) ($fixture['handlers'] ?? []))
                    ->filter(fn (mixed $enabled): bool => $enabled === true)
                    ->keys()
            )
            ->filter()
            ->unique()
            ->values();
        $labels = [
            'kyc' => ['Identity verification', 'Redeemer completes the configured identity verification.'],
            'otp' => ['Mobile verification', 'Redeemer verifies control of the configured mobile number.'],
            'selfie' => ['Selfie verification', 'Redeemer captures the required selfie evidence.'],
            'face_match' => ['Face match', 'Redeemer completes the configured face-match check.'],
            'signature' => ['Signature', 'Redeemer provides the required signature.'],
            'location' => ['Location check', 'Redeemer permits the configured location verification.'],
            'time' => ['Claim timing', 'Redeemer continues within the configured claim window.'],
            'secret' => ['Claim secret', 'Redeemer supplies the configured claim secret.'],
            'mobile' => ['Recipient match', 'Redeemer confirms the intended mobile recipient.'],
            'payable' => ['Payee verification', 'Redeemer confirms the configured payee or vendor alias.'],
            'country' => ['Country check', 'Redeemer satisfies the configured country rule.'],
        ];

        return $keys
            ->filter(fn (string $key): bool => isset($labels[$key]))
            ->map(function (string $key) use ($labels): array {
                [$title, $expected] = $labels[$key];

                return [
                    'key' => 'validation-'.$key,
                    'title' => $title,
                    'route' => '/form-flow/{flow_id}',
                    'actor' => 'redeemer',
                    'expected' => $expected,
                    'screenshot' => "screenshots/validation-{$key}.png",
                ];
            })
            ->values()
            ->all();
    }
}
