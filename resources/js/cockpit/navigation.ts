import type { CockpitNavigationItem } from './types';

export const cockpitNavigationItems: CockpitNavigationItem[] = [
    {
        key: 'funding',
        label: 'Funding',
        href: '/x/cockpit/funding',
        group: 'primary',
        description: 'Add and confirm funds',
    },
    {
        key: 'quick-generate',
        label: 'Issuance',
        href: '/x/cockpit/quick-generate',
        group: 'primary',
        description: 'Design and issue a Pay Code',
    },
    {
        key: 'campaigns',
        label: 'Campaigns',
        href: '/x/cockpit/campaigns',
        group: 'primary',
        description: 'Issue to many recipients',
    },
    {
        key: 'pay-codes',
        label: 'Pay Codes',
        href: '/x/cockpit/pay-codes',
        group: 'primary',
        description: 'Find and manage Pay Codes',
    },
    {
        key: 'dashboard',
        label: 'Overview',
        href: '/x/cockpit/overview',
        group: 'primary',
        description: 'Funds, capacity, and activity',
    },
    {
        key: 'accounts',
        label: 'Account',
        href: '/x/cockpit/accounts',
        group: 'secondary',
        description: 'Position and connected services',
    },
    {
        key: 'runtime-profile',
        label: 'System Readiness',
        href: '/x/cockpit/diagnostics/runtime-profile',
        group: 'secondary',
        description: 'Deployment and runtime checks',
    },
    {
        key: 'documentation',
        label: 'Guides',
        href: '/x/cockpit/documentation',
        group: 'secondary',
        description: 'Workflows and terminology',
    },
];

export const cockpitPrimaryNavigation = cockpitNavigationItems.filter(
    (item) => item.group === 'primary',
);

const cockpitMobileNavigationOrder = [
    'funding',
    'quick-generate',
    'dashboard',
    'pay-codes',
    'campaigns',
] as const;

export const cockpitMobilePrimaryNavigation =
    cockpitMobileNavigationOrder.flatMap((key) => {
        const item = cockpitPrimaryNavigation.find(
            (candidate) => candidate.key === key,
        );

        return item ? [item] : [];
    });

export const cockpitSecondaryNavigation = cockpitNavigationItems.filter(
    (item) => item.group === 'secondary',
);
