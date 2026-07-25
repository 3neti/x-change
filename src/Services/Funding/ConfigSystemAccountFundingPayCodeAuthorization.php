<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Funding;

use Illuminate\Contracts\Foundation\Application;
use LBHurtado\XChange\Contracts\SystemAccountFundingPayCodeAuthorizationContract;
use LBHurtado\XChange\Data\Funding\SystemAccountFundingPayCodeAuthorizationData;
use RuntimeException;

final readonly class ConfigSystemAccountFundingPayCodeAuthorization implements SystemAccountFundingPayCodeAuthorizationContract
{
    public function __construct(
        private Application $application,
    ) {}

    public function authorize(
        SystemAccountFundingPayCodeAuthorizationData $request,
    ): void {
        $maximumAmountMinor = max(
            1,
            (int) config(
                'x-change.funding.system_pay_codes.maximum_amount_minor',
                5_000_000,
            ),
        );

        if ($request->amountMinor > $maximumAmountMinor) {
            throw new RuntimeException(
                'The requested amount exceeds the configured System Account Funding Pay Code limit.',
            );
        }

        $allowedConnections = array_values(array_filter(array_map(
            static fn (mixed $reference): string => trim((string) $reference),
            (array) config(
                'x-change.funding.system_pay_codes.allowed_connections',
                [],
            ),
        )));

        if (
            $allowedConnections !== []
            && ! in_array(
                $request->connectionReference,
                $allowedConnections,
                true,
            )
        ) {
            throw new RuntimeException(
                'The selected Treasury connection is not authorized for System Account Funding Pay Codes.',
            );
        }

        if (
            $request->bearer
            && ! (bool) config(
                'x-change.funding.system_pay_codes.bearer_enabled',
                false,
            )
        ) {
            throw new RuntimeException(
                'Bearer System Account Funding Pay Codes are disabled.',
            );
        }

        if (! $request->commit) {
            return;
        }

        if (! (bool) config(
            'x-change.funding.system_pay_codes.enabled',
            ! $this->application->environment('production'),
        )) {
            throw new RuntimeException(
                'System Account Funding Pay Code issuance is disabled.',
            );
        }

        if (! $this->application->environment('production')) {
            return;
        }

        if (! (bool) config(
            'x-change.funding.system_pay_codes.allow_production',
            false,
        )) {
            throw new RuntimeException(
                'Production System Account Funding Pay Code issuance is disabled.',
            );
        }

        if (! $request->productionConfirmed) {
            throw new RuntimeException(
                'Production issuance requires --confirm-production.',
            );
        }

        if ($request->bearer) {
            throw new RuntimeException(
                'Production System Account Funding Pay Codes must be recipient-bound.',
            );
        }

        if (
            trim((string) $request->evidenceReference) === ''
            || trim((string) $request->authorizationReference) === ''
        ) {
            throw new RuntimeException(
                'Production issuance requires evidence and authorization references.',
            );
        }
    }
}
