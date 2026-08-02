<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Enums;

enum PublicationOverwritePolicy: string
{
    case AlwaysGenerated = 'always_generated';
    case CreateIfMissing = 'create_if_missing';
    case ExplicitForceOnly = 'explicit_force_only';
}
