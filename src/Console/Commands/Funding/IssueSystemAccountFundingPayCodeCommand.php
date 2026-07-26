<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Console\Commands\Funding;

use Carbon\CarbonInterface;
use Illuminate\Console\Command;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use LBHurtado\Voucher\Enums\VoucherType;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\Wallet\Contracts\SystemUserResolverContract;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryPositionReadModelContract;
use LBHurtado\Wallet\Treasury\Data\TreasuryPositionData;
use LBHurtado\Wallet\Treasury\Enums\TreasuryPositionPurpose;
use LBHurtado\XChange\Actions\Funding\IssueSystemAccountFundingPayCode;
use LBHurtado\XChange\Actions\Funding\PayApprovedFundingRequest;
use LBHurtado\XChange\Console\Concerns\InteractsWithJsonOutput;
use LBHurtado\XChange\Contracts\SystemAccountFundingPayCodeAuthorizationContract;
use LBHurtado\XChange\Contracts\TreasuryPrincipalReferenceResolverContract;
use LBHurtado\XChange\Data\Funding\IssueSystemAccountFundingPayCodeData;
use LBHurtado\XChange\Data\Funding\SystemAccountFundingPayCodeAuthorizationData;
use LBHurtado\XChange\Data\Treasury\TreasuryProviderConnectionData;
use LBHurtado\XChange\Enums\FundingRequestStatus;
use LBHurtado\XChange\Models\FundingRequest;
use LBHurtado\XChange\Models\SystemAccountFundingPayCodeIssuance;
use LBHurtado\XChange\Models\VoucherCollection;
use LBHurtado\XChange\Services\Treasury\TreasuryProviderConnectionCatalog;
use LBHurtado\XChange\Support\Auth\MobileNumber;
use RuntimeException;
use Throwable;

final class IssueSystemAccountFundingPayCodeCommand extends Command
{
    use InteractsWithJsonOutput;

    protected $signature = 'x-change:funding:issue-pay-code
        {pay-code? : Existing reviewed Account Funding Pay Code}
        {--amount= : Exact amount in the provider currency, such as 1000.00}
        {--recipient-mobile= : Verified Account owner mobile; preferred for recipient-bound issuance}
        {--recipient-id= : Account owner allowed to claim the Pay Code}
        {--bearer : Explicitly issue an unbound Pay Code when enabled}
        {--connection= : Treasury connection reference}
        {--reference= : Stable idempotency reference}
        {--expires-at= : Explicit ISO-8601 expiry; defaults to the configured TTL}
        {--evidence-reference= : Reference to verified backing evidence}
        {--authorization-reference= : Reference to the approving control record}
        {--commit : Reserve system funds and issue the Pay Code}
        {--confirm-production : Explicitly acknowledge production issuance}
        {--json : Emit a machine-readable result}
        {--pretty : Pretty-print JSON output}';

    protected $description = 'Preview or issue a system-backed Account Funding Pay Code';

    public function handle(
        TreasuryProviderConnectionCatalog $connections,
        SystemUserResolverContract $systemUsers,
        TreasuryPrincipalReferenceResolverContract $principalReferences,
        TreasuryPositionReadModelContract $positions,
        SystemAccountFundingPayCodeAuthorizationContract $authorization,
        IssueSystemAccountFundingPayCode $issue,
        PayApprovedFundingRequest $payApprovedFundingRequest,
    ): int {
        try {
            $payCode = trim((string) $this->argument('pay-code'));

            if ($payCode !== '') {
                return $this->handleReviewedFundingPayCode(
                    payCode: $payCode,
                    systemUsers: $systemUsers,
                    principalReferences: $principalReferences,
                    positions: $positions,
                    connections: $connections,
                    authorization: $authorization,
                    pay: $payApprovedFundingRequest,
                );
            }

            $this->prepareInteractiveDestination($connections);
            $recipient = $this->resolveRecipient();
            $this->prepareInteractiveIssuanceInput($recipient);

            $connection = $this->resolveConnection($connections);
            $reference = $this->requiredOption('reference');
            $amountMinor = $this->parseAmount(
                $this->requiredOption('amount'),
                $connection->decimalPlaces,
            );
            $bearer = (bool) $this->option('bearer');
            $commit = (bool) $this->option('commit');
            $evidenceReference = $this->optionalOption(
                'evidence-reference',
            );
            $authorizationReference = $this->optionalOption(
                'authorization-reference',
            );
            $expiresAt = $this->resolveExpiry($reference);
            $system = $systemUsers->resolve();

            if (
                ! $system instanceof Model
                || ! $system instanceof Authenticatable
            ) {
                throw new RuntimeException(
                    'The configured system principal is not an authenticatable model.',
                );
            }

            $authorization->authorize(
                new SystemAccountFundingPayCodeAuthorizationData(
                    amountMinor: $amountMinor,
                    connectionReference: $connection->reference,
                    bearer: $bearer,
                    commit: $commit,
                    productionConfirmed: (bool) $this->option(
                        'confirm-production',
                    ),
                    idempotencyReference: $reference,
                    evidenceReference: $evidenceReference,
                    authorizationReference: $authorizationReference,
                ),
            );

            $before = $this->positionBalances(
                $positions,
                $principalReferences->resolve($system),
                $connection,
            );

            if (! $commit) {
                $this->renderPayload(
                    $this->payload(
                        mode: 'preview',
                        status: $before['account_funding_reserve_minor'] >= $amountMinor
                            ? 'preview_ready'
                            : 'insufficient_system_funds',
                        connection: $connection,
                        amountMinor: $amountMinor,
                        reference: $reference,
                        expiresAt: $expiresAt,
                        recipient: $recipient,
                        bearer: $bearer,
                        before: $before,
                        after: [
                            'account_funding_reserve_minor' => $before['account_funding_reserve_minor'] - $amountMinor,
                            'pay_code_reserve_minor' => $before['pay_code_reserve_minor'] + $amountMinor,
                        ],
                    ),
                    'System Account Funding Pay Code preview',
                );

                return $before['account_funding_reserve_minor'] >= $amountMinor
                    ? self::SUCCESS
                    : self::FAILURE;
            }

            $existing = $this->findExisting($reference);
            $issuance = $issue->handle(
                new IssueSystemAccountFundingPayCodeData(
                    amountMinor: $amountMinor,
                    connectionReference: $connection->reference,
                    idempotencyReference: $reference,
                    expiresAt: $expiresAt,
                    recipient: $recipient,
                    evidenceReference: $evidenceReference,
                    authorizationReference: $authorizationReference,
                    source: 'system_utility',
                ),
            );
            $after = $this->positionBalances(
                $positions,
                $principalReferences->resolve($system),
                $connection,
            );

            $this->renderPayload(
                $this->payload(
                    mode: 'commit',
                    status: $existing === null ? 'issued' : 'replayed',
                    connection: $connection,
                    amountMinor: $amountMinor,
                    reference: $reference,
                    expiresAt: $issuance->expires_at,
                    recipient: $recipient,
                    bearer: $bearer,
                    before: $before,
                    after: $after,
                    issuance: $issuance,
                ),
                $existing === null
                    ? 'Account Funding Pay Code issued'
                    : 'Existing Account Funding Pay Code returned',
            );

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->renderPayload([
                'schema' => 'x-change.system-account-funding-pay-code-command.v1',
                'success' => false,
                'status' => 'rejected',
                'message' => $exception->getMessage(),
                'provider_calls' => false,
                'inventory_changed' => false,
            ]);

            return self::FAILURE;
        }
    }

    private function handleReviewedFundingPayCode(
        string $payCode,
        SystemUserResolverContract $systemUsers,
        TreasuryPrincipalReferenceResolverContract $principalReferences,
        TreasuryPositionReadModelContract $positions,
        TreasuryProviderConnectionCatalog $connections,
        SystemAccountFundingPayCodeAuthorizationContract $authorization,
        PayApprovedFundingRequest $pay,
    ): int {
        $this->rejectReviewedFundingOverrides();
        $voucher = Voucher::query()
            ->where('code', mb_strtoupper($payCode))
            ->first();

        if (
            ! $voucher instanceof Voucher
            || $voucher->voucher_type !== VoucherType::PAYABLE
        ) {
            throw new RuntimeException(
                'The reviewed Account Funding PAYABLE Pay Code was not found.',
            );
        }

        $request = FundingRequest::query()
            ->where('voucher_id', $voucher->getKey())
            ->sole();
        $this->assertReviewedFundingRequestReadyForPayment(
            $request,
            $voucher,
        );
        $connection = collect($connections->active([
            (string) $request->connection_reference,
        ]))->sole();
        $amountMinor = (int) (
            $request->approved_value_minor
            ?? $request->requested_value_minor
        );
        $commit = (bool) $this->option('commit');
        $system = $systemUsers->resolve();

        if (
            ! $system instanceof Model
            || ! $system instanceof Authenticatable
        ) {
            throw new RuntimeException(
                'The configured system principal is not an authenticatable model.',
            );
        }

        $authorizationReference = implode(':', [
            'funding-request-approval',
            (string) $request->reference,
            (string) $request->approved_by_type,
            (string) $request->approved_by_id,
        ]);
        $authorization->authorize(
            new SystemAccountFundingPayCodeAuthorizationData(
                amountMinor: $amountMinor,
                connectionReference: $connection->reference,
                bearer: false,
                commit: $commit,
                productionConfirmed: (bool) $this->option(
                    'confirm-production',
                ),
                idempotencyReference: 'reviewed-funding-system-payment:'
                    .$request->reference,
                evidenceReference: $request->evidence_reference,
                authorizationReference: $authorizationReference,
            ),
        );
        $before = $this->positionBalances(
            $positions,
            $principalReferences->resolve($system),
            $connection,
        );

        if (! $commit) {
            $this->renderPayload(
                $this->reviewedFundingPayload(
                    mode: 'preview',
                    status: $request->status === FundingRequestStatus::PayCodeIssued
                        ? 'preview_ready'
                        : 'awaiting_review',
                    voucher: $voucher,
                    request: $request,
                    connection: $connection,
                    amountMinor: $amountMinor,
                    before: $before,
                    after: $before,
                ),
                'Reviewed Account Funding Pay Code preview',
            );

            return $request->status === FundingRequestStatus::PayCodeIssued
                ? self::SUCCESS
                : self::FAILURE;
        }

        $wasCompleted = $request->status === FundingRequestStatus::Completed;
        $collection = $pay->handle($voucher);
        $after = $this->positionBalances(
            $positions,
            $principalReferences->resolve($system),
            $connection,
        );

        $this->renderPayload(
            $this->reviewedFundingPayload(
                mode: 'commit',
                status: $wasCompleted ? 'replayed' : 'paid',
                voucher: $voucher->refresh(),
                request: $request->refresh(),
                connection: $connection,
                amountMinor: $amountMinor,
                before: $before,
                after: $after,
                collection: $collection,
            ),
            $wasCompleted
                ? 'Reviewed Account Funding payment replayed'
                : 'Reviewed Account Funding paid',
        );

        return self::SUCCESS;
    }

    private function assertReviewedFundingRequestReadyForPayment(
        FundingRequest $request,
        Voucher $voucher,
    ): void {
        if (in_array($request->status, [
            FundingRequestStatus::PayCodeIssued,
            FundingRequestStatus::Completed,
        ], true)) {
            return;
        }

        $command = match ($request->status) {
            FundingRequestStatus::Submitted,
            FundingRequestStatus::UnderReview,
            FundingRequestStatus::NeedsInformation => sprintf(
                'php artisan x-change:funding:maker:verify %s',
                $voucher->code,
            ),
            FundingRequestStatus::AwaitingApproval => sprintf(
                'php artisan x-change:funding:checker:approve %s',
                $voucher->code,
            ),
            default => null,
        };

        if ($command !== null) {
            throw new RuntimeException(sprintf(
                'Pay Code %s is not ready for system payment. Next: %s',
                $voucher->code,
                $command,
            ));
        }

        throw new RuntimeException(sprintf(
            'Pay Code %s cannot be paid because its Funding Request is %s.',
            $voucher->code,
            $request->status->value,
        ));
    }

    private function rejectReviewedFundingOverrides(): void
    {
        $conflicts = collect([
            'amount',
            'recipient-mobile',
            'recipient-id',
            'connection',
            'reference',
            'expires-at',
            'evidence-reference',
            'authorization-reference',
        ])->filter(
            fn (string $option): bool => $this->optionalOption($option) !== null,
        )->values()->all();

        if ((bool) $this->option('bearer')) {
            $conflicts[] = 'bearer';
        }

        if ($conflicts !== []) {
            throw new RuntimeException(
                'Reviewed Pay Code mode derives its controls from the request; remove: --'
                .implode(', --', $conflicts).'.',
            );
        }
    }

    /**
     * @param  array{account_funding_reserve_minor: int, pay_code_reserve_minor: int}  $before
     * @param  array{account_funding_reserve_minor: int, pay_code_reserve_minor: int}  $after
     * @return array<string, mixed>
     */
    private function reviewedFundingPayload(
        string $mode,
        string $status,
        Voucher $voucher,
        FundingRequest $request,
        TreasuryProviderConnectionData $connection,
        int $amountMinor,
        array $before,
        array $after,
        ?VoucherCollection $collection = null,
    ): array {
        return [
            'schema' => 'x-change.reviewed-account-funding-pay-code-command.v1',
            'success' => ! in_array(
                $status,
                ['awaiting_review', 'rejected'],
                true,
            ),
            'mode' => $mode,
            'status' => $status,
            'pay_code' => [
                'voucher_id' => $voucher->getKey(),
                'code' => $voucher->code,
                'voucher_type' => $voucher->voucher_type->value,
                'state' => $voucher->state?->value,
            ],
            'funding_request' => [
                'reference' => $request->reference,
                'status' => $request->status->value,
                'evidence_reference' => $request->evidence_reference,
            ],
            'connection' => [
                'reference' => $connection->reference,
                'provider' => $connection->provider,
                'currency' => $connection->currency,
            ],
            'amount' => [
                'minor' => $amountMinor,
                'formatted' => $this->formatAmount(
                    $amountMinor,
                    $connection->decimalPlaces,
                ),
            ],
            'recipient' => [
                'mode' => 'requester',
                'type' => $request->requester_type,
                'id' => $request->requester_id,
            ],
            'positions' => [
                'before' => $before,
                'after' => $after,
            ],
            'collection' => $collection === null
                ? null
                : [
                    'id' => $collection->getKey(),
                    'status' => $collection->status,
                    'treasury_operation_reference' => $collection->treasury_operation_reference,
                ],
            'provider_calls' => false,
            'inventory_changed' => false,
            'accounting' => $mode === 'preview'
                ? 'No mutation; reviewed system Treasury payment preview only.'
                : 'Reserved system value moved once from Pay Code Reserve to requester Client Funds.',
        ];
    }

    private function prepareInteractiveDestination(
        TreasuryProviderConnectionCatalog $connections,
    ): void {
        if (
            ! $this->input->isInteractive()
            || $this->shouldOutputJson()
        ) {
            return;
        }

        if ($this->optionalOption('connection') === null) {
            $activeConnections = $connections->active();

            if ($activeConnections === []) {
                throw new RuntimeException(
                    'No active Treasury connection is available.',
                );
            }

            $references = array_map(
                static fn (
                    TreasuryProviderConnectionData $connection,
                ): string => $connection->reference,
                $activeConnections,
            );
            $selectedConnection = $this->choice(
                'Treasury connection',
                $references,
                0,
            );
            $this->input->setOption(
                'connection',
                (string) $selectedConnection,
            );
        }

        if (
            ! (bool) $this->option('bearer')
            && $this->optionalOption('recipient-mobile') === null
            && $this->optionalOption('recipient-id') === null
        ) {
            $recipientMode = $this->choice(
                'Recipient mode',
                ['Recipient-bound', 'Bearer'],
                0,
            );

            if ($recipientMode === 'Bearer') {
                $this->input->setOption('bearer', true);
            } else {
                $this->promptRequiredOption(
                    'recipient-mobile',
                    'Recipient verified mobile',
                );
            }
        }
    }

    private function prepareInteractiveIssuanceInput(
        ?Model $recipient,
    ): void {
        if (
            ! $this->input->isInteractive()
            || $this->shouldOutputJson()
        ) {
            return;
        }

        $this->promptRequiredOption(
            'amount',
            'Exact amount',
            (string) config(
                'x-change.funding.system_pay_codes.interactive_default_amount',
                '100.00',
            ),
        );
        $this->promptRequiredOption(
            'reference',
            'Idempotency reference',
            $this->generatedReference($recipient),
        );
        $this->promptRequiredOption(
            'expires-at',
            'Expiry (ISO-8601)',
            $this->resolveExpiry(
                $this->requiredOption('reference'),
            )->toIso8601String(),
        );

        if (! (bool) $this->option('commit')) {
            $this->input->setOption(
                'commit',
                $this->confirm(
                    'Reserve system funds and issue now?',
                    false,
                ),
            );
        }

        if ((bool) $this->option('commit')) {
            $this->promptRequiredOption(
                'evidence-reference',
                'Backing evidence reference',
            );
            $this->promptRequiredOption(
                'authorization-reference',
                'Authorization reference',
            );
        }

        if (
            $this->laravel->environment('production')
            && (bool) $this->option('commit')
            && ! (bool) $this->option('confirm-production')
        ) {
            $this->input->setOption(
                'confirm-production',
                $this->confirm(
                    'This is a production issuance. Continue?',
                    false,
                ),
            );
        }
    }

    private function resolveConnection(
        TreasuryProviderConnectionCatalog $connections,
    ): TreasuryProviderConnectionData {
        $reference = $this->optionalOption('connection');

        if ($reference !== null) {
            return collect($connections->active([$reference]))->sole();
        }

        $active = $connections->active();

        if (count($active) !== 1) {
            throw new RuntimeException(
                'Select one active Treasury connection with --connection.',
            );
        }

        return $active[0];
    }

    private function resolveRecipient(): ?Model
    {
        $bearer = (bool) $this->option('bearer');
        $recipientMobile = $this->optionalOption('recipient-mobile');
        $recipientId = $this->optionalOption('recipient-id');

        if (
            $bearer
            && ($recipientMobile !== null || $recipientId !== null)
        ) {
            throw new RuntimeException(
                'Use either one recipient selector or --bearer, not both.',
            );
        }

        if ($recipientMobile !== null && $recipientId !== null) {
            throw new RuntimeException(
                'Use either --recipient-mobile or --recipient-id, not both.',
            );
        }

        if ($bearer) {
            return null;
        }

        if ($recipientMobile === null && $recipientId === null) {
            throw new RuntimeException(
                'Recipient-bound issuance requires --recipient-mobile or --recipient-id.',
            );
        }

        $modelClass = config('x-change.onboarding.issuer_model')
            ?: config('auth.providers.users.model');

        if (
            ! is_string($modelClass)
            || $modelClass === ''
            || ! is_subclass_of($modelClass, Model::class)
        ) {
            throw new RuntimeException(
                'The configured Account owner model is invalid.',
            );
        }

        if ($recipientMobile !== null) {
            $mobile = MobileNumber::normalize($recipientMobile);

            if (
                ! is_string($mobile)
                || preg_match('/\A639\d{9}\z/', $mobile) !== 1
            ) {
                throw new RuntimeException(
                    'The Account Funding recipient mobile is invalid.',
                );
            }

            $candidates = array_values(array_unique([
                ...MobileNumber::candidates($mobile),
                '+'.$mobile,
            ]));
            $matches = $modelClass::query()
                ->whereIn('mobile', $candidates)
                ->limit(2)
                ->get();

            if ($matches->count() !== 1) {
                throw new RuntimeException(
                    'The verified Account Funding recipient could not be resolved uniquely.',
                );
            }

            $recipient = $matches->sole();

            if (
                ! $recipient instanceof Model
                || ! $recipient instanceof Authenticatable
                || $recipient->getAttribute('mobile_verified_at') === null
            ) {
                throw new RuntimeException(
                    'The Account Funding recipient mobile must be verified before issuance.',
                );
            }

            return $recipient;
        }

        $recipient = $modelClass::query()->find($recipientId);

        if (
            ! $recipient instanceof Model
            || ! $recipient instanceof Authenticatable
        ) {
            throw new RuntimeException(
                'The requested Account Funding recipient was not found.',
            );
        }

        return $recipient;
    }

    private function resolveExpiry(string $reference): Carbon
    {
        $existing = $this->findExisting($reference);
        $requestedExpiry = $this->optionalOption('expires-at');

        if ($requestedExpiry !== null) {
            return Carbon::parse($requestedExpiry);
        }

        if ($existing?->expires_at !== null) {
            return Carbon::parse(
                $existing->expires_at->toIso8601String(),
            );
        }

        $ttlSeconds = max(
            60,
            (int) config(
                'x-change.funding.system_pay_codes.default_ttl_seconds',
                604800,
            ),
        );

        return Carbon::parse(
            now()->addSeconds($ttlSeconds)->toIso8601String(),
        );
    }

    private function findExisting(
        string $reference,
    ): ?SystemAccountFundingPayCodeIssuance {
        return SystemAccountFundingPayCodeIssuance::query()
            ->with('voucher')
            ->where(
                'idempotency_reference_hash',
                hash('sha256', trim($reference)),
            )
            ->first();
    }

    /**
     * @return array{account_funding_reserve_minor: int, pay_code_reserve_minor: int}
     */
    private function positionBalances(
        TreasuryPositionReadModelContract $positions,
        string $principalReference,
        TreasuryProviderConnectionData $connection,
    ): array {
        $matching = array_values(array_filter(
            $positions->forPrincipal($principalReference),
            static fn (TreasuryPositionData $position): bool => $position->status === 'active'
                && $position->provider === $connection->provider
                && $position->currency === $connection->currency
                && $position->connectionReference === $connection->reference,
        ));

        return [
            'account_funding_reserve_minor' => $this->balanceForPurpose(
                $matching,
                TreasuryPositionPurpose::AccountFundingReserve,
            ),
            'pay_code_reserve_minor' => $this->balanceForPurpose(
                $matching,
                TreasuryPositionPurpose::PayCodeReserve,
            ),
        ];
    }

    /**
     * @param  list<TreasuryPositionData>  $positions
     */
    private function balanceForPurpose(
        array $positions,
        TreasuryPositionPurpose $purpose,
    ): int {
        return collect($positions)->first(
            static fn (TreasuryPositionData $position): bool => $position->purpose === $purpose,
        )?->balanceMinor ?? 0;
    }

    /**
     * @param  array{account_funding_reserve_minor: int, pay_code_reserve_minor: int}  $before
     * @param  array{account_funding_reserve_minor: int, pay_code_reserve_minor: int}  $after
     * @return array<string, mixed>
     */
    private function payload(
        string $mode,
        string $status,
        TreasuryProviderConnectionData $connection,
        int $amountMinor,
        string $reference,
        CarbonInterface $expiresAt,
        ?Model $recipient,
        bool $bearer,
        array $before,
        array $after,
        ?SystemAccountFundingPayCodeIssuance $issuance = null,
    ): array {
        $voucher = $issuance?->voucher;

        return [
            'schema' => 'x-change.system-account-funding-pay-code-command.v1',
            'success' => ! in_array(
                $status,
                ['insufficient_system_funds', 'rejected'],
                true,
            ),
            'mode' => $mode,
            'status' => $status,
            'reference' => $reference,
            'connection' => [
                'reference' => $connection->reference,
                'provider' => $connection->provider,
                'currency' => $connection->currency,
            ],
            'amount' => [
                'minor' => $amountMinor,
                'formatted' => $this->formatAmount(
                    $amountMinor,
                    $connection->decimalPlaces,
                ),
            ],
            'recipient' => [
                'mode' => $bearer ? 'bearer' : 'bound',
                'id' => $recipient?->getKey(),
            ],
            'expires_at' => $expiresAt->toIso8601String(),
            'positions' => [
                'before' => $before,
                'after' => $after,
            ],
            'pay_code' => $voucher instanceof Voucher
                ? [
                    'issuance_reference' => $issuance?->reference,
                    'voucher_id' => $voucher->getKey(),
                    'code' => $voucher->code,
                    'claim_url' => Route::has('x-change.claim.show')
                        ? route(
                            'x-change.claim.show',
                            ['code' => $voucher->code],
                        )
                        : null,
                ]
                : null,
            'provider_calls' => false,
            'inventory_changed' => false,
            'accounting' => $mode === 'preview'
                ? 'No mutation; proposed Account Funding Reserve to Pay Code Reserve transfer.'
                : 'System Account Funding Reserve reserved once in Pay Code Reserve.',
        ];
    }

    private function parseAmount(
        string $amount,
        int $decimalPlaces,
    ): int {
        $amount = trim($amount);
        $pattern = $decimalPlaces === 0
            ? '/^\d+$/'
            : '/^\d+(?:\.\d{1,'.$decimalPlaces.'})?$/';

        if (preg_match($pattern, $amount) !== 1) {
            throw new RuntimeException(
                "Amount must be a positive value with at most {$decimalPlaces} decimal places.",
            );
        }

        [$whole, $fraction] = array_pad(
            explode('.', $amount, 2),
            2,
            '',
        );
        $factor = 10 ** $decimalPlaces;

        if (strlen($whole) > 12) {
            throw new RuntimeException('Amount is too large.');
        }

        $minor = ((int) $whole * $factor)
            + (int) str_pad($fraction, $decimalPlaces, '0');

        if ($minor <= 0) {
            throw new RuntimeException('Amount must be greater than zero.');
        }

        return $minor;
    }

    private function formatAmount(
        int $amountMinor,
        int $decimalPlaces,
    ): string {
        return number_format(
            $amountMinor / (10 ** $decimalPlaces),
            $decimalPlaces,
            '.',
            '',
        );
    }

    private function requiredOption(string $name): string
    {
        $value = $this->optionalOption($name);

        if ($value === null) {
            throw new RuntimeException(
                "The --{$name} option is required.",
            );
        }

        return $value;
    }

    private function optionalOption(string $name): ?string
    {
        $value = trim((string) $this->option($name));

        return $value === '' ? null : $value;
    }

    private function promptRequiredOption(
        string $name,
        string $question,
        ?string $default = null,
    ): void {
        if ($this->optionalOption($name) !== null) {
            return;
        }

        $answer = trim((string) $this->ask($question, $default));
        $value = $answer !== '' ? $answer : trim((string) $default);

        if ($value !== '') {
            $this->input->setOption($name, $value);
        }
    }

    private function promptOptionalOption(
        string $name,
        string $question,
    ): void {
        if ($this->optionalOption($name) !== null) {
            return;
        }

        $answer = trim((string) $this->ask($question));

        if ($answer !== '') {
            $this->input->setOption($name, $answer);
        }
    }

    private function generatedReference(?Model $recipient): string
    {
        $recipient = (bool) $this->option('bearer')
            ? 'bearer'
            : 'user-'.$recipient?->getKey();

        return sprintf(
            'account-funding-%s-%s-%s',
            $recipient,
            now()->format('Ymd-His'),
            Str::lower((string) Str::ulid()),
        );
    }
}
