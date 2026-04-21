<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Lifecycle\Http\Requests\Issuers;

use LBHurtado\XChange\Http\Requests\Onboarding\OpenIssuerWalletRequest as LegacyOpenIssuerWalletRequest;

/**
 * Lifecycle alias/wrapper for the existing wallet opening request.
 *
 * Extending the legacy request preserves current validation behavior.
 */
class CreateIssuerWalletRequest extends LegacyOpenIssuerWalletRequest
{
}
