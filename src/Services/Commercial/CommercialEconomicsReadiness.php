<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Commercial;

use LBHurtado\XChange\Models\CommercialComponentEconomics;
use LBHurtado\XChange\Models\CommercialComponentEconomicsActivation;
use LBHurtado\XChange\Models\CommercialComponentEconomicsHead;
use LBHurtado\XChange\Models\CommercialOfferingActivation;
use LBHurtado\XChange\Exceptions\CommercialEconomicsUnavailable;
use Throwable;

final readonly class CommercialEconomicsReadiness
{
    public function __construct(private DatabaseCommercialComponentEconomicsResolver $resolver) {}

    /**
     * @param  list<string>  $profiles
     * @return array{ready: bool, profiles: array<string, array<string, mixed>>}
     */
    public function inspect(array $profiles): array
    {
        $results = [];

        foreach ($profiles as $profile) {
            $profile = trim($profile);
            if ($profile === '') {
                continue;
            }

            $results[$profile] = $this->inspectProfile($profile);
        }

        return [
            'ready' => $results !== [] && collect($results)->every(
                static fn (array $result): bool => $result['ready'] === true,
            ),
            'profiles' => $results,
        ];
    }

    /** @return array<string, mixed> */
    private function inspectProfile(string $profile): array
    {
        $head = CommercialComponentEconomicsHead::query()
            ->with('currentActivation.economics.offering')
            ->whereKey($profile)
            ->first();
        $activation = $head?->currentActivation;
        $economics = $activation?->economics;
        $offeringActivation = CommercialOfferingActivation::query()
            ->where('profile', $profile)
            ->whereNull('deactivated_at')
            ->latest('activated_at')
            ->first();

        try {
            $this->resolver->resolve($profile);
            $ready = true;
            $reason = null;
        } catch (Throwable $exception) {
            $ready = false;
            $reason = $exception instanceof CommercialEconomicsUnavailable
                ? $exception->diagnostic
                : $exception->getMessage();
        }

        return [
            'profile' => $profile,
            'ready' => $ready,
            'reason' => $reason,
            'offering_id' => $offeringActivation?->commercial_offering_id,
            'offering_version' => $offeringActivation?->offering_version,
            'offering_snapshot_hash' => $offeringActivation?->snapshot_hash,
            'economics_id' => $economics?->getKey(),
            'economics_version' => $economics?->version,
            'economics_artifact_hash' => $economics?->artifact_hash,
            'activation_id' => $activation instanceof CommercialComponentEconomicsActivation
                ? $activation->getKey()
                : null,
            'checked_at' => now()->toIso8601String(),
        ];
    }
}
