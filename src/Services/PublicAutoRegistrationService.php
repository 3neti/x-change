<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services;

use DateTimeImmutable;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryPositionOperationContract;
use LBHurtado\Wallet\Treasury\Data\TreasuryPositionReleaseData;
use LBHurtado\Wallet\Treasury\Data\TreasuryPositionReservationData;
use LBHurtado\Wallet\Treasury\Data\TreasuryPositionData;
use LBHurtado\Wallet\Treasury\Enums\TreasuryPositionPurpose;
use LBHurtado\Contact\Models\Contact;
use LBHurtado\EmiCore\Data\Funding\ProviderPayerIdentityData;
use LBHurtado\XChange\Actions\Auth\CreateNewMobileFirstUser;
use LBHurtado\XChange\Actions\Funding\CreateFundingIntent;
use LBHurtado\XChange\Actions\Funding\IssueFundingInstructions;
use LBHurtado\XChange\Contracts\FundingDestinationResolverContract;
use LBHurtado\XChange\Contracts\TreasuryAccountPortfolioProvisioningContract;
use LBHurtado\XChange\Contracts\WalletAccessContract;
use LBHurtado\XChange\Data\Funding\CreateFundingIntentData;
use LBHurtado\XChange\Enums\FundingIntentPurpose;
use LBHurtado\XChange\Enums\FundingIntentStatus;
use LBHurtado\XChange\Enums\PublicAutoRegistrationStatus;
use LBHurtado\XChange\Models\FundingIntent;
use LBHurtado\XChange\Models\PublicAutoRegistration;
use LBHurtado\XChange\Services\PublicIssuance\PublicIssuanceTurnstileVerifier;
use LBHurtado\XChange\Services\Treasury\TreasuryProviderConnectionCatalog;
use LBHurtado\XChange\Support\Auth\MobileNumber;
use Propaganistas\LaravelPhone\PhoneNumber;
use RuntimeException;

final readonly class PublicAutoRegistrationService
{
    public function __construct(
        private WalletAccessContract $wallets,
        private FundingDestinationResolverContract $destinations,
        private CreateFundingIntent $createFundingIntent,
        private IssueFundingInstructions $issueFundingInstructions,
        private CreateNewMobileFirstUser $users,
        private PublicIssuanceTurnstileVerifier $turnstile,
        private TreasuryProviderConnectionCatalog $connections,
        private TreasuryAccountPortfolioProvisioningContract $portfolios,
        private TreasuryPositionOperationContract $operations,
    ) {}

    public function start(Model|Authenticatable $operator, string $idempotencyKey, ?\Illuminate\Http\Request $request = null): PublicAutoRegistration
    {
        $request?->attributes->get('x_change_public_auto_register') === true && $this->turnstile->verify($request, 'public_auto_register');
        $amount = (int) config('x-change.public_auto_register.amount_minor', 10000);
        $currency = strtoupper((string) config('x-change.public_auto_register.currency', 'PHP'));
        $provider = strtolower((string) config('x-change.public_auto_register.provider', 'netbank'));
        $wallet = $this->wallets->resolveForUser($operator);
        $accountReference = $this->accountReference($wallet);
        $hash = hash('sha256', implode("\0", [$operator::class, (string) $operator->getAuthIdentifier(), $idempotencyKey]));

        $existing = PublicAutoRegistration::query()->where('idempotency_key_hash', $hash)->first();
        if ($existing instanceof PublicAutoRegistration) {
            return $existing->load('fundingIntent');
        }

        $intent = $this->createFundingIntent->handle(new CreateFundingIntentData(
            accountReference: $accountReference,
            provider: $provider,
            expectedAmountMinor: $amount,
            currency: $currency,
            idempotencyKey: 'public-auto-register:'.$idempotencyKey,
            actorType: $operator::class,
            actorId: (string) $operator->getAuthIdentifier(),
            expiresAt: new DateTimeImmutable(now()->addSeconds((int) config('x-change.public_auto_register.ttl_seconds', 1800))->toIso8601String()),
            metadata: [
                'source' => 'public.auto-register',
                'payer_identity_match_required' => false,
            ],
            destination: $this->destinations->resolve($operator, $provider, $accountReference),
            purpose: FundingIntentPurpose::AccountFunding,
        ));
        $intent = $this->issueFundingInstructions->handle($intent, $operator::class, (string) $operator->getAuthIdentifier());

        return PublicAutoRegistration::query()->create([
            'funding_intent_id' => $intent->getKey(),
            'status' => PublicAutoRegistrationStatus::AwaitingPayment,
            'amount_minor' => $amount,
            'currency' => $currency,
            'idempotency_key_hash' => $hash,
            'expires_at' => $intent->expires_at,
            'metadata' => ['operator_type' => $operator::class, 'operator_id' => (string) $operator->getAuthIdentifier()],
        ])->load('fundingIntent');
    }

    public function complete(PublicAutoRegistration $registration, string $pin): PublicAutoRegistration
    {
        if (! preg_match('/^[0-9]{4,8}$/', $pin)) {
            throw ValidationException::withMessages(['pin' => 'PIN must contain 4 to 8 digits.']);
        }

        return DB::transaction(function () use ($registration, $pin): PublicAutoRegistration {
            $locked = PublicAutoRegistration::query()->with('fundingIntent.settlement')->lockForUpdate()->findOrFail($registration->getKey());
            $intent = $locked->fundingIntent;
            if (! $intent instanceof FundingIntent || $intent->status !== FundingIntentStatus::Settled) {
                throw ValidationException::withMessages(['payment' => 'Payment has not been verified yet.']);
            }
            if ($locked->status === PublicAutoRegistrationStatus::Active) {
                $fundingOperation = $this->allocateInitialClientFunds($locked, $intent);

                if ($fundingOperation !== null && data_get($locked->metadata, 'initial_client_funding.operation_reference') !== $fundingOperation) {
                    $locked->forceFill([
                        'metadata' => [
                            ...(array) $locked->metadata,
                            'initial_client_funding' => [
                                'amount_minor' => $intent->expected_amount_minor,
                                'currency' => $intent->currency,
                                'operation_reference' => $fundingOperation,
                            ],
                        ],
                    ])->save();
                }

                return $locked->fresh(['fundingIntent']);
            }

            $observation = $intent->settlement?->providerFundingObservation;
            $mobile = MobileNumber::normalize($observation?->payer_mobile_ciphertext);
            if ($mobile === null) {
                $identity = $observation?->payer_identity;
                $mobile = MobileNumber::normalize($identity?->mobile);
            }
            if ($mobile === null) {
                throw ValidationException::withMessages(['payment' => 'The QR payment did not include a usable mobile number.']);
            }
            $name = trim((string) ($observation?->payer_name_ciphertext ?? '')) ?: $mobile;
            $contact = Contact::fromPhoneNumber(new PhoneNumber($mobile, 'PH'));
            $contact->name = $contact->name ?: $name;
            $contact->save();
            $user = $this->users->create([
                'name' => $name,
                'mobile' => $mobile,
                'email' => $this->fallbackEmail($mobile),
                'password' => $pin,
                'password_confirmation' => $pin,
            ]);

            $fundingOperation = $this->allocateInitialClientFunds($locked, $intent, $user);

            $locked->forceFill([
                'contact_type' => $contact::class,
                'contact_id' => (string) $contact->getKey(),
                'user_type' => $user::class,
                'user_id' => (string) $user->getAuthIdentifier(),
                'status' => PublicAutoRegistrationStatus::Active,
                'paid_at' => $intent->settled_at ?? now(),
                'activated_at' => now(),
                'metadata' => [
                    ...(array) $locked->metadata,
                    'payer_mobile' => $mobile,
                    'initial_client_funding' => [
                        'amount_minor' => $intent->expected_amount_minor,
                        'currency' => $intent->currency,
                        'operation_reference' => $fundingOperation,
                    ],
                ],
            ])->save();

            return $locked->fresh(['fundingIntent']);
        });
    }

    /** @return array<string, mixed> */
    public function present(PublicAutoRegistration $registration): array
    {
        $intent = $registration->fundingIntent;
        $status = $intent?->status === FundingIntentStatus::Settled && $registration->status === PublicAutoRegistrationStatus::AwaitingPayment
            ? PublicAutoRegistrationStatus::PaymentSettled
            : $registration->status;
        if ($status !== $registration->status) {
            $registration->forceFill(['status' => $status, 'paid_at' => $intent?->settled_at ?? now()])->saveQuietly();
        }
        return [
            'reference' => $registration->reference,
            'status' => $status->value,
            'amount_minor' => $registration->amount_minor,
            'currency' => $registration->currency,
            'expires_at' => $registration->expires_at?->toIso8601String(),
            'instructions' => $intent?->instructions_ciphertext,
            'account' => $registration->user_id === null ? null : ['id' => $registration->user_id],
        ];
    }

    private function accountReference(mixed $wallet): string
    {
        $uuid = data_get($wallet, 'uuid');
        if (is_string($uuid) && trim($uuid) !== '') {
            return 'wallet:'.trim($uuid);
        }
        if (is_object($wallet) && method_exists($wallet, 'getKey')) {
            return 'wallet:'.$wallet->getKey();
        }
        throw new RuntimeException('Funding account reference could not be resolved.');
    }

    private function fallbackEmail(string $mobile): ?string
    {
        $domain = trim((string) config('x-change.onboarding.email_fallback_domain', ''));

        return $domain === '' ? null : $mobile.'@'.$domain;
    }

    private function allocateInitialClientFunds(
        PublicAutoRegistration $registration,
        FundingIntent $intent,
        ?Model $recipient = null,
    ): ?string {
        $existingReference = data_get($registration->metadata, 'initial_client_funding.operation_reference');

        if (is_string($existingReference) && trim($existingReference) !== '') {
            return $existingReference;
        }

        if (! $recipient instanceof Model) {
            $modelClass = (string) $registration->user_type;
            $recipient = class_exists($modelClass) && is_a($modelClass, Model::class, true)
                ? $modelClass::query()->find($registration->user_id)
                : null;
        }

        if (! $recipient instanceof Model) {
            throw new RuntimeException('The public registration account is unavailable for initial funding.');
        }

        $operator = $this->resolveOperator($intent);
        $connection = $this->connection($intent);
        $source = $this->position(
            $this->portfolios->provision($operator, [$connection->reference])->positions,
            TreasuryPositionPurpose::ClientFunds,
        );
        $reserve = $this->position(
            $this->portfolios->provision($operator, [$connection->reference])->positions,
            TreasuryPositionPurpose::PayCodeReserve,
        );
        $destination = $this->position(
            $this->portfolios->provision($recipient, [$connection->reference])->positions,
            TreasuryPositionPurpose::ClientFunds,
        );
        $scope = hash('sha256', implode('|', [
            'public-auto-registration-initial-funding',
            $registration->reference,
            $intent->reference,
            $recipient->getMorphClass(),
            (string) $recipient->getKey(),
        ]));
        $this->operations->reserve(new TreasuryPositionReservationData(
            operationReference: 'public-auto-registration-funding-reserve:'.$scope,
            sourcePositionReference: $source->positionReference,
            destinationPositionReference: $reserve->positionReference,
            amountMinor: (int) $intent->expected_amount_minor,
            currency: (string) $intent->currency,
            idempotencyKey: 'public-auto-registration-funding-reserve-key:'.$scope,
            externalReference: $intent->reference,
            metadata: [
                'source' => 'x_change_public_auto_registration',
                'registration_reference' => $registration->reference,
                'funding_intent_reference' => $intent->reference,
                'provider_calls' => false,
                'provider_inventory_changed' => false,
            ],
        ));

        $allocation = $this->operations->release(new TreasuryPositionReleaseData(
            operationReference: 'public-auto-registration-funding:'.$scope,
            sourcePositionReference: $reserve->positionReference,
            destinationPositionReference: $destination->positionReference,
            amountMinor: (int) $intent->expected_amount_minor,
            currency: (string) $intent->currency,
            idempotencyKey: 'public-auto-registration-funding-key:'.$scope,
            externalReference: $intent->reference,
            metadata: [
                'source' => 'x_change_public_auto_registration',
                'registration_reference' => $registration->reference,
                'funding_intent_reference' => $intent->reference,
                'provider_calls' => false,
                'provider_inventory_changed' => false,
            ],
        ));

        return $allocation->operationReference;
    }

    private function resolveOperator(FundingIntent $intent): Model
    {
        $class = (string) $intent->created_by_type;
        $operator = class_exists($class) && is_a($class, Model::class, true)
            ? $class::query()->find($intent->created_by_id)
            : null;

        if (! $operator instanceof Model) {
            throw new RuntimeException('The public registration funding operator is unavailable.');
        }

        return $operator;
    }

    private function connection(FundingIntent $intent): \LBHurtado\XChange\Data\Treasury\TreasuryProviderConnectionData
    {
        $configuredReference = trim((string) config('x-change.public_auto_register.treasury_connection_reference', ''));

        if ($configuredReference !== '') {
            $connection = collect($this->connections->active())
                ->firstWhere('reference', $configuredReference);

            if ($connection !== null && $connection->currency === mb_strtoupper((string) $intent->currency)) {
                return $connection;
            }
        }

        $matches = array_values(array_filter(
            $this->connections->active(),
            static fn ($connection): bool => $connection->provider === mb_strtolower((string) $intent->provider_code)
                && $connection->currency === mb_strtoupper((string) $intent->currency),
        ));

        if (count($matches) !== 1) {
            throw new RuntimeException('Exactly one active Treasury connection is required for initial funding.');
        }

        return $matches[0];
    }

    /** @param list<TreasuryPositionData> $positions */
    private function position(array $positions, TreasuryPositionPurpose $purpose): TreasuryPositionData
    {
        $matches = array_values(array_filter(
            $positions,
            static fn (TreasuryPositionData $position): bool => $position->purpose === $purpose
                && $position->status === 'active',
        ));

        if (count($matches) !== 1) {
            throw new RuntimeException("Exactly one active {$purpose->value} Treasury Position is required.");
        }

        return $matches[0];
    }
}
