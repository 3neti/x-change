<?php

declare(strict_types=1);

use LBHurtado\XChange\Actions\Commercial\PreparePartnerCommissionPayoutRetry;
use LBHurtado\XChange\Enums\CommercialOperatorCapability;
use LBHurtado\XChange\Enums\CommercialPartnerRevisionStatus;
use LBHurtado\XChange\Enums\CommercialPartnerStatus;
use LBHurtado\XChange\Enums\PartnerCommissionPayoutAttemptStatus;
use LBHurtado\XChange\Enums\PartnerCommissionPayoutBatchStatus;
use LBHurtado\XChange\Models\CommercialOperatorAuthorization;
use LBHurtado\XChange\Models\CommercialPartner;
use LBHurtado\XChange\Models\CommercialPartnerDestinationRevision;
use LBHurtado\XChange\Models\CommercialPartnerRevision;
use LBHurtado\XChange\Models\PartnerCommissionPayoutAttempt;
use LBHurtado\XChange\Models\PartnerCommissionPayoutBatch;
use LBHurtado\XChange\Tests\Fakes\User;
use LBHurtado\XJournal\Models\ExecutionJournalEntry;

it('preserves a rejected attempt and prepares the same batch against a newly approved destination', function (): void {
    $system = actingAsTestUser();
    config()->set('account.system_user.candidates', [
        'x-change' => [
            'model' => User::class,
            'identifier' => $system->email,
            'identifier_column' => 'email',
        ],
    ]);
    $operator = actingAsTestUser();
    CommercialOperatorAuthorization::query()->create([
        'operator_type' => $operator->getMorphClass(),
        'operator_id' => $operator->getKey(),
        'capability' => CommercialOperatorCapability::ExecuteCommissionPayouts->value,
        'authorization_reference' => 'board:commission-retry',
        'valid_from' => now()->subMinute(),
    ]);
    $partner = CommercialPartner::query()->create([
        'reference' => 'partner:retry',
        'display_name' => 'Retry Partner',
        'status' => CommercialPartnerStatus::Active,
        'created_by_type' => $operator->getMorphClass(),
        'created_by_id' => $operator->getKey(),
        'activated_at' => now(),
    ]);
    $partnerRevision = CommercialPartnerRevision::query()->create([
        'commercial_partner_id' => $partner->getKey(),
        'version' => 1,
        'status' => CommercialPartnerRevisionStatus::Approved,
        'display_name' => 'Retry Partner',
        'attribution_basis' => 'contractual_referral',
        'authorization_reference' => 'contract:retry',
        'terms' => [],
        'snapshot_hash' => str_repeat('f', 64),
        'maker_type' => $operator->getMorphClass(),
        'maker_id' => $operator->getKey(),
        'approved_at' => now(),
        'effective_at' => now(),
    ]);
    $destinations = collect([1, 2])->map(function (int $version) use ($operator, $partner, $partnerRevision): CommercialPartnerDestinationRevision {
        return CommercialPartnerDestinationRevision::query()->create([
            'commercial_partner_id' => $partner->getKey(),
            'commercial_partner_revision_id' => $partnerRevision->getKey(),
            'version' => $version,
            'status' => CommercialPartnerRevisionStatus::Approved,
            'provider' => 'netbank',
            'connection_reference' => 'netbank-primary',
            'currency' => 'PHP',
            'destination' => [
                'bank_code' => 'GXCHPHM2XXX',
                'account_number' => '0917123456'.$version,
                'recipient_name' => 'Retry Partner',
                'mobile' => '0917123456'.$version,
            ],
            'destination_hash' => str_repeat((string) $version, 64),
            'destination_summary' => 'GCash · ••••3456'.$version,
            'maker_type' => $operator->getMorphClass(),
            'maker_id' => $operator->getKey(),
            'authorization_reference' => 'board:retry-destination:'.$version,
            'approved_at' => now(),
            'effective_at' => now(),
        ]);
    });
    $batch = PartnerCommissionPayoutBatch::query()->create([
        'reference' => 'commission-payout:retry',
        'partner_reference' => $partner->reference,
        'commercial_partner_id' => $partner->getKey(),
        'commercial_partner_revision_id' => $partnerRevision->getKey(),
        'commercial_partner_destination_revision_id' => $destinations[0]->getKey(),
        'provider' => 'netbank',
        'connection_reference' => 'netbank-primary',
        'position_reference' => 'position:partner:retry',
        'amount_minor' => 500,
        'currency' => 'PHP',
        'status' => PartnerCommissionPayoutBatchStatus::Rejected,
        'destination' => $destinations[0]->destination,
        'destination_hash' => $destinations[0]->destination_hash,
        'destination_summary' => $destinations[0]->destination_summary,
        'request_idempotency_key' => 'commission-payout:retry',
        'request_hash' => str_repeat('a', 64),
        'submission_idempotency_key' => 'commission-payout:retry:attempt:1',
        'provider_transaction_id' => 'NETBANK-REJECTED-001',
        'metadata' => [],
        'period_started_at' => now()->subMonth(),
        'period_ended_at' => now(),
        'requested_at' => now()->subDay(),
        'approved_at' => now()->subDay(),
        'submitted_at' => now()->subHour(),
        'rejected_at' => now(),
    ]);
    $attempt = PartnerCommissionPayoutAttempt::query()->create([
        'batch_id' => $batch->getKey(),
        'commercial_partner_destination_revision_id' => $destinations[0]->getKey(),
        'attempt_number' => 1,
        'status' => PartnerCommissionPayoutAttemptStatus::Rejected,
        'submission_idempotency_key' => 'commission-payout:retry:attempt:1',
        'provider_transaction_id' => 'NETBANK-REJECTED-001',
        'rejection_code' => 'AC01',
        'rejection_message' => 'Incorrect account number',
        'metadata' => [],
        'submitted_at' => now()->subHour(),
        'reconciled_at' => now(),
    ]);

    $prepared = app(PreparePartnerCommissionPayoutRetry::class)->execute($operator, $batch, $destinations[1]);

    expect($prepared->status)->toBe(PartnerCommissionPayoutBatchStatus::Approved)
        ->and($prepared->commercial_partner_destination_revision_id)->toBe($destinations[1]->getKey())
        ->and($prepared->submission_idempotency_key)->toBeNull()
        ->and($prepared->provider_transaction_id)->toBeNull()
        ->and($attempt->fresh()->status)->toBe(PartnerCommissionPayoutAttemptStatus::Rejected)
        ->and($attempt->fresh()->rejection_code)->toBe('AC01')
        ->and(ExecutionJournalEntry::query()
            ->where('event_type', 'commercial.partner_commission_batch.retry_prepared')->exists())->toBeTrue();
});
