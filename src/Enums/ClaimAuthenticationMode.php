<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Enums;

enum ClaimAuthenticationMode: string
{
    case None = 'none';
    case AuthenticatedOfficer = 'authenticated_officer';
    case ClaimantHandoff = 'claimant_handoff';
}
