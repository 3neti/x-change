<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Enums;

enum StandingFundingFailureClassification: string
{
    case ProviderTransient = 'provider_transient';
    case ProviderThrottled = 'provider_throttled';
    case ConfigurationPermanent = 'configuration_permanent';
    case DatabaseResourceExhausted = 'database_resource_exhausted';
    case DatabaseUnavailable = 'database_unavailable';
    case AmbiguousAfterProviderCall = 'ambiguous_after_provider_call';
    case Unexpected = 'unexpected';
}
