<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Console\Commands\Funding;

use Illuminate\Console\Command;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use LBHurtado\XChange\Actions\Funding\PrepareFundingRequest;
use LBHurtado\XChange\Console\Concerns\InteractsWithJsonOutput;
use LBHurtado\XChange\Data\Funding\PrepareFundingRequestData;
use LBHurtado\XChange\Data\Treasury\TreasuryProviderConnectionData;
use LBHurtado\XChange\Enums\FundingRequestStatus;
use LBHurtado\XChange\Models\FundingRequest;
use LBHurtado\XChange\Services\Funding\FundingRequestOperatorDirectory;
use LBHurtado\XChange\Services\Funding\ReviewedFundingRequestLocator;
use LBHurtado\XChange\Services\Treasury\TreasuryProviderConnectionCatalog;
use RuntimeException;
use Throwable;

final class VerifyFundingRequestBackingCommand extends Command
{
    use InteractsWithJsonOutput;

    protected $signature = 'x-change:funding:maker:verify
        {pay-code : Reviewed Account Funding Pay Code}
        {--operator= : Configured maker operator ID}
        {--recognized-value= : Independently recognized amount}
        {--connection= : Active Treasury connection reference}
        {--evidence-reference= : Independent backing evidence reference}
        {--review-notes= : Optional maker review notes}
        {--commit : Record maker verification}
        {--json : Emit a machine-readable result}
        {--pretty : Pretty-print JSON output}';

    protected $description = 'Interactively verify backing for a reviewed Account Funding Pay Code';

    public function handle(
        ReviewedFundingRequestLocator $locator,
        FundingRequestOperatorDirectory $operators,
        TreasuryProviderConnectionCatalog $connections,
        PrepareFundingRequest $prepare,
    ): int {
        try {
            $request = $locator->byPayCode((string) $this->argument('pay-code'));

            if ($request->status === FundingRequestStatus::AwaitingApproval) {
                $this->renderPayload(
                    $this->payload($request, 'already_verified', false),
                    'Funding Request backing already verified',
                );

                return self::SUCCESS;
            }

            if (! in_array($request->status, [
                FundingRequestStatus::Submitted,
                FundingRequestStatus::UnderReview,
                FundingRequestStatus::NeedsInformation,
            ], true)) {
                throw new RuntimeException(
                    "Funding Request [{$request->reference}] is not awaiting maker verification.",
                );
            }

            $eligibleMakers = $operators->makersFor($request);

            if ($eligibleMakers === []) {
                throw new RuntimeException(
                    'No configured maker is eligible; the requester cannot verify their own Funding Request.',
                );
            }

            $operator = $this->resolveOperator($operators, $eligibleMakers);
            $connection = $this->resolveConnection($connections);
            $recognizedValueMinor = $this->resolveRecognizedValue($request);
            $evidenceReference = $this->resolveEvidenceReference($request);
            $reviewNotes = $this->resolveReviewNotes();
            $commit = $this->resolveCommit();

            if (! $commit) {
                $this->renderPayload(
                    $this->payload(
                        $request,
                        'preview_ready',
                        false,
                        $operator,
                        $connection->reference,
                        $recognizedValueMinor,
                        $evidenceReference,
                    ),
                    'Funding Request maker verification preview',
                );

                return self::SUCCESS;
            }

            $prepared = $prepare->handle(
                $request,
                new PrepareFundingRequestData(
                    recognizedValueMinor: $recognizedValueMinor,
                    currency: $request->currency,
                    connectionReference: $connection->reference,
                    evidenceReference: $evidenceReference,
                    reviewerType: $operator::class,
                    reviewerId: (string) $operator->getAuthIdentifier(),
                    reviewNotes: $reviewNotes,
                ),
            );
            $this->renderPayload(
                $this->payload(
                    $prepared,
                    'verified',
                    true,
                    $operator,
                    $connection->reference,
                    $recognizedValueMinor,
                    $evidenceReference,
                ),
                'Funding Request backing verified',
            );

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->renderPayload([
                'schema' => 'x-change.funding-maker-verification-command.v1',
                'success' => false,
                'status' => 'rejected',
                'message' => $exception->getMessage(),
                'funds_moved' => false,
                'provider_calls' => false,
            ]);

            return self::FAILURE;
        }
    }

    /**
     * @param  list<Model&Authenticatable>  $eligibleMakers
     * @return Model&Authenticatable
     */
    private function resolveOperator(
        FundingRequestOperatorDirectory $directory,
        array $eligibleMakers,
    ): Model {
        $operatorId = $this->optionalOption('operator');

        if ($operatorId === null && $this->shouldPrompt()) {
            $options = $directory->options($eligibleMakers);
            $labels = array_values($options);
            $selected = (string) $this->choice(
                'Maker operator',
                $labels,
                $labels[0],
            );
            $operatorId = (string) array_search($selected, $options, true);
        }

        if ($operatorId === null) {
            throw new RuntimeException(
                'Maker verification requires --operator in non-interactive mode.',
            );
        }

        return $directory->resolve($eligibleMakers, $operatorId, 'maker');
    }

    private function resolveConnection(
        TreasuryProviderConnectionCatalog $connections,
    ): TreasuryProviderConnectionData {
        $active = $connections->active();

        if ($active === []) {
            throw new RuntimeException('No active Treasury connection is available.');
        }

        $reference = $this->optionalOption('connection');

        if ($reference === null && $this->shouldPrompt()) {
            $labels = array_map(
                static fn ($connection): string => sprintf(
                    '%s · %s %s',
                    $connection->reference,
                    mb_strtoupper($connection->provider),
                    $connection->currency,
                ),
                $active,
            );
            $selected = (string) $this->choice(
                'Treasury connection',
                $labels,
                $labels[0],
            );
            $index = array_search($selected, $labels, true);
            $reference = $active[$index === false ? 0 : $index]->reference;
        }

        if ($reference === null) {
            throw new RuntimeException(
                'Maker verification requires --connection in non-interactive mode.',
            );
        }

        return collect($connections->active([$reference]))->sole();
    }

    private function resolveRecognizedValue(FundingRequest $request): int
    {
        $default = $this->formatAmount($request->requested_value_minor);
        $amount = $this->optionalOption('recognized-value');

        if ($amount === null && $this->shouldPrompt()) {
            $answer = trim((string) $this->ask(
                'Recognized value',
                $default,
            ));
            $amount = $answer !== '' ? $answer : $default;
        }

        if ($amount === null) {
            throw new RuntimeException(
                'Maker verification requires --recognized-value in non-interactive mode.',
            );
        }

        $minor = $this->parseAmount($amount);

        if ($minor !== $request->requested_value_minor) {
            throw new RuntimeException(
                'Recognized backing must exactly match the requested Pay Code target.',
            );
        }

        return $minor;
    }

    private function resolveEvidenceReference(FundingRequest $request): string
    {
        $reference = $this->optionalOption('evidence-reference');
        $default = $this->laravel->environment('production')
            ? null
            : 'manual-verification:'.$request->reference;

        if ($reference === null && $this->shouldPrompt()) {
            $answer = trim((string) $this->ask(
                'Independent evidence reference',
                $default,
            ));
            $reference = $answer !== '' ? $answer : $default;
        }

        if ($reference === null || $reference === '') {
            throw new RuntimeException(
                'Independent backing evidence is required.',
            );
        }

        return $reference;
    }

    private function resolveReviewNotes(): ?string
    {
        $notes = $this->optionalOption('review-notes');

        if ($notes === null && $this->shouldPrompt()) {
            $default = 'Verified through the privileged Artisan maker workflow.';
            $answer = trim((string) $this->ask(
                'Review notes',
                $default,
            ));
            $notes = $answer !== '' ? $answer : $default;
        }

        return $notes;
    }

    private function resolveCommit(): bool
    {
        if ((bool) $this->option('commit')) {
            return true;
        }

        return $this->shouldPrompt()
            && $this->confirm('Record backing verification?', false);
    }

    private function parseAmount(string $amount): int
    {
        $amount = trim($amount);

        if (preg_match('/^\d+(?:\.\d{1,2})?$/', $amount) !== 1) {
            throw new RuntimeException(
                'Recognized value must be a positive amount with at most two decimal places.',
            );
        }

        [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '');
        $minor = ((int) $whole * 100) + (int) str_pad($fraction, 2, '0');

        if ($minor <= 0) {
            throw new RuntimeException(
                'Recognized value must be greater than zero.',
            );
        }

        return $minor;
    }

    private function formatAmount(int $minor): string
    {
        return number_format($minor / 100, 2, '.', '');
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
        ?Model $operator = null,
        ?string $connection = null,
        ?int $recognizedValueMinor = null,
        ?string $evidenceReference = null,
    ): array {
        return [
            'schema' => 'x-change.funding-maker-verification-command.v1',
            'success' => true,
            'status' => $status,
            'committed' => $committed,
            'pay_code' => $request->voucher?->code,
            'funding_request' => [
                'reference' => $request->reference,
                'status' => $request->status->value,
                'requested_value_minor' => $request->requested_value_minor,
                'currency' => $request->currency,
            ],
            'maker' => $operator === null ? null : [
                'type' => $operator::class,
                'id' => (string) $operator->getKey(),
            ],
            'verification' => [
                'recognized_value_minor' => $recognizedValueMinor,
                'connection_reference' => $connection,
                'evidence_reference' => $evidenceReference,
            ],
            'funds_moved' => false,
            'provider_calls' => false,
        ];
    }
}
