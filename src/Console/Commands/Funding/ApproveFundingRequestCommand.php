<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Console\Commands\Funding;

use Illuminate\Console\Command;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use LBHurtado\XChange\Actions\Funding\ApproveFundingRequestAndIssueCode;
use LBHurtado\XChange\Console\Concerns\InteractsWithJsonOutput;
use LBHurtado\XChange\Enums\FundingRequestStatus;
use LBHurtado\XChange\Jobs\Funding\PayApprovedFundingRequestJob;
use LBHurtado\XChange\Models\FundingRequest;
use LBHurtado\XChange\Services\Funding\FundingRequestOperatorDirectory;
use LBHurtado\XChange\Services\Funding\ReviewedFundingRequestLocator;
use RuntimeException;
use Throwable;

final class ApproveFundingRequestCommand extends Command
{
    use InteractsWithJsonOutput;

    protected $signature = 'x-change:funding:checker:approve
        {pay-code : Maker-verified Account Funding Pay Code}
        {--operator= : Configured checker operator ID}
        {--commit : Approve and queue system Treasury payment}
        {--json : Emit a machine-readable result}
        {--pretty : Pretty-print JSON output}';

    protected $description = 'Interactively approve maker-verified Account Funding and queue payment';

    public function handle(
        ReviewedFundingRequestLocator $locator,
        FundingRequestOperatorDirectory $operators,
        ApproveFundingRequestAndIssueCode $approve,
    ): int {
        try {
            $request = $locator->byPayCode((string) $this->argument('pay-code'));

            if (! in_array($request->status, [
                FundingRequestStatus::AwaitingApproval,
                FundingRequestStatus::PayCodeIssued,
                FundingRequestStatus::Completed,
            ], true)) {
                throw new RuntimeException(
                    "Funding Request [{$request->reference}] is not awaiting checker approval. "
                    ."Run php artisan x-change:funding:maker:verify {$request->voucher->code} first.",
                );
            }

            $eligibleCheckers = $operators->checkersFor($request);

            if ($eligibleCheckers === []) {
                throw new RuntimeException(
                    'No configured checker is eligible; requester and maker are excluded.',
                );
            }

            $operator = $this->resolveOperator($operators, $eligibleCheckers);
            $commit = $this->resolveCommit();

            if (! $commit) {
                $this->renderPayload(
                    $this->payload(
                        $request,
                        'preview_ready',
                        false,
                        false,
                        $operator,
                    ),
                    'Funding Request checker approval preview',
                );

                return self::SUCCESS;
            }

            $result = $approve->approve(
                $request,
                $operator::class,
                (string) $operator->getAuthIdentifier(),
            );

            if ($result->newlyApproved) {
                PayApprovedFundingRequestJob::dispatch($request->reference)
                    ->afterCommit();
            }

            $fresh = $request->refresh();
            $status = match (true) {
                ! $result->newlyApproved => 'already_approved',
                $fresh->status === FundingRequestStatus::Completed => 'funded',
                default => 'approved_and_queued',
            };
            $this->renderPayload(
                $this->payload(
                    $fresh,
                    $status,
                    true,
                    $result->newlyApproved,
                    $operator,
                ),
                match ($status) {
                    'funded' => 'Funding Request approved and funded',
                    'already_approved' => 'Funding Request approval replayed',
                    default => 'Funding Request approved; Treasury payment queued',
                },
            );

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->renderPayload([
                'schema' => 'x-change.funding-checker-approval-command.v1',
                'success' => false,
                'status' => 'rejected',
                'message' => $exception->getMessage(),
                'payment_queued' => false,
                'provider_calls' => false,
            ]);

            return self::FAILURE;
        }
    }

    /**
     * @param  list<Model&Authenticatable>  $eligibleCheckers
     * @return Model&Authenticatable
     */
    private function resolveOperator(
        FundingRequestOperatorDirectory $directory,
        array $eligibleCheckers,
    ): Model {
        $operatorId = $this->optionalOption('operator');

        if ($operatorId === null && $this->shouldPrompt()) {
            $options = $directory->options($eligibleCheckers);
            $labels = array_values($options);
            $selected = (string) $this->choice(
                'Checker operator',
                $labels,
                $labels[0],
            );
            $operatorId = (string) array_search($selected, $options, true);
        }

        if ($operatorId === null) {
            throw new RuntimeException(
                'Checker approval requires --operator in non-interactive mode.',
            );
        }

        return $directory->resolve($eligibleCheckers, $operatorId, 'checker');
    }

    private function resolveCommit(): bool
    {
        if ((bool) $this->option('commit')) {
            return true;
        }

        return $this->shouldPrompt()
            && $this->confirm('Approve and fund this Account?', false);
    }

    private function optionalOption(string $name): ?string
    {
        $value = trim((string) $this->option($name));

        return $value === '' ? null : $value;
    }

    private function shouldPrompt(): bool
    {
        return $this->input->isInteractive() && ! $this->shouldOutputJson();
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(
        FundingRequest $request,
        string $status,
        bool $committed,
        bool $paymentQueued,
        ?Model $operator = null,
    ): array {
        return [
            'schema' => 'x-change.funding-checker-approval-command.v1',
            'success' => true,
            'status' => $status,
            'committed' => $committed,
            'pay_code' => $request->voucher?->code,
            'funding_request' => [
                'reference' => $request->reference,
                'status' => $request->status->value,
                'requested_value_minor' => $request->requested_value_minor,
                'recognized_value_minor' => $request->approved_value_minor,
                'currency' => $request->currency,
                'connection_reference' => $request->connection_reference,
                'evidence_reference' => $request->evidence_reference,
                'maker' => [
                    'type' => $request->reviewed_by_type,
                    'id' => $request->reviewed_by_id,
                ],
            ],
            'checker' => $operator === null ? null : [
                'type' => $operator::class,
                'id' => (string) $operator->getKey(),
            ],
            'payment_queued' => $paymentQueued,
            'provider_calls' => false,
        ];
    }
}
