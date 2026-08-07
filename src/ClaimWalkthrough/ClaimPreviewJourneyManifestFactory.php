<?php

declare(strict_types=1);

namespace LBHurtado\XChange\ClaimWalkthrough;

use Illuminate\Filesystem\Filesystem;
use LBHurtado\FormFlowManager\Data\FormFlowPreviewStepData;

final class ClaimPreviewJourneyManifestFactory
{
    public function __construct(
        private readonly Filesystem $files,
    ) {}

    /**
     * @param  array<string, mixed>  $report
     * @param  array<string, mixed>  $scenario
     * @return array<string, mixed>
     */
    public function fromReport(array $report, array $scenario, array $formFlowScreens = []): array
    {
        $canonical = $this->canonicalSteps($scenario);
        $storyboard = $this->storyboard($report);
        $captured = collect(data_get($storyboard, 'checkpoints', []))
            ->filter(fn (mixed $checkpoint): bool => is_array($checkpoint))
            ->mapWithKeys(function (array $checkpoint): array {
                $key = $this->canonicalKey((string) ($checkpoint['key'] ?? ''));

                return $key === '' ? [] : [$key => $checkpoint];
            });
        $root = $this->artifactRoot($report);

        $steps = collect($canonical)
            ->map(function (array $step, int $index) use ($captured, $root, $scenario): array {
                $checkpoint = $captured->get($step['key']);

                return $this->step(
                    step: $step,
                    sequence: $index + 1,
                    checkpoint: is_array($checkpoint) ? $checkpoint : null,
                    root: $root,
                    scenario: $scenario,
                );
            })
            ->values()
            ->all();

        if ($formFlowScreens !== []) {
            $steps = $this->withCompiledFormFlowScreens($steps, $formFlowScreens);
        }

        return [
            'schema' => 'x-change.claim-experience-preview.journey.v2',
            'step_count' => count($steps),
            'steps' => $steps,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $steps
     * @param  array<int, FormFlowPreviewStepData>  $formFlowScreens
     * @return array<int, array<string, mixed>>
     */
    private function withCompiledFormFlowScreens(array $steps, array $formFlowScreens): array
    {
        $formFlowKeys = ['generic-payout-form', 'account-funding-details'];
        $before = [];
        $after = [];
        $enteredFormFlow = false;

        foreach ($steps as $step) {
            $key = (string) ($step['key'] ?? '');

            if (in_array($key, $formFlowKeys, true) || str_starts_with($key, 'validation-')) {
                $enteredFormFlow = true;

                continue;
            }

            if ($enteredFormFlow) {
                $after[] = $step;
            } else {
                $before[] = $step;
            }
        }

        $compiled = collect($formFlowScreens)
            ->map(function (FormFlowPreviewStepData $screen): array {
                $title = (string) ($screen->props['title'] ?? $screen->props['config']['title'] ?? str($screen->handler)->headline());
                $description = (string) ($screen->props['description'] ?? $screen->props['config']['description'] ?? '');

                return [
                    'key' => sprintf('form-flow-%02d-%s', $screen->index + 1, str($screen->handler)->slug()),
                    'phase' => 'form_flow',
                    'title' => $title,
                    'description' => $description,
                    'actor' => 'redeemer',
                    'render_kind' => 'actual_screen',
                    'status' => 'rendered',
                    'frame' => null,
                    'screen' => [
                        'kind' => 'form_flow_handler',
                        'component' => $screen->component,
                        'props' => $screen->props,
                    ],
                ];
            })
            ->all();

        return collect([...$before, ...$compiled, ...$after])
            ->values()
            ->map(function (array $step, int $index): array {
                $step['sequence'] = $index + 1;

                return $step;
            })
            ->all();
    }

    /**
     * @param  array<string, mixed>  $scenario
     * @return array<int, array<string, mixed>>
     */
    private function canonicalSteps(array $scenario): array
    {
        return collect(data_get($scenario, 'checkpoints', []))
            ->filter(
                fn (mixed $checkpoint): bool => is_array($checkpoint)
                    && ($checkpoint['actor'] ?? 'redeemer') === 'redeemer'
                    && ! $this->isInternalCheckpoint($checkpoint)
            )
            ->map(function (array $checkpoint): array {
                $key = $this->canonicalKey((string) ($checkpoint['key'] ?? ''));

                return [
                    'key' => $key,
                    'phase' => $this->phase($key),
                    'title' => (string) ($checkpoint['title'] ?? 'Claim step'),
                    'description' => (string) ($checkpoint['expected'] ?? ''),
                    'actor' => 'redeemer',
                ];
            })
            ->filter(fn (array $step): bool => $step['key'] !== '')
            ->unique('key')
            ->values()
            ->all();
    }

    /**
     * Keep recorder-only diagnostic overlays out of the issuer's recipient
     * walkthrough. They are useful while developing the claim experience, but
     * do not explain an action the recipient takes.
     *
     * @param  array<string, mixed>  $checkpoint
     */
    private function isInternalCheckpoint(array $checkpoint): bool
    {
        return $this->canonicalKey((string) ($checkpoint['key'] ?? ''))
            === 'xray-preview';
    }

    /**
     * @param  array<string, mixed>  $step
     * @param  array<string, mixed>|null  $checkpoint
     * @return array<string, mixed>
     */
    private function step(
        array $step,
        int $sequence,
        ?array $checkpoint,
        ?string $root,
        array $scenario,
    ): array {
        $frame = $checkpoint === null
            ? null
            : $this->frame($checkpoint, $root);

        return [
            ...$step,
            'sequence' => $sequence,
            'render_kind' => $frame === null ? 'live_screen' : 'captured_frame',
            'status' => $frame === null ? 'rendered' : 'captured',
            'frame' => $frame,
            'screen' => $frame === null ? $this->screen($step, $scenario) : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $step
     * @param  array<string, mixed>  $scenario
     * @return array<string, mixed>
     */
    private function screen(array $step, array $scenario): array
    {
        $key = (string) ($step['key'] ?? '');
        $fixture = (array) data_get($scenario, 'fixture', []);
        $instructions = (array) data_get($fixture, 'instructions', []);
        $rider = (array) data_get($fixture, 'rider', []);
        $amount = (float) data_get($fixture, 'amount', 0);
        $formattedAmount = '₱'.number_format($amount, 2);
        $validation = str_starts_with($key, 'validation-')
            ? substr($key, strlen('validation-'))
            : null;

        return [
            'kind' => $this->screenKind($key, $validation),
            'code' => 'PREVIEW',
            'amount' => $formattedAmount,
            'title' => $this->screenTitle($key, $validation),
            'description' => $this->screenDescription($key, $validation),
            'fields' => $this->screenFields($key, $instructions, $validation),
            'message' => $this->plainText((string) data_get($rider, 'message', '')),
            'artwork_url' => $this->artworkUrl($key, $fixture),
            'handoff' => $key === 'rider-url'
                ? (array) data_get($fixture, 'rider_handoff_preview', [])
                : null,
        ];
    }

    private function screenKind(string $key, ?string $validation): string
    {
        if ($validation !== null) {
            return 'validation_'.$validation;
        }

        return match ($key) {
            'claim-entry' => 'claim_entry',
            'pre-claim-rider-splash' => 'rider_splash',
            'named-slice-selection' => 'slice_selection',
            'generic-payout-form', 'account-funding-details' => 'claim_details',
            'confirmation' => 'confirmation',
            'claim-success-rider-message' => 'success',
            'rider-redirect-countdown' => 'redirect_countdown',
            'rider-url' => 'rider_handoff',
            default => 'claim_step',
        };
    }

    private function screenTitle(string $key, ?string $validation): string
    {
        if ($validation !== null) {
            return match ($validation) {
                'kyc' => 'Identity Check',
                'otp' => 'OTP Verification',
                'selfie' => 'Selfie Required',
                'signature' => 'Signature Required',
                'location' => 'Location Required',
                'secret' => 'Claim Secret',
                default => 'Verification Required',
            };
        }

        return match ($key) {
            'claim-entry' => 'Claim Pay Code',
            'pre-claim-rider-splash' => 'A message for you',
            'named-slice-selection' => 'Choose claim portions',
            'generic-payout-form' => 'Claim details',
            'account-funding-details' => 'Add to your Account',
            'confirmation' => 'Confirm Claim',
            'claim-success-rider-message' => 'Claim accepted',
            'rider-redirect-countdown' => 'Continue to the issuer’s link',
            'rider-url' => 'Issuer link',
            default => 'Pay Code claim',
        };
    }

    private function screenDescription(string $key, ?string $validation): string
    {
        if ($validation !== null) {
            return match ($validation) {
                'kyc' => 'Your Pay Code remains protected while identity verification is resolved.',
                'otp' => 'Enter the 6-digit code sent to your mobile.',
                'selfie' => 'Please take a clear photo of yourself.',
                'signature' => 'Please sign in the box below using your finger or mouse.',
                'location' => 'Please share your current location to continue.',
                'secret' => 'Enter the secret supplied by the issuer.',
                default => 'Complete this safeguard to continue.',
            };
        }

        return match ($key) {
            'claim-entry' => 'Enter the Pay Code shared with you.',
            'pre-claim-rider-splash' => 'The issuer’s introduction appears before claim details.',
            'named-slice-selection' => 'Select the available portions you want to claim.',
            'generic-payout-form' => 'Tell us where the funds should be sent.',
            'account-funding-details' => 'Review the Account that will receive the claimed value.',
            'confirmation' => 'Review and confirm your Pay Code claim.',
            'claim-success-rider-message' => 'The claim is recorded. Payout follows provider confirmation.',
            'rider-redirect-countdown' => 'You will leave x-change after the claim is complete.',
            'rider-url' => 'The issuer’s configured destination opens next.',
            default => 'Continue through the configured claim experience.',
        };
    }

    /**
     * @param  array<string, mixed>  $instructions
     * @return array<int, array{key: string, label: string, value: string}>
     */
    private function screenFields(string $key, array $instructions, ?string $validation): array
    {
        if ($validation !== null) {
            return [];
        }

        if ($key === 'claim-entry') {
            return [['key' => 'code', 'label' => 'Pay Code', 'value' => 'PREVIEW']];
        }

        if ($key === 'confirmation') {
            return [
                ['key' => 'mobile', 'label' => 'Mobile Number', 'value' => '09••• ••• •••'],
                ['key' => 'destination', 'label' => 'Destination', 'value' => 'Selected bank or wallet'],
            ];
        }

        if (! in_array($key, ['generic-payout-form', 'account-funding-details'], true)) {
            return [];
        }

        $labels = [
            'name' => ['Full Name', 'Recipient name'],
            'email' => ['Email Address', 'name@example.com'],
            'mobile' => ['Mobile Number', '09••• ••• •••'],
            'address' => ['Full Address', 'Recipient address'],
            'birth_date' => ['Birth Date', 'MM / DD / YYYY'],
            'reference_code' => ['Reference Code', 'Reference'],
        ];
        $fields = collect((array) data_get($instructions, 'inputs.fields', []))
            ->filter(fn (mixed $field): bool => is_string($field) && isset($labels[$field]))
            ->map(function (string $field) use ($labels): array {
                [$label, $value] = $labels[$field];

                return ['key' => $field, 'label' => $label, 'value' => $value];
            });

        if ($key === 'generic-payout-form') {
            $fields->prepend(['key' => 'account', 'label' => 'Account Number', 'value' => 'Enter account number']);
            $fields->prepend(['key' => 'bank', 'label' => 'Bank or Wallet', 'value' => 'Choose institution']);
        }

        return $fields->unique('key')->values()->all();
    }

    /**
     * @param  array<string, mixed>  $fixture
     */
    private function artworkUrl(string $key, array $fixture): ?string
    {
        if ($key === 'rider-url') {
            $url = data_get($fixture, 'rider_handoff_preview.public_image_url');

            return $this->safeHttpUrl($url);
        }

        if ($key !== 'pre-claim-rider-splash') {
            return null;
        }

        $splash = (string) data_get($fixture, 'rider.splash', '');

        if (preg_match('/<img[^>]+src=["\']([^"\']+)["\']/i', $splash, $matches) !== 1) {
            return null;
        }

        return $this->safeHttpUrl($matches[1] ?? null);
    }

    private function safeHttpUrl(mixed $value): ?string
    {
        if (! is_string($value) || filter_var($value, FILTER_VALIDATE_URL) === false) {
            return null;
        }

        return in_array(parse_url($value, PHP_URL_SCHEME), ['http', 'https'], true)
            ? $value
            : null;
    }

    private function plainText(string $value): string
    {
        return trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5)) ?? '');
    }

    /**
     * @param  array<string, mixed>  $checkpoint
     * @return array<string, mixed>|null
     */
    private function frame(array $checkpoint, ?string $root): ?array
    {
        $path = $checkpoint['screenshot_path'] ?? null;

        if (! is_string($path) || $root === null || ! $this->files->isFile($path)) {
            return null;
        }

        $root = rtrim($root, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;
        $realRoot = realpath($root);
        $realPath = realpath($path);

        if (
            ! is_string($realRoot)
            || ! is_string($realPath)
            || ! str_starts_with($realPath, $realRoot.DIRECTORY_SEPARATOR)
        ) {
            return null;
        }

        $dimensions = @getimagesize($realPath);

        return [
            'artifact' => ltrim(substr($realPath, strlen($realRoot)), DIRECTORY_SEPARATOR),
            'mime_type' => is_array($dimensions) && is_string($dimensions['mime'] ?? null)
                ? $dimensions['mime']
                : 'image/png',
            'sha256' => hash_file('sha256', $realPath) ?: null,
            'width' => is_array($dimensions) ? ($dimensions[0] ?? null) : null,
            'height' => is_array($dimensions) ? ($dimensions[1] ?? null) : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $report
     * @return array<string, mixed>
     */
    private function storyboard(array $report): array
    {
        $path = data_get($report, 'artifacts.storyboard_json');

        if (! is_string($path) || ! $this->files->isFile($path)) {
            return [];
        }

        $decoded = json_decode($this->files->get($path), true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param  array<string, mixed>  $report
     */
    private function artifactRoot(array $report): ?string
    {
        $root = data_get($report, 'artifacts.root');

        return is_string($root) && $root !== '' ? $root : null;
    }

    private function canonicalKey(string $key): string
    {
        return match ($key) {
            'claim-entry-empty' => 'claim-entry',
            default => $key,
        };
    }

    private function phase(string $key): string
    {
        return match (true) {
            in_array($key, ['claim-entry', 'named-slice-selection'], true) => 'entry',
            str_contains($key, 'splash') => 'introduction',
            str_contains($key, 'payout-form'), str_contains($key, 'claim-details') => 'inputs',
            str_starts_with($key, 'validation-') => 'validation',
            $key === 'confirmation' => 'review',
            str_contains($key, 'approval') => 'approval',
            str_contains($key, 'success') => 'completion',
            str_contains($key, 'redirect'), $key === 'rider-url' => 'handoff',
            default => 'claim',
        };
    }
}
