import type {
    CockpitPayCodeExplorerFilter,
    CockpitPayCodeExplorerRecord,
    CockpitPayCodeRowAction,
} from './types';

export const cockpitPayCodeExplorerFilters: CockpitPayCodeExplorerFilter[] = [
    {
        key: 'status',
        label: 'Status',
        value: 'All lifecycle states',
        helper: 'Filtering is presentation-only until a host query API is wired.',
    },
    {
        key: 'template',
        label: 'Template',
        value: 'All templates',
        helper: 'Template ownership remains in existing product services.',
    },
    {
        key: 'risk',
        label: 'Risk',
        value: 'Attention signals pending',
        helper: 'Risk computation is not inferred in the frontend.',
    },
];

export const cockpitPayCodeExplorerRecords: CockpitPayCodeExplorerRecord[] = [
    {
        code: 'PC-READY-001',
        template: 'Money Changer',
        amount: '₱5,000.00',
        status: 'Issued',
        owner: 'Branch Operations',
        lastActivity: 'Read model placeholder',
    },
    {
        code: 'PC-PENDING-002',
        template: 'OFW Remittance',
        amount: '₱12,500.00',
        status: 'Awaiting Claim',
        owner: 'Treasury Operations',
        lastActivity: 'Journal redaction pending',
    },
    {
        code: 'PC-SETTLE-003',
        template: 'Settlement Envelope',
        amount: 'Pending',
        status: 'Readiness Pending',
        owner: 'Settlement Desk',
        lastActivity: 'Gateway binding deferred',
    },
];

export const cockpitPayCodeRowActions: CockpitPayCodeRowAction[] = [
    {
        key: 'view',
        label: 'View details',
        enabled: false,
        disabled: true,
        reason: 'Voucher detail foundation is a later slice.',
    },
    {
        key: 'timeline',
        label: 'Open timeline',
        enabled: false,
        disabled: true,
        reason: 'Journal visibility and redaction are not wired in Slice 4.',
    },
    {
        key: 'notify',
        label: 'Notify recipient',
        enabled: false,
        disabled: true,
        reason: 'Feedback delivery must be explicitly routed through x-feedback later.',
    },
];
