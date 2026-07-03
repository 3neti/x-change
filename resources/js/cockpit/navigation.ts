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
        label: 'Quick Generate',
        href: '/x/cockpit/quick-generate',
        group: 'primary',
        description: 'Template-first issuance',
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
        key: 'templates',
        label: 'Templates',
        href: '/x/cockpit/templates',
        group: 'primary',
        description: 'Institutional products',
    },
    {
        key: 'contacts',
        label: 'Contacts',
        href: '/x/cockpit/contacts',
        group: 'primary',
        description: 'People and counterparties',
    },
    {
        key: 'operations',
        label: 'Operations',
        href: '/x/cockpit/operations',
        group: 'primary',
        badge: 'Monitor',
        description: 'Execution and exceptions',
    },
    {
        key: 'reports',
        label: 'Reports',
        href: '/x/cockpit/reports',
        group: 'primary',
        description: 'Operational reporting',
    },
    {
        key: 'approvals',
        label: 'Approvals',
        href: '/x/cockpit/approvals',
        group: 'secondary',
        badge: 'CTA',
        description: 'Human approval queues',
    },
    {
        key: 'administration',
        label: 'Administration',
        href: '/x/cockpit/administration',
        group: 'secondary',
        description: 'Configuration and controls',
    },
];

export const cockpitPrimaryNavigation = cockpitNavigationItems.filter(
    (item) => item.group === 'primary',
);

export const cockpitSecondaryNavigation = cockpitNavigationItems.filter(
    (item) => item.group === 'secondary',
);

