<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Funding;

use DateTimeImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LBHurtado\EmiCore\Data\Funding\FundingDestinationData;
use LBHurtado\EmiCore\Data\Funding\FundingInstructionRequestData;
use LBHurtado\EmiCore\Data\Funding\FundingInstructionsData;
use LBHurtado\XChange\Contracts\FundingDestinationResolverContract;
use LBHurtado\XChange\Enums\FundingIntentStatus;
use LBHurtado\XChange\Exceptions\FundingIntentTransitionDenied;
use LBHurtado\XChange\Models\FundingIntent;
use LBHurtado\XChange\Services\Funding\FundingProviderAdapterRegistry;
use LBHurtado\XChange\Support\Funding\FundingDestinationSnapshot;
use LogicException;

class IssueFundingInstructions
{
    public function __construct(
        private readonly FundingProviderAdapterRegistry $providers,
        private readonly FundingDestinationResolverContract $destinations,
    ) {}

    public function handle(FundingIntent $intent, string $actorType, string $actorId): FundingIntent
    {
        $actorType = trim($actorType);
        $actorId = trim($actorId);

        if ($actorType === '' || $actorId === '') {
            throw new InvalidArgumentException('Actor identity is required to issue funding instructions.');
        }

        return Cache::lock(
            'x-change:funding-instructions:'.$intent->getKey(),
            (int) config('x-change.funding.instruction_lock_seconds', 30),
        )->block(
            (int) config('x-change.funding.instruction_lock_wait_seconds', 5),
            fn (): FundingIntent => $this->issue($intent, $actorType, $actorId),
        );
    }

    private function issue(FundingIntent $intent, string $actorType, string $actorId): FundingIntent
    {
        $current = FundingIntent::query()->findOrFail($intent->getKey());

        if ($current->status === FundingIntentStatus::AwaitingFunds) {
            return $current->load('events');
        }

        if ($current->status !== FundingIntentStatus::PendingInstructions) {
            throw FundingIntentTransitionDenied::from($current->status, FundingIntentStatus::AwaitingFunds);
        }

        $instructions = $this->providers
            ->for($current->provider_code)
            ->createFundingInstructions(new FundingInstructionRequestData(
                provider: $current->provider_code,
                fundingReference: $current->reference,
                amountMinor: $current->expected_amount_minor,
                currency: $current->currency,
                accountReference: $current->account_reference,
                expiresAt: $current->expires_at === null
                    ? null
                    : DateTimeImmutable::createFromInterface($current->expires_at),
                metadata: [
                    'funding_intent_reference' => $current->reference,
                ],
                destination: $this->destination($current),
            ));

        $this->assertInstructionsMatch($current, $instructions);

        return DB::transaction(function () use ($current, $instructions, $actorType, $actorId): FundingIntent {
            $locked = FundingIntent::query()->lockForUpdate()->findOrFail($current->getKey());

            if ($locked->status === FundingIntentStatus::AwaitingFunds) {
                return $locked->load('events');
            }

            if ($locked->status !== FundingIntentStatus::PendingInstructions) {
                throw FundingIntentTransitionDenied::from($locked->status, FundingIntentStatus::AwaitingFunds);
            }

            $fundingAddress = trim((string) $instructions->fundingAddress);
            $providerReferenceHash = $this->referenceHash($instructions->providerReference);
            $fundingAddressHash = $this->referenceHash($fundingAddress);
            $nextVersion = $locked->version + 1;
            $issuedAt = now();

            $locked->forceFill([
                'status' => FundingIntentStatus::AwaitingFunds,
                'version' => $nextVersion,
                'provider_reference' => 'sha256:'.$providerReferenceHash,
                'provider_request_id' => $instructions->providerReference,
                'funding_address_ciphertext' => $fundingAddress,
                'funding_address_hash' => $fundingAddressHash,
                'instructions_ciphertext' => $this->instructionPayload($instructions),
                'instructions_created_at' => $issuedAt,
                'expires_at' => $instructions->expiresAt ?? $locked->expires_at,
            ])->saveQuietly();

            $locked->events()->create([
                'sequence' => $nextVersion,
                'event_type' => 'provider_instructions_created',
                'from_status' => FundingIntentStatus::PendingInstructions,
                'to_status' => FundingIntentStatus::AwaitingFunds,
                'actor_type' => $actorType,
                'actor_id' => $actorId,
                'evidence_reference' => null,
                'metadata' => [
                    'provider' => $instructions->provider,
                    'provider_reference_hash' => $providerReferenceHash,
                    'expires_at' => $instructions->expiresAt?->format(DATE_ATOM),
                ],
                'occurred_at' => $issuedAt,
            ]);

            return $locked->refresh()->load('events');
        }, 3);
    }

    private function assertInstructionsMatch(
        FundingIntent $intent,
        FundingInstructionsData $instructions,
    ): void {
        if (strtolower(trim($instructions->provider)) !== $intent->provider_code
            || $instructions->amountMinor !== $intent->expected_amount_minor
            || strtoupper(trim($instructions->currency)) !== $intent->currency) {
            throw new InvalidArgumentException('Provider funding instructions do not match the Funding Intent.');
        }

        if (trim($instructions->providerReference) === '' || trim((string) $instructions->fundingAddress) === '') {
            throw new InvalidArgumentException('Provider funding instructions require a reference and funding address.');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function instructionPayload(FundingInstructionsData $instructions): array
    {
        return [
            'provider' => $instructions->provider,
            'amount_minor' => $instructions->amountMinor,
            'currency' => $instructions->currency,
            'expires_at' => $instructions->expiresAt?->format(DATE_ATOM),
            'funding_address' => $instructions->fundingAddress,
            'action_url' => $instructions->actionUrl,
            'display_data' => $instructions->displayData,
            'qr_code' => $instructions->qrCode === null
                ? null
                : [
                    'mime_type' => $instructions->qrCode->mimeType,
                    'base64_payload' => $instructions->qrCode->base64Payload,
                    'qr_mode' => $instructions->qrCode->qrMode,
                    'transaction_type' => $instructions->qrCode->transactionType,
                    'embedded_amount' => $instructions->qrCode->embeddedAmount,
                    'provider_generated' => $instructions->qrCode->providerGenerated,
                ],
        ];
    }

    private function referenceHash(string $value): string
    {
        $key = config('x-change.funding.reference_hash_key') ?: config('app.key');

        if (! is_string($key) || trim($key) === '') {
            throw new LogicException('A Funding reference hash key must be configured.');
        }

        return hash_hmac('sha256', $value, $key);
    }

    private function destination(FundingIntent $intent): FundingDestinationData
    {
        $snapshot = $intent->destination_snapshot_ciphertext;

        return is_array($snapshot)
            ? FundingDestinationSnapshot::toData($snapshot)
            : $this->destinations->shared($intent->provider_code, $intent->account_reference);
    }
}
