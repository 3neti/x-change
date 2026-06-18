<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services;

use Illuminate\Support\Facades\Route;

class BuildProvisioningRequirementViewData
{
    public function __construct(
        protected BuildProvisioningFlowDescriptor $descriptors,
    ) {}

    /**
     * @param  array<string, mixed>|null  $requirement
     * @return array<string, mixed>|null
     */
    public function handle(?array $requirement): ?array
    {
        if (! is_array($requirement) || $requirement === []) {
            return null;
        }

        $provider = $this->stringOrNull(data_get($requirement, 'provider'));
        $mode = $this->stringOrNull(data_get($requirement, 'mode'));
        $topology = $this->stringOrNull(data_get($requirement, 'topology'))
            ?? $this->stringOrNull(data_get($requirement, 'readiness.topology'));

        $descriptor = data_get($requirement, 'descriptor');

        if (! is_array($descriptor) && $provider !== null && $mode !== null) {
            $descriptor = $this->descriptors->handle($provider, $mode, $topology)->toArray();
        }

        return [
            'purpose' => data_get($requirement, 'purpose'),
            'provider' => $provider,
            'topology' => $topology,
            'mode' => $mode,
            'reason' => $this->stringOrNull(data_get($requirement, 'reason')),
            'missing' => array_values(array_filter(
                (array) data_get($requirement, 'missing', []),
                static fn (mixed $value): bool => is_string($value) && trim($value) !== '',
            )),
            'readiness' => is_array(data_get($requirement, 'readiness'))
                ? data_get($requirement, 'readiness')
                : null,
            'onboarding' => $this->onboarding(
                is_array(data_get($requirement, 'onboarding'))
                    ? data_get($requirement, 'onboarding')
                    : null,
            ),
            'descriptor' => is_array($descriptor) ? $descriptor : null,
        ];
    }

    /**
     * @param  array<string, mixed>|null  $onboarding
     * @return array<string, mixed>|null
     */
    protected function onboarding(?array $onboarding): ?array
    {
        if ($onboarding === null) {
            return null;
        }

        $reference = $this->stringOrNull(data_get($onboarding, 'reference'));

        if ($reference !== null) {
            data_set($onboarding, 'links.status_url', $this->onboardingStatusUrl($reference));
            data_set($onboarding, 'links.resume_url', $this->onboardingResumeUrl($reference));
        }

        return $onboarding;
    }

    protected function onboardingStatusUrl(string $reference): ?string
    {
        if (! Route::has('onboarding.show')) {
            return null;
        }

        return route('onboarding.show', ['reference' => $reference], false);
    }

    protected function onboardingResumeUrl(string $reference): ?string
    {
        if (! Route::has('onboarding.web.show')) {
            return null;
        }

        return route('onboarding.web.show', ['reference' => $reference], false);
    }

    protected function stringOrNull(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
