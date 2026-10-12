<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Payment;

use Illuminate\Support\Facades\Cache;
use LBHurtado\EmiCore\Actions\Funding\RecordProviderFundingObservation;
use LBHurtado\EmiCore\Data\Funding\FundingVerificationData;
use LBHurtado\EmiCore\Models\ProviderFundingObservation;
use LBHurtado\PaymentGateway\Funding\NetbankFundingProviderAdapter;
use LBHurtado\XChange\Actions\Payment\Pipes\AssociateSettledPayerContact;
use LBHurtado\XChange\Actions\Payment\Pipes\QueueSettledPaymentSms;
use LBHurtado\XChange\Models\FundingSettlement;
use LBHurtado\XChange\Models\SettledPaymentProcess;
use LBHurtado\XChange\Services\Settlement\CampaignWalletPayerMobile;
use LBHurtado\XChange\Support\Funding\FundingDestinationSnapshot;

final readonly class RecoverSettledPayerIdentity
{
    public function __construct(
        private NetbankFundingProviderAdapter $provider,
        private RecordProviderFundingObservation $observations,
        private CampaignWalletPayerMobile $walletPayerMobile,
        private AssociateSettledPayerContact $contacts,
        private QueueSettledPaymentSms $sms,
    ) {}

    public function handle(int $processId): bool
    {
        return Cache::lock("x-change:settled-payer-recovery:{$processId}", 120)->block(
            5,
            fn (): bool => $this->recover($processId),
        );
    }

    private function recover(int $processId): bool
    {
        $process = SettledPaymentProcess::query()->find($processId);

        if ($process === null || ! $process->sms_enabled_snapshot
            || ! in_array($process->sms_status, ['skipped', null], true)) {
            return true;
        }

        if ($process->payment_type !== 'funding_settlement') {
            return true;
        }

        if ($process->contact_id !== null) {
            $this->sms->handle($process, static fn (SettledPaymentProcess $process): null => null);

            return true;
        }

        $original = ProviderFundingObservation::query()->findOrFail($process->observation_id);
        $settlement = FundingSettlement::query()->with('fundingIntent')->findOrFail($process->payment_id);
        $intent = $settlement->fundingIntent;
        $address = trim((string) $intent?->funding_address_ciphertext);

        if ($original->provider_code !== 'netbank' || $intent?->provider_code !== 'netbank'
            || $address === '' || ! is_array($intent->destination_snapshot_ciphertext)) {
            return true;
        }

        $fresh = $this->provider->verifyFunding(new FundingVerificationData(
            provider: 'netbank',
            fundingIntentReference: $intent->reference,
            expectedAmountMinor: (int) $intent->expected_amount_minor,
            currency: $intent->currency,
            providerRequestId: $intent->provider_request_id,
            fundingAddress: $address,
            destination: FundingDestinationSnapshot::toData($intent->destination_snapshot_ciphertext),
        ));

        if ($fresh->provider !== 'netbank'
            || $fresh->providerTransactionId !== $original->provider_transaction_id
            || $fresh->fundingAddress !== $original->funding_address
            || $fresh->providerAccountReference !== $original->provider_account_reference
            || $fresh->grossAmountMinor !== $original->gross_amount_minor
            || $fresh->feeAmountMinor !== $original->fee_amount_minor
            || $fresh->netAmountMinor !== $original->net_amount_minor
            || $fresh->currency !== $original->currency
            || $fresh->providerStatus !== 'settled'
            || $fresh->settledAt === null
            || data_get($fresh->metadata, 'destination_verified') !== true) {
            return true;
        }

        $identity = $fresh->payerIdentity;
        if ($identity === null) {
            return false;
        }

        $institution = strtoupper(trim((string) $identity->institutionCode));
        $account = trim((string) $identity->accountNumber);
        if (! in_array($institution, ['GXCHPHM2XXX', 'PAPHPHM1XXX', 'GCASH', 'MAYA', 'PAYMAYA'], true)
            || preg_match('/^(?:0|63|\+63)9[0-9]{9}$/', $account) !== 1) {
            return false;
        }

        $fresh->metadata = [
            ...$fresh->metadata,
            'normalization_version' => 'netbank-incoming-credit-v2-payer-recovery-v1',
        ];
        $observation = $this->observations->handle($fresh);
        if ($this->walletPayerMobile->fromObservation($observation) === null) {
            return false;
        }

        $process->forceFill(['observation_id' => $observation->getKey()])->save();
        $this->contacts->handle($process, static fn (SettledPaymentProcess $process): null => null);
        $this->sms->handle($process, static fn (SettledPaymentProcess $process): null => null);

        return $process->sms_status !== 'skipped';
    }
}
