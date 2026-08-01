<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Onboarding;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use LBHurtado\Onboarding\Contracts\ContactUserProvisionerContract;
use LBHurtado\Onboarding\Data\ContactPromotionResultData;
use LBHurtado\XChange\Contracts\AccountProvisioningContract;
use LBHurtado\XChange\Support\Auth\MobileNumber;
use RuntimeException;

final readonly class XChangeContactUserProvisioner implements ContactUserProvisionerContract
{
    public function __construct(
        private AccountProvisioningContract $accounts,
        private AccountPinSetupState $pinSetup,
        private OnboardingCredentialPolicy $credentials,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function provision(mixed $contact, array $attributes = []): ContactPromotionResultData
    {
        $mobile = MobileNumber::normalize(
            $attributes['mobile'] ?? data_get($contact, 'mobile'),
        );
        $name = trim((string) ($attributes['name'] ?? ''));
        $email = mb_strtolower(trim((string) ($attributes['email'] ?? '')));

        if ($mobile === null || $name === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new RuntimeException('Verified mobile, Full Name, and Email are required for Account setup.');
        }

        return DB::transaction(function () use ($attributes, $contact, $email, $mobile, $name): ContactPromotionResultData {
            $modelClass = $this->userModelClass();
            $user = $modelClass::query()
                ->whereIn('mobile', MobileNumber::candidates($mobile))
                ->lockForUpdate()
                ->first();
            $reused = $user instanceof Model;

            $emailOwner = $modelClass::query()
                ->whereRaw('lower(email) = ?', [$email])
                ->lockForUpdate()
                ->first();

            if ($emailOwner instanceof Model && (! $user instanceof Model || ! $emailOwner->is($user))) {
                throw new RuntimeException('That Email is already linked to another Account.');
            }

            if (! $user instanceof Model) {
                $user = new $modelClass;

                if (! $user instanceof Model || ! $user instanceof Authenticatable) {
                    throw new RuntimeException('The configured onboarding user must be an authenticatable Eloquent model.');
                }

                $user->forceFill([
                    'name' => $name,
                    'mobile' => $mobile,
                    'email' => $email,
                    'password' => Hash::make(Str::password(40)),
                ]);
            } else {
                $existingEmail = mb_strtolower(trim((string) $user->getAttribute('email')));

                if ($existingEmail !== '' && $existingEmail !== $email) {
                    throw new RuntimeException('The verified Mobile is already linked to a different Email.');
                }

                $user->setAttribute('name', $user->getAttribute('name') ?: $name);
                $user->setAttribute('email', $user->getAttribute('email') ?: $email);
            }

            if (($attributes['mobile_verified'] ?? false) === true) {
                $user->setAttribute(
                    'mobile_verified_at',
                    $user->getAttribute('mobile_verified_at') ?? now(),
                );
            }

            $user->save();

            if (
                ! $reused
                && $this->credentials->requiresInvitedAccountPinSetup()
            ) {
                $this->pinSetup->markRequired($user);
            }

            $portfolio = $this->accounts->provision($user);

            return new ContactPromotionResultData(
                promoted: true,
                user: $user->fresh() ?? $user,
                contact: $contact,
                meta: [
                    'reused' => $reused,
                    'principal_reference' => $portfolio->principalReference,
                    'position_count' => count($portfolio->positions),
                ],
            );
        });
    }

    /**
     * @return class-string<Model>
     */
    private function userModelClass(): string
    {
        $modelClass = config('x-change.onboarding.issuer_model');

        if (! is_string($modelClass) || ! class_exists($modelClass)) {
            throw new RuntimeException('No valid onboarding user model is configured.');
        }

        /** @var class-string<Model> $modelClass */
        return $modelClass;
    }
}
