<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Provisioning;

use DomainException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use LBHurtado\XChange\Enums\ProvisioningOperatorCapability;
use LBHurtado\XChange\Services\Provisioning\ProvisioningOperatorAuthority;
use LBHurtado\XProvisioning\Actions\AttachCommissioningSeatRequest;
use LBHurtado\XProvisioning\Actions\CreateProvisioningRequest;
use LBHurtado\XProvisioning\Actions\SubmitProvisioningRequest;
use LBHurtado\XProvisioning\Enums\ProvisioningActivationMode;
use LBHurtado\XProvisioning\Enums\ProvisioningProfile;
use LBHurtado\XProvisioning\Enums\ProvisioningSeatStatus;
use LBHurtado\XProvisioning\Models\ProvisioningRequest;
use LBHurtado\XProvisioning\Models\ProvisioningSeat;

final readonly class CreateCockpitProvisioningRequest
{
    public function __construct(
        private ProvisioningOperatorAuthority $authority,
        private CreateProvisioningRequest $create,
        private SubmitProvisioningRequest $submit,
        private AttachCommissioningSeatRequest $attachSeat,
    ) {}

    /** @param array<string, mixed> $input */
    public function handle(Model $maker, array $input): ProvisioningRequest
    {
        $this->authority->assertAllows($maker, ProvisioningOperatorCapability::Request);

        return DB::transaction(function () use ($maker, $input): ProvisioningRequest {
            $seat = $this->resolveSeat($input['seat_reference'] ?? null);
            $profile = $seat?->profile ?? ProvisioningProfile::from((string) $input['profile']);
            $profileConfig = (array) config("x-change.provisioning.operator_profiles.{$profile->value}", []);

            if ($profileConfig === []) {
                throw new DomainException('The requested provisioning profile is unavailable.');
            }

            $request = $this->create->handle(
                profile: $profile,
                snapshot: [
                    'label' => (string) ($profileConfig['label'] ?? $profile->value),
                    'purpose' => trim((string) $input['purpose']),
                    'authority_profile' => $profile->value,
                ],
                maker: $maker,
                activationMode: ProvisioningActivationMode::ReviewRequired,
                commissioning: $seat !== null,
                metadata: $seat === null ? [] : ['commissioning_seat_reference' => $seat->reference],
            );

            $this->submit->handle($request, $maker);

            if ($seat !== null) {
                $this->attachSeat->handle($seat, $request);
            }

            return $request->refresh()->load('revisions');
        }, attempts: 3);
    }

    private function resolveSeat(mixed $reference): ?ProvisioningSeat
    {
        if (! is_string($reference) || trim($reference) === '') {
            return null;
        }

        $seat = ProvisioningSeat::query()
            ->where('reference', trim($reference))
            ->lockForUpdate()
            ->firstOrFail();

        if ($seat->status !== ProvisioningSeatStatus::Vacant) {
            throw new DomainException('The commissioning seat is no longer vacant.');
        }

        return $seat;
    }
}
