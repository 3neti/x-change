<?php

declare(strict_types=1);

namespace LBHurtado\XChange\ClaimWalkthrough;

final class ClaimHandlerPreviewScenarioFactory
{
    /**
     * @return array<string, array<string, mixed>>
     */
    public function all(): array
    {
        return collect($this->definitions())
            ->mapWithKeys(fn (array $definition): array => [
                $definition['key'] => $this->make($definition),
            ])
            ->all();
    }

    /**
     * @param  array<string, mixed>  $definition
     * @return array<string, mixed>
     */
    private function make(array $definition): array
    {
        $handler = (string) $definition['handler'];

        return [
            'key' => $definition['key'],
            'label' => $definition['label'],
            'description' => $definition['description'],
            'fixture' => [
                'amount' => '15.00',
                'money_movement' => false,
                'form_flow_default_splash' => false,
                'handlers' => $this->handlers($handler),
                'rider_splash' => false,
                'rider_redirect' => false,
                'feedback' => false,
                'validation' => [
                    $handler => [
                        'required' => true,
                        'purpose' => $definition['purpose'],
                        ...((array) ($definition['validation'] ?? [])),
                    ],
                ],
            ],
            'checkpoints' => $this->checkpoints($definition),
        ];
    }

    /**
     * @return array<string, bool>
     */
    private function handlers(string $activeHandler): array
    {
        return collect(['kyc', 'location', 'otp', 'selfie', 'signature'])
            ->mapWithKeys(fn (string $handler): array => [$handler => $handler === $activeHandler])
            ->all();
    }

    /**
     * @param  array<string, mixed>  $definition
     * @return array<int, array<string, string>>
     */
    private function checkpoints(array $definition): array
    {
        $handler = (string) $definition['handler'];
        $noun = (string) $definition['noun'];

        return [
            [
                'key' => 'claim-entry',
                'title' => 'Claim entry',
                'route' => '/x/claim',
                'actor' => 'redeemer',
                'expected' => (string) $definition['entry_expected'],
                'screenshot' => 'screenshots/01-claim-entry.png',
            ],
            [
                'key' => 'xray-preview',
                'title' => 'Pay Code x-ray preview',
                'route' => '/x/claim',
                'actor' => 'redeemer',
                'expected' => (string) $definition['xray_expected'],
                'screenshot' => 'screenshots/02-xray-preview.png',
            ],
            [
                'key' => 'validation-'.$handler,
                'title' => (string) $definition['validation_title'],
                'route' => '/form-flow/{flow_id}',
                'actor' => 'redeemer',
                'expected' => (string) $definition['validation_expected'],
                'screenshot' => "screenshots/03-validation-{$handler}.png",
            ],
            [
                'key' => 'generic-payout-form',
                'title' => 'Generic payout form',
                'route' => '/form-flow/{flow_id}',
                'actor' => 'redeemer',
                'expected' => "Redeemer enters mobile number, bank or wallet, and account number after {$noun}.",
                'screenshot' => 'screenshots/04-generic-payout-form.png',
            ],
            [
                'key' => 'confirmation',
                'title' => 'Claim confirmation',
                'route' => '/x/claim/{code}/confirm',
                'actor' => 'redeemer',
                'expected' => (string) $definition['confirmation_expected'],
                'screenshot' => 'screenshots/05-confirmation.png',
            ],
            [
                'key' => 'claim-success',
                'title' => 'Claim success',
                'route' => '/x/claim/{code}/success',
                'actor' => 'redeemer',
                'expected' => (string) $definition['success_expected'],
                'screenshot' => 'screenshots/06-claim-success.png',
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function definitions(): array
    {
        return [
            [
                'key' => 'claim_fake_otp_handler_preview',
                'handler' => 'otp',
                'label' => 'Fake redeemer OTP handler preview',
                'description' => 'No-money claim preview for redeemer-side mobile OTP verification. This is not Paynamics issuer payout OTP approval.',
                'purpose' => 'redeemer_mobile_verification',
                'noun' => 'mobile verification',
                'entry_expected' => 'Redeemer starts the claim without seeing issuer payout approval language.',
                'xray_expected' => 'The Pay Code preview explains the amount before mobile verification begins.',
                'validation_title' => 'Mobile verification',
                'validation_expected' => 'Redeemer verifies control of the claim mobile number; copy must not resemble Paynamics issuer OTP approval.',
                'confirmation_expected' => 'Redeemer reviews the payout details before confirming the claim.',
                'success_expected' => 'The journey ends on the x-change success state without issuer approval or rider redirect.',
            ],
            [
                'key' => 'claim_fake_kyc_handler_preview',
                'handler' => 'kyc',
                'label' => 'Fake KYC handler preview',
                'description' => 'No-money claim preview for redeemer-side identity verification copy, loading, and retry states.',
                'purpose' => 'redeemer_identity_verification',
                'noun' => 'identity verification',
                'entry_expected' => 'Redeemer starts the claim with a clear path toward identity verification.',
                'xray_expected' => 'The Pay Code preview explains the amount before identity verification begins.',
                'validation_title' => 'Identity verification',
                'validation_expected' => 'Redeemer sees why identity verification is needed, with visible loading, retry, and continue states.',
                'confirmation_expected' => 'Redeemer reviews the verified claim details before confirming the claim.',
                'success_expected' => 'The journey ends on the x-change success state without external KYC or payout-provider calls.',
            ],
            [
                'key' => 'claim_mocked_location_handler_preview',
                'handler' => 'location',
                'label' => 'Mocked location handler preview',
                'description' => 'No-money claim preview for redeemer-side location permission, retry controls, and map sizing.',
                'purpose' => 'redeemer_location_verification',
                'validation' => ['mocked' => true],
                'noun' => 'location verification',
                'entry_expected' => 'Redeemer starts the claim with a clear path toward location verification.',
                'xray_expected' => 'The Pay Code preview explains the amount before location permission begins.',
                'validation_title' => 'Location check',
                'validation_expected' => 'Redeemer sees why location is needed, with permission guidance, retry controls, and a map surface large enough to inspect.',
                'confirmation_expected' => 'Redeemer reviews the location-verified claim details before confirming the claim.',
                'success_expected' => 'The journey ends on the x-change success state without real geolocation or payout-provider calls.',
            ],
            [
                'key' => 'claim_mocked_selfie_handler_preview',
                'handler' => 'selfie',
                'label' => 'Mocked selfie handler preview',
                'description' => 'No-money claim preview for redeemer-side camera permission, selfie preview, retake, and continue states.',
                'purpose' => 'redeemer_selfie_verification',
                'validation' => ['mocked' => true],
                'noun' => 'selfie verification',
                'entry_expected' => 'Redeemer starts the claim with a clear path toward selfie verification.',
                'xray_expected' => 'The Pay Code preview explains the amount before camera permission begins.',
                'validation_title' => 'Selfie verification',
                'validation_expected' => 'Redeemer sees why camera access is needed, with a large selfie preview, retake control, and continue action.',
                'confirmation_expected' => 'Redeemer reviews the selfie-verified claim details before confirming the claim.',
                'success_expected' => 'The journey ends on the x-change success state without real camera capture or payout-provider calls.',
            ],
            [
                'key' => 'claim_signature_handler_preview',
                'handler' => 'signature',
                'label' => 'Signature handler preview',
                'description' => 'No-money claim preview for redeemer-side signature pad sizing, clear, cancel, and continue states.',
                'purpose' => 'redeemer_signature_capture',
                'noun' => 'signature capture',
                'entry_expected' => 'Redeemer starts the claim with a clear path toward signature capture.',
                'xray_expected' => 'The Pay Code preview explains the amount before signature capture begins.',
                'validation_title' => 'Signature',
                'validation_expected' => 'Redeemer sees a signature pad large enough to sign comfortably, with clear, cancel, and continue controls.',
                'confirmation_expected' => 'Redeemer reviews the signed claim details before confirming the claim.',
                'success_expected' => 'The journey ends on the x-change success state without payout-provider calls.',
            ],
        ];
    }
}
