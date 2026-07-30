import type { CockpitNavigationItem } from './types';

export const cockpitNavigationItems: CockpitNavigationItem[] = [
    {
        key: 'dashboard',
        label: 'Dashboard',
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
        key: 'templates',
        label: 'Templates',
        href: '/x/cockpit/templates',
        group: 'primary',
        description: 'Institutional products',
        enabled: false,
        disabledLabel: 'Coming soon',
        disabledReason: 'Template Cockpit route has not been implemented yet.',
    },
    {
        key: 'contacts',
        label: 'Contacts',
        href: '/x/cockpit/contacts',
        group: 'primary',
        description: 'People and counterparties',
        enabled: false,
        disabledLabel: 'Coming soon',
        disabledReason: 'Contacts Cockpit route has not been implemented yet.',
    },
    {
        key: 'operations',
        label: 'Operations',
        href: '/x/cockpit/operations',
        group: 'primary',
        description: 'Execution and exceptions',
        enabled: false,
        disabledLabel: 'Coming soon',
        disabledReason:
            'Operations Cockpit route has not been implemented yet.',
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
        label: 'Runtime Profile',
        href: '/x/cockpit/diagnostics/runtime-profile',
        group: 'secondary',
        description: 'Read-only handoff configuration',
    },
    {
        key: 'reports',
        label: 'Reports',
        href: '/x/cockpit/reports',
        group: 'primary',
        description: 'Operational reporting',
        enabled: false,
        disabledLabel: 'Coming soon',
        disabledReason: 'Reports Cockpit route has not been implemented yet.',
    },
    {
        key: 'approvals',
        label: 'Approvals',
        href: '/x/cockpit/approvals',
        group: 'secondary',
        description: 'Human approval queues',
        enabled: false,
        disabledLabel: 'Coming soon',
        disabledReason: 'Approvals Cockpit route has not been implemented yet.',
    },
    {
        key: 'administration',
        label: 'Administration',
        href: '/x/cockpit/administration',
        group: 'secondary',
        description: 'Configuration and controls',
        enabled: false,
        disabledLabel: 'Coming soon',
        disabledReason:
            'Administration Cockpit route has not been implemented yet.',
    },
];

export const cockpitPrimaryNavigation = cockpitNavigationItems.filter(
    (item) => item.group === 'primary',
);

export const cockpitSecondaryNavigation = cockpitNavigationItems.filter(
    (item) => item.group === 'secondary',
);
