<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Lifecycle\Http\Requests\Issuers;

use LBHurtado\XChange\Http\Requests\Onboarding\OnboardIssuerRequest as LegacyOnboardIssuerRequest;

/**
 * Lifecycle alias/wrapper for the existing onboarding request.
 *
 * Extending the legacy request preserves current validation behavior.
 */
class CreateIssuerRequest extends LegacyOnboardIssuerRequest
{
}
