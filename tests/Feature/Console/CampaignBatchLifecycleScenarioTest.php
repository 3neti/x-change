<?php

declare(strict_types=1);

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryInventoryOperationContract;
use LBHurtado\Wallet\Treasury\Data\TreasuryInventoryData;
use LBHurtado\Wallet\Treasury\Data\TreasuryInventoryRecognitionData;
use LBHurtado\XCampaign\Models\CampaignWorksheet;
use LBHurtado\XChange\Actions\Campaigns\ApproveCampaignWorksheetAuthorization;
use LBHurtado\XChange\Contracts\TreasuryAccountPortfolioProvisioningContract;
use LBHurtado\XChange\Contracts\VerifiedTreasuryFundingAllocationContract;
use LBHurtado\XChange\Jobs\Campaigns\DispatchCampaignFeedbackJob;
use LBHurtado\XChange\Lifecycle\Scenarios\LifecycleScenarioEngine;
use LBHurtado\XChange\Lifecycle\Scenarios\LifecycleScenarioRunOptions;
use LBHurtado\XChange\Models\CampaignBatchFulfillmentOutbox;
use LBHurtado\XChange\Models\DisbursementReconciliation;
use LBHurtado\XChange\Tests\Fakes\User;

function campaignBatchLifecycleCommand(): Command
{
    return new class extends Command
    {
        public function option($key = null): mixed
        {
            return false;
        }

        public function info($string, $verbosity = null): void {}

        public function line($string, $style = null, $verbosity = null): void {}
    };
}

function campaignBatchLifecycleIssuer(): User
{
    config()->set('x-change.lifecycle.defaults.user_model', User::class);

    $issuer = actingAsTestUser();
    $issuer->setMobileChannel('09170000001');
    $issuer->save();

    return $issuer;
}

function campaignBatchCsv(string $contents): string
{
    $path = tempnam(sys_get_temp_dir(), 'campaign-batch-');
    expect($path)->toBeString();
    $csv = $path.'.csv';
    rename($path, $csv);
    file_put_contents($csv, $contents);

    return $csv;
}

function fundCampaignBatchClientFunds(User $owner, int $amountMinor): void
{
    enableNetbankTreasuryForTests();
    $inventory = app(TreasuryInventoryOperationContract::class);
    $inventory->registerInventory(new TreasuryInventoryData(
        inventoryReference: 'inventory:netbank:vca-cash',
        resourceType: 'cash_at_bank',
        currency: 'PHP',
        capacityMinor: 0,
        status: 'requested',
        idempotencyKey: 'campaign-batch:inventory:register',
        externalReference: 'resource:netbank:corporate-vca',
    ));
    $inventory->recognize(new TreasuryInventoryRecognitionData(
        operationReference: 'campaign-batch:inventory:recognize:'.str()->uuid(),
        inventoryReference: 'inventory:netbank:vca-cash',
        settlementResourceReference: 'resource:netbank:corporate-vca',
        amountMinor: $amountMinor,
        currency: 'PHP',
        status: 'requested',
        idempotencyKey: 'campaign-batch:inventory:recognize-key:'.str()->uuid(),
        externalReference: 'campaign-batch:funding:'.str()->uuid(),
    ));
    app(TreasuryAccountPortfolioProvisioningContract::class)->provision($owner, ['netbank-primary']);
    app(VerifiedTreasuryFundingAllocationContract::class)->allocate(
        accountReference: 'wallet:'.$owner->wallet->uuid,
        provider: 'netbank',
        amountMinor: $amountMinor,
        currency: 'PHP',
        evidenceReference: 'campaign-batch:'.str()->uuid(),
    );
}

it('ingests a payroll CSV into encrypted campaign rows and stops for an independent checker', function () {
    $issuer = campaignBatchLifecycleIssuer();
    $input = campaignBatchCsv(implode("\n", [
        'name,mobile,amount',
        'Payroll Recipient One,09170000011,25.00',
        'Payroll Recipient Two,09170000022,30.00',
    ]));

    $result = app(LifecycleScenarioEngine::class)->run(
        command: campaignBatchLifecycleCommand(),
        scenarioKey: 'campaign_payroll_pay_code_sms',
        options: new LifecycleScenarioRunOptions(
            issuer: (string) $issuer->getKey(),
            runReference: 'PAYROLL-TEST-001',
            input: $input,
            json: true,
        ),
    );

    expect($result->exitCode)->toBe(Command::SUCCESS)
        ->and($result->payload)->toMatchArray([
            'success' => true,
            'phase' => 'awaiting_checker',
            'beneficiary_count' => 2,
            'principal_minor' => 5_500,
            'currency' => 'PHP',
            'fulfillment_mode' => 'pay_code_distribution',
            'provider_calls' => 0,
            'money_moved' => false,
        ])
        ->and($result->payload['approval_pay_code'])->toBeString()->not->toBeEmpty();

    $worksheet = CampaignWorksheet::query()->with(['rows', 'authorizations'])->sole();
    expect($worksheet->owner_id)->toBe((string) $issuer->getKey())
        ->and(data_get($worksheet->metadata, 'lifecycle.run_reference'))->toBe('PAYROLL-TEST-001')
        ->and(data_get($worksheet->metadata, 'lifecycle.content_hash'))->toBe(hash_file('sha256', $input))
        ->and($worksheet->rows)->toHaveCount(2)
        ->and($worksheet->rows[0]->beneficiary_ciphertext['mobile'])->toBe('09170000011')
        ->and($worksheet->authorizations->sole()->status)->toBe('awaiting_officer');

    $rawBeneficiary = DB::table('campaign_worksheet_rows')->value('beneficiary_ciphertext');
    expect($rawBeneficiary)->toBeString()
        ->not->toContain('09170000011')
        ->not->toContain('Payroll Recipient One')
        ->and(Voucher::query()->where('code', $result->payload['approval_pay_code'])->exists())->toBeTrue();
});

it('replays the same lifecycle run without duplicating its worksheet or approval Pay Code', function () {
    $issuer = campaignBatchLifecycleIssuer();
    $input = campaignBatchCsv("name,mobile,amount\nPayroll Recipient,09170000011,25.00\n");
    $options = new LifecycleScenarioRunOptions(
        issuer: (string) $issuer->getKey(),
        runReference: 'PAYROLL-REPLAY-001',
        input: $input,
        json: true,
    );

    $first = app(LifecycleScenarioEngine::class)->run(
        campaignBatchLifecycleCommand(),
        'campaign_payroll_pay_code_sms',
        $options,
    );
    $second = app(LifecycleScenarioEngine::class)->run(
        campaignBatchLifecycleCommand(),
        'campaign_payroll_pay_code_sms',
        $options,
    );

    expect($second->exitCode)->toBe(Command::SUCCESS)
        ->and($second->payload['worksheet_reference'])->toBe($first->payload['worksheet_reference'])
        ->and($second->payload['approval_pay_code'])->toBe($first->payload['approval_pay_code'])
        ->and(CampaignWorksheet::query()->count())->toBe(1)
        ->and(CampaignWorksheet::query()->sole()->rows()->count())->toBe(1);
});

it('rejects a changed input file under the same lifecycle run reference', function () {
    $issuer = campaignBatchLifecycleIssuer();
    $firstInput = campaignBatchCsv("name,mobile,amount\nFirst Recipient,09170000011,25.00\n");
    $changedInput = campaignBatchCsv("name,mobile,amount\nChanged Recipient,09170000022,30.00\n");

    $first = app(LifecycleScenarioEngine::class)->run(
        campaignBatchLifecycleCommand(),
        'campaign_payroll_pay_code_sms',
        new LifecycleScenarioRunOptions(
            issuer: (string) $issuer->getKey(),
            runReference: 'PAYROLL-CONFLICT-001',
            input: $firstInput,
            json: true,
        ),
    );
    $changed = app(LifecycleScenarioEngine::class)->run(
        campaignBatchLifecycleCommand(),
        'campaign_payroll_pay_code_sms',
        new LifecycleScenarioRunOptions(
            issuer: (string) $issuer->getKey(),
            runReference: 'PAYROLL-CONFLICT-001',
            input: $changedInput,
            json: true,
        ),
    );

    expect($first->exitCode)->toBe(Command::SUCCESS)
        ->and($changed->exitCode)->toBe(Command::FAILURE)
        ->and($changed->payload['message'])->toContain('different campaign input file')
        ->and(CampaignWorksheet::query()->count())->toBe(1)
        ->and(CampaignWorksheet::query()->sole()->rows()->count())->toBe(1);
});

it('requires an explicit feedback gate after checker approval before issuing Pay Codes and queueing SMS', function () {
    Queue::fake([DispatchCampaignFeedbackJob::class]);
    config()->set('x-change.campaigns.delivery.sms.enabled', true);
    config()->set('x-change.redemption.feedback.queue', 'x-change-feedback');
    $issuer = campaignBatchLifecycleIssuer();
    fundCampaignBatchClientFunds($issuer, 10_000);
    $input = campaignBatchCsv("name,mobile,amount\nPayroll Recipient,09170000011,25.00\n");
    $baseOptions = [
        'issuer' => (string) $issuer->getKey(),
        'runReference' => 'PAYROLL-SMS-APPROVAL-001',
        'input' => $input,
        'json' => true,
    ];

    $prepared = app(LifecycleScenarioEngine::class)->run(
        campaignBatchLifecycleCommand(),
        'campaign_payroll_pay_code_sms',
        new LifecycleScenarioRunOptions(...$baseOptions),
    );
    $checker = actingAsTestUser();
    app(ApproveCampaignWorksheetAuthorization::class)->handle(
        $prepared->payload['approval_pay_code'],
        $checker,
    );

    $gated = app(LifecycleScenarioEngine::class)->run(
        campaignBatchLifecycleCommand(),
        'campaign_payroll_pay_code_sms',
        new LifecycleScenarioRunOptions(...$baseOptions),
    );
    expect($gated->payload['phase'])->toBe('authorized_waiting_feedback_gate')
        ->and(CampaignWorksheet::query()->sole()->authorizations()->sole()->fulfillments()->whereNotNull('pay_code')->count())->toBe(0);

    $resumed = app(LifecycleScenarioEngine::class)->run(
        campaignBatchLifecycleCommand(),
        'campaign_payroll_pay_code_sms',
        new LifecycleScenarioRunOptions(...$baseOptions, liveFeedback: true),
    );

    $authorization = CampaignWorksheet::query()->sole()->authorizations()->sole();
    expect($resumed->exitCode)->toBe(Command::SUCCESS)
        ->and($resumed->payload['phase'])->toBe('fulfillment_queued')
        ->and($authorization->fulfillments()->whereNotNull('pay_code')->count())->toBe(1);

    Queue::assertPushedOn(
        'x-change-feedback',
        DispatchCampaignFeedbackJob::class,
        fn (DispatchCampaignFeedbackJob $job): bool => $job->recipient === '09170000011',
    );
});

it('queues an authorized SMS batch through the durable fulfillment outbox', function () {
    Queue::fake([DispatchCampaignFeedbackJob::class]);
    config()->set('x-change.campaigns.delivery.sms.enabled', true);
    config()->set('x-change.redemption.feedback.queue', 'x-change-feedback');
    $issuer = campaignBatchLifecycleIssuer();
    fundCampaignBatchClientFunds($issuer, 10_000);
    $input = campaignBatchCsv("name,mobile,amount\nPayroll Recipient,09170000011,25.00\n");
    $prepared = app(LifecycleScenarioEngine::class)->run(
        campaignBatchLifecycleCommand(),
        'campaign_payroll_pay_code_sms',
        new LifecycleScenarioRunOptions(
            issuer: (string) $issuer->getKey(),
            runReference: 'PAYROLL-SMS-OUTBOX-001',
            input: $input,
            liveFeedback: true,
            json: true,
        ),
    );

    $checker = actingAsTestUser();
    app(ApproveCampaignWorksheetAuthorization::class)->handle(
        $prepared->payload['approval_pay_code'],
        $checker,
    );

    expect(CampaignBatchFulfillmentOutbox::query()->sole()->status)->toBe('pending')
        ->and(Artisan::call('x-change:campaigns:process-batches'))->toBe(Command::SUCCESS)
        ->and(CampaignBatchFulfillmentOutbox::query()->sole()->status)->toBe('completed')
        ->and(CampaignWorksheet::query()->sole()->authorizations()->sole()->fulfillments()->whereNotNull('pay_code')->count())->toBe(1);
    Queue::assertPushedOn(
        'x-change-feedback',
        DispatchCampaignFeedbackJob::class,
        fn (DispatchCampaignFeedbackJob $job): bool => $job->recipient === '09170000011',
    );
});

it('rejects invalid direct-transfer input before creating a campaign or calling a provider', function () {
    $issuer = campaignBatchLifecycleIssuer();
    $input = campaignBatchCsv("name,mobile,amount\nMissing Destination,,25.00\n");

    config()->set('x-change.provider_runtime.lifecycle.allow_live_provider_scenarios', true);

    $result = app(LifecycleScenarioEngine::class)->run(
        command: campaignBatchLifecycleCommand(),
        scenarioKey: 'campaign_payroll_direct_transfer',
        options: new LifecycleScenarioRunOptions(
            issuer: (string) $issuer->getKey(),
            runReference: 'PAYROLL-DIRECT-INVALID',
            input: $input,
            liveProvider: true,
            confirmLiveTransfer: true,
            json: true,
        ),
    );

    expect($result->exitCode)->toBe(Command::FAILURE)
        ->and($result->payload['message'])->toContain('row 2 is invalid')
        ->and(CampaignWorksheet::query()->count())->toBe(0);
});

it('refuses a direct-transfer batch unless both live money gates are explicit', function () {
    $issuer = campaignBatchLifecycleIssuer();
    $input = campaignBatchCsv("name,mobile,bank,account number,amount\nRecipient,09170000011,GCash,09170000011,25.00\n");

    $result = app(LifecycleScenarioEngine::class)->run(
        command: campaignBatchLifecycleCommand(),
        scenarioKey: 'campaign_payroll_direct_transfer',
        options: new LifecycleScenarioRunOptions(
            issuer: (string) $issuer->getKey(),
            runReference: 'PAYROLL-DIRECT-GATED',
            input: $input,
            json: true,
        ),
    );

    expect($result->exitCode)->toBe(Command::FAILURE)
        ->and($result->payload['message'])->toContain('--live-provider')
        ->and(CampaignWorksheet::query()->count())->toBe(0);
});

it('executes an approved direct-transfer batch once through Treasury-backed Pay Codes and the voucher engine', function () {
    config()->set('x-change.provider_runtime.lifecycle.allow_live_provider_scenarios', true);
    $provider = fakePayoutProvider()->willReturnSuccessfulResult(
        transactionId: 'TXN-CAMPAIGN-DIRECT-001',
        uuid: 'uuid-campaign-direct-001',
        provider: 'netbank',
    );
    $issuer = campaignBatchLifecycleIssuer();
    fundCampaignBatchClientFunds($issuer, 10_000);
    $input = campaignBatchCsv("name,mobile,bank,account number,amount\nPayroll Recipient,09170000011,GCash,09170000011,25.00\n");
    $options = new LifecycleScenarioRunOptions(
        issuer: (string) $issuer->getKey(),
        runReference: 'PAYROLL-DIRECT-LIVE-001',
        input: $input,
        liveProvider: true,
        confirmLiveTransfer: true,
        json: true,
    );

    $prepared = app(LifecycleScenarioEngine::class)->run(
        campaignBatchLifecycleCommand(),
        'campaign_payroll_direct_transfer',
        $options,
    );
    expect($prepared->payload['phase'])->toBe('awaiting_checker');

    $checker = actingAsTestUser();
    app(ApproveCampaignWorksheetAuthorization::class)->handle(
        $prepared->payload['approval_pay_code'],
        $checker,
    );
    expect(CampaignBatchFulfillmentOutbox::query()->sole()->status)->toBe('pending');
    expect(Artisan::call('x-change:campaigns:process-batches', ['--limit' => 10]))
        ->toBe(Command::SUCCESS);
    $fulfilled = app(LifecycleScenarioEngine::class)->run(
        campaignBatchLifecycleCommand(),
        'campaign_payroll_direct_transfer',
        $options,
    );

    $fulfillment = CampaignWorksheet::query()
        ->sole()
        ->authorizations()
        ->sole()
        ->fulfillments()
        ->sole();
    $voucher = Voucher::query()->where('code', $fulfillment->pay_code)->sole();
    expect($fulfilled->exitCode)->toBe(Command::SUCCESS)
        ->and(data_get($fulfillment->metadata, 'execution_exception'))->toBeNull()
        ->and(data_get($fulfillment->metadata, 'execution_failure'))->toBeNull()
        ->and($fulfilled->payload['phase'])->toBe('fulfilled')
        ->and($fulfilled->payload['money_moved'])->toBeTrue()
        ->and($fulfilled->payload['provider_calls'])->toBe(1)
        ->and($fulfillment->status)->toBe('completed');
    $reconciliation = DisbursementReconciliation::query()
        ->where('voucher_code', $voucher->code)
        ->sole();

    expect(data_get($voucher->instructions?->toArray(), 'execution.driver'))->toBe('x_change_live_cash')
        ->and(data_get($voucher->metadata, 'treasury.pay_code_reservation.status'))->toBe('settled')
        ->and($voucher->isRedeemed())->toBeTrue()
        ->and($reconciliation->provider_transaction_id)->toBe('TXN-CAMPAIGN-DIRECT-001')
        ->and($reconciliation->account_number_masked)->toBe('*******0011')
        ->and(CampaignBatchFulfillmentOutbox::query()->sole()->status)->toBe('completed');
    $provider->assertDisburseCalledTimes(1);

    $replay = app(LifecycleScenarioEngine::class)->run(
        campaignBatchLifecycleCommand(),
        'campaign_payroll_direct_transfer',
        $options,
    );
    expect($replay->payload['phase'])->toBe('fulfilled');
    $provider->assertDisburseCalledTimes(1);
});
