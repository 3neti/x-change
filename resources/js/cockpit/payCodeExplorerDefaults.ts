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
        helper: 'Current list includes every lifecycle state.',
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
        helper: 'Risk signals come from the sanitized read model only.',
    },
];

export const cockpitPayCodeExplorerRecords: CockpitPayCodeExplorerRecord[] = [
    {
        code: 'PC-READY-001',
        template: 'Money Changer',
        capability: {
            key: 'disbursement',
            label: 'Disbursement',
            voucherTypeLabel: 'Redeemable',
        },
        instructionBadges: [
            { key: 'mobile_bound', label: 'Mobile-bound' },
            { key: 'settlement_rail', label: 'InstaPay' },
        ],
        amount: '₱5,000.00',
        status: 'Issued',
        party: {
            state: 'targeted',
            label: 'For',
            primary: '•••• 1987',
            secondary: null,
            masked: true,
        },
        timing: {
            createdAt: null,
            startsAt: null,
            expiresAt: null,
            redeemedAt: null,
        },
        owner: 'Branch Operations',
        lastActivity: 'Read model placeholder',
    },
    {
        code: 'PC-PENDING-002',
        template: 'OFW Remittance',
        capability: {
            key: 'collection',
            label: 'Collection',
            voucherTypeLabel: 'Payable',
        },
        instructionBadges: [{ key: 'vendor_bound', label: 'Vendor-bound' }],
        amount: '₱12,500.00',
        status: 'Awaiting Claim',
        party: {
            state: 'targeted',
            label: 'Vendor',
            primary: 'REMIT-HUB',
            secondary: null,
            masked: false,
        },
        timing: {
            createdAt: null,
            startsAt: null,
            expiresAt: null,
            redeemedAt: null,
        },
        owner: 'Treasury Operations',
        lastActivity: 'Journal redaction pending',
    },
    {
        code: 'PC-SETTLE-003',
        template: 'Settlement Envelope',
        capability: {
            key: 'settlement',
            label: 'Settlement',
            voucherTypeLabel: 'Bidirectional',
        },
        instructionBadges: [
            { key: 'otp', label: 'OTP' },
            { key: 'signature', label: 'Signature' },
        ],
        amount: 'Pending',
        status: 'Readiness Pending',
        party: {
            state: 'open',
            label: 'Availability',
            primary: 'Open claim',
            secondary: null,
            masked: false,
        },
        timing: {
            createdAt: null,
            startsAt: null,
            expiresAt: null,
            redeemedAt: null,
        },
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
