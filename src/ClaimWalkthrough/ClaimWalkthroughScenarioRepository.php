<?php

declare(strict_types=1);

namespace LBHurtado\XChange\ClaimWalkthrough;

final class ClaimWalkthroughScenarioRepository
{
    /**
     * @return array<string, array<string, mixed>>
     */
    public function all(): array
    {
        $defaultRiderMessage = (string) config(
            'x-change.claim_preview.rider.message',
            'The quick brown fox jumps over the lazy dog.',
        );
        $defaultRiderSplash = (string) config('x-change.claim_preview.rider.splash_html', '');
        $defaultRiderUrl = (string) config(
            'x-change.claim_preview.rider.url',
            'https://open.spotify.com/track/6kyxQuFD38mo4S3urD2Wkw?si=6yq6W4oRQ76HGpbDCG-74w&utm_source=copy-link&rowId=35e1bf6b4faf0da8',
        );
        $defaultRiderRedirectTimeout = config('x-change.claim_preview.rider.redirect_timeout', 4);
        $defaultRiderSplashTimeout = config('x-change.claim_preview.rider.splash_timeout');
        $defaultRiderOgSource = config('x-change.claim_preview.rider.og_source');
        $riderPreviewFixture = [
            'amount' => '15.00',
            'money_movement' => false,
            'form_flow_default_splash' => true,
            'handlers' => [
                'kyc' => false,
                'location' => false,
                'otp' => false,
                'selfie' => false,
                'signature' => false,
            ],
            'rider_splash' => true,
            'rider_redirect' => true,
            'rider' => [
                'message' => $defaultRiderMessage,
                'url' => $defaultRiderUrl,
                'redirect_timeout' => $defaultRiderRedirectTimeout,
                'splash' => $defaultRiderSplash,
                'splash_timeout' => $defaultRiderSplashTimeout,
                'og_source' => $defaultRiderOgSource,
            ],
            'feedback' => false,
        ];
        $riderPreviewFixture['og_preview'] = (new RiderOgPreviewPayloadFactory)->make($riderPreviewFixture);

        return [
            'claim_basic_15_no_inputs_no_riders_no_feedbacks' => [
                'key' => 'claim_basic_15_no_inputs_no_riders_no_feedbacks',
                'label' => 'Basic ₱15 claim without extras',
                'description' => 'Plain redeemer claim journey for a ₱15 voucher with no extra form inputs, no rider handoff, and no feedback capture.',
                'fixture' => [
                    'amount' => '15.00',
                    'money_movement' => false,
                    'form_flow_default_splash' => false,
                    'handlers' => [
                        'kyc' => false,
                        'location' => false,
                        'otp' => false,
                        'selfie' => false,
                        'signature' => false,
                    ],
                    'rider_splash' => false,
                    'rider_redirect' => false,
                    'feedback' => false,
                ],
                'checkpoints' => [
                    [
                        'key' => 'claim-entry',
                        'title' => 'Claim entry',
                        'route' => '/x/claim',
                        'actor' => 'redeemer',
                        'expected' => 'Redeemer can start with the voucher code and is not asked for extra verification inputs.',
                        'screenshot' => 'screenshots/01-claim-entry.png',
                    ],
                    [
                        'key' => 'claim-review',
                        'title' => 'Claim review',
                        'route' => '/x/claim/{code}',
                        'actor' => 'redeemer',
                        'expected' => 'The ₱15 claim is clear and does not include rider, feedback, KYC, location, selfie, signature, or OTP prompts.',
                        'screenshot' => 'screenshots/02-claim-review.png',
                    ],
                    [
                        'key' => 'claim-success',
                        'title' => 'Claim success',
                        'route' => '/x/claim/{code}/success',
                        'actor' => 'redeemer',
                        'expected' => 'The journey ends on the x-change success state without external rider redirection or feedback capture.',
                        'screenshot' => 'screenshots/03-claim-success.png',
                    ],
                ],
            ],
            'claim_basic_15_preview_with_rider' => [
                'key' => 'claim_basic_15_preview_with_rider',
                'label' => 'Basic ₱15 claim preview with rider',
                'description' => 'No-money issuer preview showing the redeemer journey with rider splash, rider message, and rider redirect.',
                'fixture' => $riderPreviewFixture,
                'checkpoints' => [
                    [
                        'key' => 'og-social-preview',
                        'title' => 'Social / OG preview',
                        'route' => 'og-meta://pay-code/{code}',
                        'actor' => 'issuer',
                        'expected' => 'Issuer sees the local Open Graph preview that can later map to the generated social card.',
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
                        'expected' => 'Entering the Pay Code reveals the x-ray claim preview before proceeding.',
                        'screenshot' => 'screenshots/02-xray-preview.png',
                    ],
                    [
                        'key' => 'pre-claim-rider-splash',
                        'title' => 'Rider splash',
                        'route' => '/x/claim',
                        'actor' => 'redeemer',
                        'expected' => 'The issuer rider splash appears before payout details are collected.',
                        'screenshot' => 'screenshots/03-pre-claim-rider-splash.png',
                    ],
                    [
                        'key' => 'form-flow-splash',
                        'title' => 'Form-flow splash',
                        'route' => '/form-flow/{flow_id}',
                        'actor' => 'redeemer',
                        'expected' => 'The form-flow splash boundary is visible after the rider splash.',
                        'screenshot' => 'screenshots/04-form-flow-splash.png',
                    ],
                    [
                        'key' => 'generic-payout-form',
                        'title' => 'Generic payout form',
                        'route' => '/form-flow/{flow_id}',
                        'actor' => 'redeemer',
                        'expected' => 'Redeemer enters mobile number, bank or wallet, and account number.',
                        'screenshot' => 'screenshots/05-generic-payout-form.png',
                    ],
                    [
                        'key' => 'generic-payout-form-filled',
                        'title' => 'Generic payout form filled',
                        'route' => '/form-flow/{flow_id}',
                        'actor' => 'redeemer',
                        'expected' => 'Redeemer payout details are filled before moving to confirmation.',
                        'screenshot' => 'screenshots/06-generic-payout-form-filled.png',
                    ],
                    [
                        'key' => 'confirmation',
                        'title' => 'Claim confirmation',
                        'route' => '/x/claim/{code}/confirm',
                        'actor' => 'redeemer',
                        'expected' => 'Redeemer reviews collected details and confirms the claim.',
                        'screenshot' => 'screenshots/07-confirmation.png',
                    ],
                    [
                        'key' => 'claim-success-rider-message',
                        'title' => 'Claim success with rider message',
                        'route' => '/x/claim/{code}/success',
                        'actor' => 'redeemer',
                        'expected' => 'Redeemer sees the completed claim state with the issuer rider message in the same success card.',
                        'screenshot' => 'screenshots/08-claim-success-rider-message.png',
                    ],
                    [
                        'key' => 'rider-redirect-countdown',
                        'title' => 'Rider redirect handoff',
                        'route' => '/x/claim/{code}/success',
                        'actor' => 'redeemer',
                        'expected' => 'The success page shows an intentional rider redirect countdown and Continue Now action.',
                        'screenshot' => 'screenshots/09-rider-redirect-countdown.png',
                    ],
                    [
                        'key' => 'rider-url',
                        'title' => 'Rider URL',
                        'route' => '{rider_url}',
                        'actor' => 'redeemer',
                        'expected' => 'The browser reaches the configured rider URL.',
                        'screenshot' => 'screenshots/10-rider-url.png',
                    ],
                ],
            ],
            'claim_basic_15_full_browser_handoff' => [
                'key' => 'claim_basic_15_full_browser_handoff',
                'label' => 'Basic ₱15 browser claim with rider handoff',
                'description' => 'Actual browser walkthrough from Pay Code entry to x-ray preview, splash, generic payout form, confirmation, success, and rider redirect.',
                'fixture' => [
                    'amount' => '15.00',
                    'money_movement' => true,
                    'form_flow_default_splash' => true,
                    'handlers' => [
                        'kyc' => false,
                        'location' => false,
                        'otp' => false,
                        'selfie' => false,
                        'signature' => false,
                    ],
                    'rider_splash' => true,
                    'rider_redirect' => true,
                    'feedback' => false,
                ],
                'checkpoints' => [
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
                        'expected' => 'Entering the Pay Code reveals the x-ray claim preview before proceeding.',
                        'screenshot' => 'screenshots/02-xray-preview.png',
                    ],
                    [
                        'key' => 'pre-claim-rider-splash',
                        'title' => 'Rider splash',
                        'route' => '/x/claim',
                        'actor' => 'redeemer',
                        'expected' => 'The rider splash renders issuer-authored content before payout details are collected.',
                        'screenshot' => 'screenshots/03-pre-claim-rider-splash.png',
                    ],
                    [
                        'key' => 'form-flow-splash',
                        'title' => 'Form-flow splash',
                        'route' => '/form-flow/{flow_id}',
                        'actor' => 'redeemer',
                        'expected' => 'The form-flow splash boundary is visible when it is not consumed by the rider splash.',
                        'screenshot' => 'screenshots/04-form-flow-splash.png',
                    ],
                    [
                        'key' => 'generic-payout-form',
                        'title' => 'Generic payout form',
                        'route' => '/form-flow/{flow_id}',
                        'actor' => 'redeemer',
                        'expected' => 'Redeemer enters mobile number, bank or wallet, and account number.',
                        'screenshot' => 'screenshots/05-generic-payout-form.png',
                    ],
                    [
                        'key' => 'generic-payout-form-filled',
                        'title' => 'Generic payout form filled',
                        'route' => '/form-flow/{flow_id}',
                        'actor' => 'redeemer',
                        'expected' => 'Redeemer payout details are filled before moving to confirmation.',
                        'screenshot' => 'screenshots/06-generic-payout-form-filled.png',
                    ],
                    [
                        'key' => 'confirmation',
                        'title' => 'Claim confirmation',
                        'route' => '/x/claim/{code}/confirm',
                        'actor' => 'redeemer',
                        'expected' => 'Redeemer reviews collected details and confirms the claim.',
                        'screenshot' => 'screenshots/07-confirmation.png',
                    ],
                    [
                        'key' => 'claim-success-rider-message',
                        'title' => 'Claim success with rider message',
                        'route' => '/x/claim/{code}/success',
                        'actor' => 'redeemer',
                        'expected' => 'Redeemer sees the completed claim state with the issuer rider message in the same success card.',
                        'screenshot' => 'screenshots/08-claim-success-rider-message.png',
                    ],
                    [
                        'key' => 'rider-redirect-countdown',
                        'title' => 'Rider redirect handoff',
                        'route' => '/x/claim/{code}/success',
                        'actor' => 'redeemer',
                        'expected' => 'The success page shows an intentional rider redirect countdown and Continue Now action.',
                        'screenshot' => 'screenshots/09-rider-redirect-countdown.png',
                    ],
                    [
                        'key' => 'rider-url',
                        'title' => 'Rider URL',
                        'route' => '{rider_url}',
                        'actor' => 'redeemer',
                        'expected' => 'The browser reaches the configured rider URL.',
                        'screenshot' => 'screenshots/10-rider-url.png',
                    ],
                ],
            ],
            'claim_basic_no_rider' => [
                'key' => 'claim_basic_no_rider',
                'label' => 'Basic claim without rider handoff',
                'description' => 'Baseline claim journey from code entry through success without rider splash or redirect noise.',
                'fixture' => [
                    'money_movement' => false,
                    'form_flow_default_splash' => false,
                    'rider_splash' => false,
                    'rider_redirect' => false,
                ],
                'checkpoints' => [
                    [
                        'key' => 'claim-entry',
                        'title' => 'Claim entry',
                        'route' => '/x/claim',
                        'actor' => 'redeemer',
                        'expected' => 'Redeemer can enter or confirm the voucher code without seeing unrelated rider copy.',
                        'screenshot' => 'screenshots/01-claim-entry.png',
                    ],
                    [
                        'key' => 'claim-form',
                        'title' => 'Claim information',
                        'route' => '/x/claim/{code}',
                        'actor' => 'redeemer',
                        'expected' => 'Required claim fields are clear, compact, and do not expose issuer-side approval controls.',
                        'screenshot' => 'screenshots/02-claim-form.png',
                    ],
                    [
                        'key' => 'claim-success',
                        'title' => 'Claim success',
                        'route' => '/x/claim/{code}/success',
                        'actor' => 'redeemer',
                        'expected' => 'Success state confirms the claim outcome before any optional handoff.',
                        'screenshot' => 'screenshots/03-claim-success.png',
                    ],
                ],
            ],
            'claim_rider_full_walkthrough' => [
                'key' => 'claim_rider_full_walkthrough',
                'label' => 'Claim with rider splash and redirect',
                'description' => 'Checks the handoff boundary when a rider splash and rider URL are intentionally configured.',
                'fixture' => [
                    'money_movement' => false,
                    'form_flow_default_splash' => false,
                    'rider_splash' => true,
                    'rider_redirect' => true,
                ],
                'checkpoints' => [
                    [
                        'key' => 'claim-success',
                        'title' => 'Claim success before rider',
                        'route' => '/x/claim/{code}/success',
                        'actor' => 'redeemer',
                        'expected' => 'Redeemer sees a clear completed claim state before the rider handoff begins.',
                        'screenshot' => 'screenshots/01-claim-success.png',
                    ],
                    [
                        'key' => 'rider-splash',
                        'title' => 'Rider splash',
                        'route' => '/x/claim/{code}/success',
                        'actor' => 'redeemer',
                        'expected' => 'The rider splash is purposeful content from x-rider, not the form-flow fallback splash.',
                        'screenshot' => 'screenshots/02-rider-splash.png',
                    ],
                    [
                        'key' => 'rider-redirect',
                        'title' => 'Rider redirect',
                        'route' => '{rider_url}',
                        'actor' => 'redeemer',
                        'expected' => 'External redirect is observable and does not feel like a silent page disappearance.',
                        'screenshot' => 'screenshots/03-rider-redirect.png',
                    ],
                ],
            ],
            'claim_paynamics_approval_walkthrough' => [
                'key' => 'claim_paynamics_approval_walkthrough',
                'label' => 'Paynamics issuer OTP approval',
                'description' => 'Separates redeemer waiting from issuer OTP entry for Paynamics payout authorization.',
                'fixture' => [
                    'money_movement' => false,
                    'provider' => 'paynamics',
                    'form_flow_default_splash' => false,
                    'approval_entry_mode' => 'redeemer_waiting',
                ],
                'checkpoints' => [
                    [
                        'key' => 'redeemer-waiting',
                        'title' => 'Redeemer approval waiting',
                        'route' => '/x/claim/{code}/approval',
                        'actor' => 'redeemer',
                        'expected' => 'Redeemer waits without an OTP field or Verify OTP action.',
                        'screenshot' => 'screenshots/01-redeemer-approval-waiting.png',
                    ],
                    [
                        'key' => 'issuer-otp-entry',
                        'title' => 'Issuer OTP entry',
                        'route' => '/x/pay-codes/{code}/approval',
                        'actor' => 'issuer',
                        'expected' => 'Issuer enters the Paynamics OTP on the issuer approval surface.',
                        'screenshot' => 'screenshots/02-issuer-otp-entry.png',
                    ],
                    [
                        'key' => 'issuer-return',
                        'title' => 'Issuer return to pay code',
                        'route' => '/x/pay-codes/{code}',
                        'actor' => 'issuer',
                        'expected' => 'Issuer returns to pay-code management instead of following the redeemer rider redirect.',
                        'screenshot' => 'screenshots/03-issuer-return.png',
                    ],
                    [
                        'key' => 'redeemer-completion',
                        'title' => 'Redeemer completion after refresh',
                        'route' => '/x/claim/{code}/success',
                        'actor' => 'redeemer',
                        'expected' => 'Redeemer reaches success after approval and can continue to rider handoff if configured.',
                        'screenshot' => 'screenshots/04-redeemer-completion.png',
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function get(string $key): array
    {
        return $this->all()[$key] ?? [];
    }

    public function exists(string $key): bool
    {
        return array_key_exists($key, $this->all());
    }
}
