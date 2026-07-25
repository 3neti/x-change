<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Treasury;

use Illuminate\Contracts\Foundation\Application;
use LBHurtado\XChange\Contracts\TreasuryOpeningCapitalizationAuthorizationContract;
use LBHurtado\XChange\Data\Treasury\TreasuryOpeningCapitalizationAuthorizationData;
use LBHurtado\XChange\Exceptions\TreasuryConfigurationException;

final readonly class ConfigTreasuryOpeningCapitalizationAuthorization implements TreasuryOpeningCapitalizationAuthorizationContract
{
    public function __construct(
        private Application $application,
    ) {}

    public function authorize(
        TreasuryOpeningCapitalizationAuthorizationData $request,
    ): void {
        $allowedConnections = array_values(array_filter(array_map(
            static fn (mixed $reference): string => trim((string) $reference),
            (array) config(
                'x-change.treasury.opening_capitalization.allowed_connections',
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
            throw new TreasuryConfigurationException(
                'The selected Treasury connection is not authorized for opening capitalization.',
            );
        }

        if (! $request->commit) {
            return;
        }

        if (! $request->systemOwnershipConfirmed) {
            throw new TreasuryConfigurationException(
                'Opening capitalization requires explicit confirmation of system ownership.',
            );
        }

        $authorizationReference = trim($request->authorizationReference);

        if (
            $authorizationReference === ''
            || mb_strlen($authorizationReference) > 191
            || preg_match(
                '/[\x00-\x1F\x7F]/',
                $authorizationReference,
            ) === 1
        ) {
            throw new TreasuryConfigurationException(
                'Opening capitalization requires a valid authorization reference.',
            );
        }

        if (
            $this->application->environment('production')
            && ! (bool) config(
                'x-change.treasury.opening_capitalization.allow_production',
                false,
            )
        ) {
            throw new TreasuryConfigurationException(
                'Production Treasury opening capitalization is disabled.',
            );
        }
    }
}
