<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Lifecycle\Runners;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use LBHurtado\XCampaign\Contracts\CampaignWorksheetRepository;
use LBHurtado\XCampaign\Data\CampaignWorksheetData;
use LBHurtado\XCampaign\Data\CampaignWorksheetRowData;
use LBHurtado\XCampaign\Models\CampaignWorksheet;
use LBHurtado\XCampaign\Models\CampaignWorksheetAuthorization;
use LBHurtado\XChange\Actions\Campaigns\ApproveCampaignWorksheetAuthorization;
use LBHurtado\XChange\Actions\Campaigns\DispatchCampaignPayCodeDeliveries;
use LBHurtado\XChange\Actions\Campaigns\ExecuteCampaignWorksheetDirectTransfers;
use LBHurtado\XChange\Actions\Campaigns\IssueCampaignWorksheetApprovalPayCode;
use LBHurtado\XChange\Actions\Campaigns\IssueCampaignWorksheetPayCodes;
use LBHurtado\XChange\Lifecycle\Scenarios\LifecycleScenarioBootstrapper;
use LBHurtado\XChange\Models\CampaignBatchFulfillmentOutbox;
use LBHurtado\XChange\Services\Campaigns\CampaignBatchFulfillmentOutboxProcessor;
use LBHurtado\XChange\Services\Campaigns\CampaignWorksheetImportNormalizer;
use LBHurtado\XChange\Services\Campaigns\CampaignWorksheetTabularReader;
use RuntimeException;

final readonly class CampaignBatchScenarioRunner implements ScenarioRunnerContract
{
    public function __construct(
        private CampaignWorksheetRepository $worksheets,
        private CampaignWorksheetTabularReader $reader,
        private CampaignWorksheetImportNormalizer $normalizer,
        private IssueCampaignWorksheetApprovalPayCode $approvalPayCodes,
        private IssueCampaignWorksheetPayCodes $payCodes,
        private DispatchCampaignPayCodeDeliveries $deliveries,
        private ExecuteCampaignWorksheetDirectTransfers $directTransfers,
        private ApproveCampaignWorksheetAuthorization $approveAuthorization,
        private LifecycleScenarioBootstrapper $bootstrapper,
        private CampaignBatchFulfillmentOutboxProcessor $outboxProcessor,
    ) {}

    public function run(ScenarioRunContext $context): ScenarioRunResult
    {
        try {
            $runReference = $this->requiredRuntimeString($context, 'run_reference');
            $phase = $this->phase($context);
            $checker = $this->checker($context);
            $worksheet = $this->existingWorksheet($context->issuer, $runReference);

            if ($phase === 'prepare') {
                if (! $worksheet instanceof CampaignWorksheet) {
                    $worksheet = $this->prepareWorksheet($context, $runReference, $checker);
                } else {
                    $this->assertReplayMatches($context, $worksheet, $checker, requireInput: true);
                }
            } else {
                if (! $worksheet instanceof CampaignWorksheet) {
                    throw new RuntimeException('The campaign batch must be prepared by its maker before this phase can run.');
                }

                $this->assertReplayMatches($context, $worksheet, $checker, requireInput: false);
            }

            $authorization = $worksheet->authorizations()->latest('id')->first();

            if ($phase === 'approve' && $authorization?->status !== 'authorized') {
                if (data_get($context->scenario, '_runtime.confirm_checker_approval') !== true) {
                    throw new RuntimeException('Checker approval requires --confirm-checker-approval.');
                }

                $authorization = $this->approveAuthorization->handle(
                    (string) $authorization?->approval_pay_code,
                    $checker,
                );

                $outbox = CampaignBatchFulfillmentOutbox::query()
                    ->where('campaign_worksheet_authorization_id', $authorization->getKey())
                    ->first();
                if ($outbox instanceof CampaignBatchFulfillmentOutbox) {
                    $this->outboxProcessor->process($outbox);
                }
            }

            if ($phase !== 'status' && $authorization?->status === 'authorized') {
                $this->resumeAuthorizedFulfillment($context, $authorization);
                $worksheet->refresh()->load(['rows', 'authorizations.fulfillments']);
                $authorization = $worksheet->authorizations->sortByDesc('id')->first();
            }

            return new ScenarioRunResult(
                exitCode: Command::SUCCESS,
                payload: $this->payload($context, $worksheet, $authorization),
            );
        } catch (RuntimeException $exception) {
            return new ScenarioRunResult(
                exitCode: Command::FAILURE,
                payload: [
                    'success' => false,
                    'scenario' => $context->scenarioKey,
                    'mode' => 'campaign_batch',
                    'message' => $exception->getMessage(),
                ],
            );
        }
    }

    private function prepareWorksheet(
        ScenarioRunContext $context,
        string $runReference,
        Model $checker,
    ): CampaignWorksheet {
        $path = $this->requiredRuntimeString($context, 'input');
        $realPath = realpath($path);

        if ($realPath === false || ! is_file($realPath) || ! is_readable($realPath)) {
            throw new RuntimeException('The campaign input file is unavailable or unreadable.');
        }

        $extension = mb_strtolower((string) pathinfo($realPath, PATHINFO_EXTENSION));
        if (! in_array($extension, ['csv', 'xlsx'], true)) {
            throw new RuntimeException('Campaign batch input must be a CSV or XLSX file.');
        }

        $file = new UploadedFile(
            path: $realPath,
            originalName: basename($realPath),
            mimeType: $extension === 'xlsx'
                ? 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
                : 'text/csv',
            error: UPLOAD_ERR_OK,
            test: true,
        );
        $source = $this->reader->read($file);
        if ($source['rows'] === []) {
            throw new RuntimeException('The campaign input file contains no beneficiary rows.');
        }

        $fulfillmentMode = (string) data_get($context->scenario, 'campaign.fulfillment_mode');
        if (! in_array($fulfillmentMode, ['direct_bank_transfer', 'pay_code_distribution'], true)) {
            throw new RuntimeException('The campaign lifecycle fulfillment mode is unsupported.');
        }

        $mapping = $this->normalizer->detectMapping($source['headers']);
        $normalized = $this->normalizer->normalizeRows(
            rows: $source['rows'],
            mapping: $mapping,
            fulfillmentMode: $fulfillmentMode,
            defaultWallet: (string) data_get($context->scenario, 'campaign.default_wallet', 'GCash'),
            defaultDeliveryPreference: (string) data_get($context->scenario, 'campaign.delivery_preference', 'manual'),
        );
        $invalid = collect($normalized)->where('status', 'invalid');
        if ($invalid->isNotEmpty()) {
            $first = $invalid->first();
            $message = (string) data_get($first, 'errors.0', 'A beneficiary row is invalid.');

            throw new RuntimeException(sprintf(
                'Campaign input row %d is invalid: %s',
                (int) data_get($first, 'source_row', 0),
                $message,
            ));
        }

        $ownerType = $context->issuer->getMorphClass();
        $ownerId = (string) $context->issuer->getKey();
        $worksheetData = $this->worksheets->put(new CampaignWorksheetData(
            reference: null,
            ownerType: $ownerType,
            ownerId: $ownerId,
            profile: (string) data_get($context->scenario, 'campaign.profile', 'payroll'),
            name: (string) data_get($context->scenario, 'label', 'Campaign payroll'),
            fulfillmentMode: $fulfillmentMode,
            deliveryPlan: [(string) data_get($context->scenario, 'campaign.delivery_preference', 'manual')],
            metadata: [
                'lifecycle' => [
                    'schema' => 'x-change.campaign-batch.v1',
                    'scenario' => $context->scenarioKey,
                    'run_reference' => $runReference,
                    'content_hash' => hash_file('sha256', $realPath),
                    'automatic_fulfillment' => true,
                    'live_provider_authorized' => data_get($context->scenario, '_runtime.live_provider') === true,
                    'live_transfer_confirmed' => data_get($context->scenario, '_runtime.confirm_live_transfer') === true,
                    'live_feedback_authorized' => data_get($context->scenario, '_runtime.live_feedback') === true,
                    'maker_type' => $context->issuer->getMorphClass(),
                    'maker_id' => (string) $context->issuer->getKey(),
                    'checker_type' => $checker->getMorphClass(),
                    'checker_id' => (string) $checker->getKey(),
                    'provider' => (string) data_get($context->scenario, 'provider', 'netbank'),
                ],
            ],
        ));

        $rows = collect($normalized)->map(function (array $row): CampaignWorksheetRowData {
            $normalizedRow = (array) $row['normalized'];

            return new CampaignWorksheetRowData(
                reference: null,
                ordinal: 0,
                beneficiary: (array) $normalizedRow['beneficiary'],
                amountMinor: (int) $normalizedRow['amount_minor'],
                currency: (string) $normalizedRow['currency'],
                deliveryPreference: (string) $normalizedRow['delivery_preference'],
            );
        })->all();

        $this->worksheets->appendRows(
            $worksheetData->reference,
            $ownerType,
            $ownerId,
            $rows,
        );
        $this->worksheets->updateInstructionBlueprint(
            $worksheetData->reference,
            $ownerType,
            $ownerId,
            $this->instructionBlueprint($context),
            'x-campaign.instruction-blueprint.v1',
            0,
        );
        $this->worksheets->freeze($worksheetData->reference, $ownerType, $ownerId);
        $this->approvalPayCodes->handle($worksheetData->reference, $context->issuer);

        return CampaignWorksheet::query()
            ->with(['rows', 'authorizations'])
            ->where('reference', $worksheetData->reference)
            ->firstOrFail();
    }

    private function existingWorksheet(Model $issuer, string $runReference): ?CampaignWorksheet
    {
        return CampaignWorksheet::query()
            ->with(['rows', 'authorizations'])
            ->where('owner_type', $issuer->getMorphClass())
            ->where('owner_id', (string) $issuer->getKey())
            ->where('metadata->lifecycle->run_reference', $runReference)
            ->first();
    }

    private function assertReplayMatches(
        ScenarioRunContext $context,
        CampaignWorksheet $worksheet,
        Model $checker,
        bool $requireInput,
    ): void {
        $input = data_get($context->scenario, '_runtime.input');
        if ($requireInput || (is_string($input) && trim($input) !== '')) {
            $path = $this->requiredRuntimeString($context, 'input');
            $realPath = realpath($path);
            if ($realPath === false || ! is_file($realPath) || ! is_readable($realPath)) {
                throw new RuntimeException('The campaign input file is unavailable or unreadable.');
            }

            $expected = data_get($worksheet->metadata, 'lifecycle.content_hash');
            $actual = hash_file('sha256', $realPath);
            if (! is_string($expected) || ! hash_equals($expected, $actual)) {
                throw new RuntimeException('The lifecycle run reference is already bound to a different campaign input file.');
            }
        }

        if (data_get($worksheet->metadata, 'lifecycle.scenario') !== $context->scenarioKey) {
            throw new RuntimeException('The lifecycle run reference is already bound to a different campaign scenario.');
        }

        if (data_get($worksheet->metadata, 'lifecycle.checker_type') !== $checker->getMorphClass()
            || (string) data_get($worksheet->metadata, 'lifecycle.checker_id') !== (string) $checker->getKey()) {
            throw new RuntimeException('The lifecycle run reference is already bound to a different designated checker.');
        }
    }

    private function phase(ScenarioRunContext $context): string
    {
        $phase = data_get($context->scenario, '_runtime.phase', 'prepare');
        $phase = is_string($phase) && trim($phase) !== '' ? trim($phase) : 'prepare';
        if (! in_array($phase, ['prepare', 'approve', 'status'], true)) {
            throw new RuntimeException('Campaign batch lifecycle --phase must be prepare, approve, or status.');
        }

        return $phase;
    }

    private function checker(ScenarioRunContext $context): Model
    {
        $checkerId = $this->requiredRuntimeString($context, 'checker');
        if (! ctype_digit($checkerId) || (int) $checkerId < 1) {
            throw new RuntimeException('Campaign batch lifecycle --checker must be a persisted user id.');
        }

        $checker = $this->bootstrapper->resolveIssuerModel((int) $checkerId);
        if ($checker->getMorphClass() === $context->issuer->getMorphClass()
            && (string) $checker->getKey() === (string) $context->issuer->getKey()) {
            throw new RuntimeException('Campaign maker and checker must be different persisted users.');
        }

        return $checker;
    }

    /** @return array<string, mixed> */
    private function instructionBlueprint(ScenarioRunContext $context): array
    {
        $channel = (string) data_get($context->scenario, 'campaign.delivery_preference', 'manual');

        return [
            'rider' => [
                'message' => (string) data_get($context->scenario, 'campaign.purpose', 'Payroll'),
            ],
            'feedback' => [
                'channels' => $channel === 'sms' ? ['mobile'] : [],
            ],
            'claim' => [
                'onboarding' => ['mode' => 'if_required'],
            ],
            'expiry_days' => max(1, (int) data_get($context->scenario, 'campaign.expiry_days', 7)),
        ];
    }

    /** @return array<string, mixed> */
    private function payload(
        ScenarioRunContext $context,
        CampaignWorksheet $worksheet,
        ?CampaignWorksheetAuthorization $authorization,
    ): array {
        $authorized = $authorization?->status === 'authorized';
        $payCodeDistribution = $worksheet->fulfillment_mode === 'pay_code_distribution';
        $liveFeedback = data_get($context->scenario, '_runtime.live_feedback') === true;
        $fulfillments = $authorization?->fulfillments ?? collect();
        $completed = $fulfillments->where('status', 'completed')->count();
        $indeterminate = $fulfillments->where('status', 'provider_indeterminate')->count();
        $phase = match (true) {
            ! $authorized => 'awaiting_checker',
            $payCodeDistribution && ! $liveFeedback => 'authorized_waiting_feedback_gate',
            $payCodeDistribution => 'fulfillment_queued',
            $indeterminate > 0 => 'provider_indeterminate',
            $completed === $worksheet->rows->count() => 'fulfilled',
            default => 'authorized_waiting_transfer_gate',
        };

        return [
            'success' => true,
            'scenario' => $context->scenarioKey,
            'mode' => 'campaign_batch',
            'phase' => $phase,
            'worksheet_reference' => (string) $worksheet->reference,
            'authorization_reference' => $authorization?->reference,
            'approval_pay_code' => $authorization?->approval_pay_code,
            'beneficiary_count' => $worksheet->rows->count(),
            'principal_minor' => $worksheet->rows->sum('amount_minor'),
            'currency' => (string) $worksheet->currency,
            'fulfillment_mode' => (string) $worksheet->fulfillment_mode,
            'provider_calls' => $payCodeDistribution ? 0 : $completed + $indeterminate,
            'money_moved' => $completed > 0,
            'next_action' => match ($phase) {
                'awaiting_checker' => 'An independent checker must claim the approval Pay Code before fulfillment can begin.',
                'authorized_waiting_feedback_gate' => 'Resume with --live-feedback to issue beneficiary Pay Codes and queue SMS delivery.',
                'fulfillment_queued' => 'Beneficiary Pay Codes were issued and their SMS deliveries were queued.',
                'fulfilled' => 'Every approved beneficiary transfer completed through the voucher execution engine.',
                'provider_indeterminate' => 'At least one provider outcome is indeterminate. Reconcile it before any retry.',
                default => 'The approved direct-transfer batch is waiting for its accounting-safe execution gate.',
            },
        ];
    }

    private function requiredRuntimeString(ScenarioRunContext $context, string $key): string
    {
        $value = data_get($context->scenario, '_runtime.'.$key);
        if (! is_string($value) || trim($value) === '') {
            throw new RuntimeException(sprintf('Campaign batch lifecycle scenarios require --%s.', str_replace('_', '-', $key)));
        }

        return trim($value);
    }

    private function resumeAuthorizedFulfillment(
        ScenarioRunContext $context,
        CampaignWorksheetAuthorization $authorization,
    ): void {
        if ($authorization->worksheet?->fulfillment_mode !== 'pay_code_distribution') {
            $this->payCodes->handle((string) $authorization->reference, $context->issuer, 500);
            $authorization->refresh()->load('fulfillments.row');
            $this->directTransfers->handle($authorization, $context->issuer, 500);

            return;
        }

        if (data_get($context->scenario, '_runtime.live_feedback') !== true) {
            return;
        }

        $this->payCodes->handle((string) $authorization->reference, $context->issuer, 500);
        $authorization->refresh()->load('fulfillments.row');
        $this->deliveries->handle($authorization, $context->issuer, 'sms', 500);
    }
}
