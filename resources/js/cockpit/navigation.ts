import type { CockpitNavigationItem } from './types';

export const cockpitNavigationItems: CockpitNavigationItem[] = [
    {
        key: 'dashboard',
        label: 'Cockpit',
        href: '/x/cockpit',
        group: 'primary',
        description: 'Operational awareness',
    },
    {
        key: 'quick-generate',
        label: 'Create',
        href: '/x/cockpit/quick-generate',
        group: 'primary',
        description: 'Design and issue a Pay Code',
    },
    {
        key: 'funding',
        label: 'Funding',
        href: '/x/cockpit/funding',
        group: 'primary',
        description: 'Liquidity and funding state',
    },
    {
        key: 'pay-codes',
        label: 'Pay Codes',
        href: '/x/cockpit/pay-codes',
        group: 'primary',
        description: 'Pay Code explorer',
    },
    {
        key: 'campaigns',
        label: 'Campaigns',
        href: '/x/cockpit/campaigns',
        group: 'primary',
        description: 'Payments to many recipients',
    },
    {
        key: 'accounts',
        label: 'Accounts',
        href: '/x/cockpit/accounts',
        group: 'secondary',
        description: 'Provider funding destinations',
    },
    {
        key: 'runtime-profile',
        label: 'System Readiness',
        href: '/x/cockpit/diagnostics/runtime-profile',
        group: 'secondary',
        description: 'Deployment and operational readiness',
    },
    {
        key: 'documentation',
        label: 'Documentation',
        href: '/x/cockpit/documentation',
        group: 'secondary',
        description: 'Use, operations, and deployment guides',
    },
];

export const cockpitPrimaryNavigation = cockpitNavigationItems.filter(
    (item) => item.group === 'primary',
);

export const cockpitSecondaryNavigation = cockpitNavigationItems.filter(
    (item) => item.group === 'secondary',
);
