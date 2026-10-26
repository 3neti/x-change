<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Lifecycle\Runners;

use Illuminate\Console\Command;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LBHurtado\Contact\Models\Contact;
use LBHurtado\Voucher\Data\ExecutionContextData;
use LBHurtado\Voucher\Data\ExecutionInstructionData;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\Voucher\Services\ExecutionEngine;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryAllocationReadModelContract;
use LBHurtado\Wallet\Treasury\Data\TreasuryAllocationReadModelQueryData;
use LBHurtado\Wallet\Treasury\Enums\TreasuryAllocationOperationType;
use LBHurtado\Wallet\Treasury\Models\TreasuryAllocationOperation;
use LBHurtado\Wallet\Treasury\Models\TreasuryInventory;
use LBHurtado\XChange\Lifecycle\Support\SimulatedIdentityOtpChallengeGateway;
use LBHurtado\XChange\Models\StoredValueHolderBinding;
use LBHurtado\XChange\Services\StoredValue\StoredValueAuditJournal;
use LBHurtado\XChange\Services\StoredValue\StoredValueCollectionService;
use LBHurtado\XChange\Support\Auth\MobileNumber;
use RuntimeException;
use Throwable;

final readonly class StoredValueCollectionScenarioRunner implements ScenarioRunnerContract
{
    public function __construct(
        private ExecutionEngine $engine,
        private TreasuryAllocationReadModelContract $balances,
        private StoredValueAuditJournal $auditJournal,
        private SimulatedIdentityOtpChallengeGateway $otp,
    ) {}

    public function run(ScenarioRunContext $context): ScenarioRunResult
    {
        $startingLevel = DB::transactionLevel();
        $originalUser = Auth::user();
        $originalProjection = config('x-change.audit.cache.projection_enabled');
        $payload = [];
        $exitCode = Command::FAILURE;
        DB::beginTransaction();
        config()->set('x-change.audit.cache.projection_enabled', false);

        try {
            $payload = $this->exercise($context);
            $exitCode = $payload['success'] ? Command::SUCCESS : Command::FAILURE;
        } catch (Throwable $exception) {
            if (app()->runningUnitTests()) {
                throw $exception;
            }
            report($exception);
            $payload = [
                'success' => false,
                'message' => 'Stored value collection simulation could not complete safely.',
                'error' => $exception::class,
            ];
        } finally {
            while (DB::transactionLevel() > $startingLevel) {
                DB::rollBack();
            }
            config()->set('x-change.audit.cache.projection_enabled', $originalProjection);
            if ($originalUser instanceof Authenticatable) {
                Auth::guard()->setUser($originalUser);
            } else {
                Auth::forgetGuards();
            }
        }

        return new ScenarioRunResult($exitCode, [
            'scenario' => $context->scenarioKey,
            'mode' => 'stored_value_collection_simulation',
            'rollback_completed' => DB::transactionLevel() === $startingLevel,
            ...$payload,
        ]);
    }

    /** @return array<string, mixed> */
    private function exercise(ScenarioRunContext $context): array
    {
        $voucher = $context->voucher;
        if (! $voucher instanceof Voucher || ! $voucher->exists
            || data_get($voucher->metadata, 'instructions.execution.driver') !== 'stored_value') {
            throw new RuntimeException('The scenario requires an issued stored value Pay Code with a Treasury reservation.');
        }

        $holder = $this->user((string) data_get($context->scenario, 'stored_value_collection.holder_email'));
        $collector = $this->user((string) data_get($context->scenario, 'stored_value_collection.collector_email'));
        $mobile = MobileNumber::normalize($holder->getRawOriginal('mobile'));
        if ($mobile === null || $holder->getAttribute('mobile_verified_at') === null) {
            throw new RuntimeException('The scenario holder must have a verified mobile.');
        }

        $binding = StoredValueHolderBinding::query()->where('voucher_id', $voucher->getKey())->first();
        if (! $binding instanceof StoredValueHolderBinding) {
            Auth::guard()->setUser($holder);
            $activation = $this->engine->execute(new ExecutionContextData(
                contact: new Contact(['mobile' => '+'.$mobile, 'country' => 'PH']),
                voucherCode: (string) $voucher->code,
                voucher: $voucher,
                instruction: ExecutionInstructionData::from(data_get($voucher->metadata, 'instructions.execution')),
                correlation: ['execution_id' => 'stored-value-collection-simulation:'.$context->idempotencyKey.':activate'],
            ));
            if (! $activation->successful) {
                throw new RuntimeException('Stored value activation failed in the collection simulation.');
            }
            $binding = StoredValueHolderBinding::query()->where('voucher_id', $voucher->getKey())->sole();
        }

        $amountMinor = (int) data_get($context->scenario, 'stored_value_collection.amount_minor', 2500);
        $before = $this->balances->read(new TreasuryAllocationReadModelQueryData($binding->allocation_reference, $binding->currency));
        $drawsBefore = TreasuryAllocationOperation::query()->where('operation_type', TreasuryAllocationOperationType::Draw)->count();
        $inventoryBefore = (int) TreasuryInventory::query()->sum('balance_minor');
        $service = new StoredValueCollectionService($this->otp, $this->engine, $this->balances, $this->auditJournal);
        $pending = $service->start($collector, 'cockpit', '|| '.$voucher->code.' ||', $amountMinor, $binding->currency, $context->idempotencyKey);
        $reference = $pending['data']['reference'];
        $otpRequired = $pending['data']['status'] === 'pending';
        $balanceHidden = ! array_key_exists('available_minor', $pending['data']);
        $blockedBeforeOtp = false;
        $verified = $pending;
        if ($otpRequired) {
            try {
                $service->commit($collector, 'cockpit', $reference);
            } catch (ValidationException) {
                $blockedBeforeOtp = true;
            }
            $verified = $service->verify($collector, 'cockpit', $reference, '000000');
        }
        $committed = $service->commit($collector, 'cockpit', $reference);
        $replayed = $service->commit($collector, 'cockpit', $reference);
        $after = $this->balances->read(new TreasuryAllocationReadModelQueryData($binding->allocation_reference, $binding->currency));
        $drawsAfter = TreasuryAllocationOperation::query()->where('operation_type', TreasuryAllocationOperationType::Draw)->count();
        $inventoryAfter = (int) TreasuryInventory::query()->sum('balance_minor');
        $passed = ($otpRequired ? $balanceHidden && $blockedBeforeOtp : ! $balanceHidden && ! $blockedBeforeOtp)
            && $verified['data']['available_minor'] === $before->usableAmountMinor
            && $committed === $replayed
            && $after->usableAmountMinor === $before->usableAmountMinor - $amountMinor
            && $drawsAfter === $drawsBefore + 1
            && $inventoryAfter === $inventoryBefore;

        return [
            'success' => $passed,
            'voucher_code' => (string) $voucher->code,
            'collection_reference' => $reference,
            'amount_minor' => $amountMinor,
            'balance_before_minor' => $before->usableAmountMinor,
            'balance_after_minor' => $after->usableAmountMinor,
            'balance_hidden_until_otp' => $balanceHidden,
            'commit_blocked_before_otp' => $blockedBeforeOtp,
            'authorization_method' => $pending['data']['authorization_method'],
            'draws_added' => $drawsAfter - $drawsBefore,
            'replay_preserved_receipt' => $committed === $replayed,
            'inventory_unchanged' => $inventoryAfter === $inventoryBefore,
        ];
    }

    private function user(string $email): Model
    {
        $class = config('auth.providers.users.model');
        if ($email === '' || ! is_string($class) || ! is_subclass_of($class, Model::class)) {
            throw new RuntimeException('The scenario requires holder and collector Account emails.');
        }

        return $class::query()->where('email', $email)->first()
            ?? throw new RuntimeException('The scenario Account email could not be resolved.');
    }
}
