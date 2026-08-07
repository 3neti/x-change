<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Enums;

enum CommercialGovernanceState: string
{
    case BaselineActiveChangesLocked = 'baseline_active_changes_locked';
    case RolesReady = 'roles_ready';
    case RevisionAwaitingApproval = 'revision_awaiting_approval';
    case PublishedAwaitingActivation = 'published_awaiting_activation';
    case GovernedOfferingActive = 'governed_offering_active';
    case ConfigurationInvalid = 'configuration_invalid';
}
