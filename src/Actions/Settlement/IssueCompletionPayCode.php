<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Settlement;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LBHurtado\SettlementEnvelope\Models\Envelope;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Contracts\AuditLoggerContract;
use LBHurtado\XChange\Contracts\PayCodeIssuanceContract;
use LBHurtado\XChange\Data\Settlement\CompletionPayCodeInstructionsData;
use LBHurtado\XChange\Data\Settlement\CompletionPayCodeIssuanceData;
use LBHurtado\XChange\Enums\ProvisionalCoverageStatus;
use LBHurtado\XChange\Events\CompletionPayCodeIssued;
use LBHurtado\XChange\Models\CompletionPayCodeIssuance;
use LBHurtado\XChange\Models\ProvisionalCoverage;
use LBHurtado\XChange\Services\Execution\CampaignCoverageCompletionExecutionDriver;
use LBHurtado\XChange\Services\Settlement\CampaignWalletPayerMobile;

final readonly class IssueCompletionPayCode
{
    public function __construct(private PayCodeIssuanceContract $issuance, private AuditLoggerContract $audit) {}

    public function handle(ProvisionalCoverage $coverage, Authenticatable $issuer, CompletionPayCodeInstructionsData $instructions): CompletionPayCodeIssuanceData
    {
        $this->assertValid($coverage, $issuer);
        $snapshot = $instructions->canonical();
        $instructionHash = $this->hash($snapshot);
        $issuerType = $issuer instanceof Model ? $issuer->getMorphClass() : $issuer::class;
        $issuerId = (string) $issuer->getAuthIdentifier();
        $key = $this->hash(['coverage' => $coverage->reference, 'envelope' => $coverage->envelope_id, 'issuer_type' => $issuerType, 'issuer_id' => $issuerId]);

        $result = DB::transaction(function () use ($coverage, $issuer, $snapshot, $instructionHash, $issuerType, $issuerId, $key): array {
            $locked = ProvisionalCoverage::query()->with(['envelope', 'recognition.binding.standingFundingAddress', 'recognition.source.campaign.owner'])->lockForUpdate()->findOrFail($coverage->getKey());
            $existing = CompletionPayCodeIssuance::query()->with('voucher')->where('provisional_coverage_id', $locked->getKey())->first();

            if ($existing instanceof CompletionPayCodeIssuance) {
                if (! hash_equals($existing->issuance_key, $key) || ! hash_equals($existing->instruction_hash, $instructionHash)) {
                    throw new InvalidArgumentException('Existing completion Pay Code does not match the requested issuance.');
                }

                return ['issuance' => $existing, 'voucher' => $existing->voucher, 'created' => false];
            }

            $issued = $this->issuance->issue($issuer, $this->payload($locked, $snapshot));
            $voucher = Voucher::query()->findOrFail($issued['voucher_id']);
            $link = CompletionPayCodeIssuance::query()->create([
                'issuance_key' => $key, 'instruction_hash' => $instructionHash,
                'provisional_coverage_id' => $locked->getKey(), 'envelope_id' => $locked->envelope_id,
                'voucher_id' => $voucher->getKey(), 'issuer_type' => $issuerType, 'issuer_id' => $issuerId,
                'driver_id' => $locked->driver_id, 'driver_version' => $locked->driver_version,
                'requirements_snapshot' => $snapshot, 'issued_at' => now(),
            ]);

            return ['issuance' => $link, 'voucher' => $voucher, 'created' => true];
        }, attempts: 5);

        if ($result['created']) {
            $payload = ['schema' => 'x-change.completion-pay-code-issued.v1', 'issuance_reference' => $result['issuance']->reference, 'coverage_reference' => $coverage->reference, 'envelope_reference' => $coverage->envelope->reference_code, 'voucher_code' => $result['voucher']->code, 'driver_id' => $coverage->driver_id, 'driver_version' => $coverage->driver_version];
            $this->audit->log('campaign.completion_pay_code.issued', $payload + ['financial_side_effects' => false]);
            CompletionPayCodeIssued::dispatch($payload);
        }

        return new CompletionPayCodeIssuanceData($result['issuance'], $result['voucher'], $result['created']);
    }

    private function assertValid(ProvisionalCoverage $coverage, Authenticatable $issuer): void
    {
        if (! $coverage->exists || $coverage->status !== ProvisionalCoverageStatus::Provisional) {
            throw new InvalidArgumentException('Persisted provisional coverage is required.');
        }
        $coverage->loadMissing(['envelope', 'recognition.binding.standingFundingAddress', 'recognition.source.campaign.owner']);
        $owner = $coverage->recognition->ownerRecord();
        $issuerType = $issuer instanceof Model ? $issuer->getMorphClass() : $issuer::class;
        if ($owner->getMorphClass() !== $issuerType || (string) $owner->getKey() !== (string) $issuer->getAuthIdentifier()) {
            throw new InvalidArgumentException('Completion Pay Code issuer must own the campaign payment source.');
        }
        if (! $coverage->envelope instanceof Envelope || $coverage->envelope->driver_id !== $coverage->driver_id || $coverage->envelope->driver_version !== $coverage->driver_version) {
            throw new InvalidArgumentException('Coverage and envelope driver identities must match.');
        }
        if (data_get($coverage->envelope->payload, 'coverage.coverage_reference') !== $coverage->reference) {
            throw new InvalidArgumentException('Settlement envelope does not contain the provisional coverage reference.');
        }
    }

    private function payload(ProvisionalCoverage $coverage, array $snapshot): array
    {
        $validation = ['country' => 'PH'];
        $mobile = (new CampaignWalletPayerMobile)->resolve($coverage->recognition);
        if ($mobile !== null) {
            $validation['mobile'] = $mobile;
        }
        $fields = $snapshot['applicant_fields'];
        if ($snapshot['requires_otp']) {
            $fields[] = 'otp';
        }

        return [
            'cash' => ['amount' => 0, 'currency' => $coverage->currency, 'validation' => $validation],
            'inputs' => ['fields' => $fields], 'feedback' => [], 'count' => 1,
            'prefix' => $snapshot['prefix'], 'mask' => $snapshot['mask'], 'voucher_type' => 'redeemable',
            'rider' => ['message' => $snapshot['message']],
            'execution' => ['schema' => 'voucher.execution.v1', 'driver' => CampaignCoverageCompletionExecutionDriver::Key, 'metadata' => [
                'completion' => ['schema' => 'x-change.campaign-coverage-completion.v1', 'coverage_reference' => $coverage->reference, 'envelope_reference' => $coverage->envelope->reference_code, 'driver_id' => $coverage->driver_id, 'driver_version' => $coverage->driver_version],
                'post_redemption' => ['mode' => CampaignCoverageCompletionExecutionDriver::PostRedemptionMode],
            ]],
            'claim' => [
                'outcomes' => [['key' => 'envelope_completion']],
                'selection' => 'server',
                'default_outcome' => 'envelope_completion',
            ],
            'metadata' => ['flow_type' => 'disbursable', 'custom' => ['completion' => ['schema' => 'x-change.campaign-coverage-completion.v1', 'coverage_reference' => $coverage->reference, 'envelope_reference' => $coverage->envelope->reference_code]]],
        ];
    }

    private function hash(array $value): string
    {
        return hash('sha256', json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    }
}
