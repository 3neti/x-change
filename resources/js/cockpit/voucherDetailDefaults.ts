import type {
    CockpitVoucherAuditItem,
    CockpitVoucherDetailAction,
    CockpitVoucherDistributionItem,
    CockpitVoucherEvidenceItem,
    CockpitVoucherOverviewItem,
    CockpitVoucherTimelineItem,
} from './types';

export const cockpitVoucherOverviewItems: CockpitVoucherOverviewItem[] = [
    {
        key: 'pay-code',
        label: 'Pay Code',
        value: 'PC-READY-001',
        helper: 'Placeholder from the Pay Code Explorer read-model row.',
    },
    {
        key: 'template',
        label: 'Template',
        value: 'Money Changer',
        helper: 'Template behavior remains owned by existing x-change services.',
    },
    {
        key: 'amount',
        label: 'Amount',
        value: '₱5,000.00',
        helper: 'Funding and settlement truth are not computed in the frontend.',
    },
    {
        key: 'status',
        label: 'Status',
        value: 'Issued',
        helper: 'Lifecycle state must come from host read models later.',
    },
    {
        key: 'execution-id',
        label: 'Execution ID',
        value: 'Deferred',
        helper: 'Execution correlation is displayed only after a host read model exists.',
    },
    {
        key: 'owner',
        label: 'Owner',
        value: 'Branch Operations',
        helper: 'Operator visibility and redaction remain future host concerns.',
    },
];

export const cockpitVoucherTimelineItems: CockpitVoucherTimelineItem[] = [
    {
        id: 'issued',
        label: 'Issued',
        description: 'Voucher issuance fact placeholder.',
        timestamp: 'Read model pending',
        source: 'system',
    },
    {
        id: 'claim-started',
        label: 'Claim started',
        description: 'Claim lifecycle fact placeholder.',
        timestamp: 'Journal visibility pending',
        source: 'journal',
    },
    {
        id: 'execution-outcome',
        label: 'Execution outcome',
        description: 'Execution result placeholder; no driver is invoked here.',
        timestamp: 'Execution read model pending',
        source: 'execution',
    },
    {
        id: 'feedback-status',
        label: 'Feedback status',
        description: 'Feedback delivery status placeholder.',
        timestamp: 'Delivery read model pending',
        source: 'feedback',
    },
];

export const cockpitVoucherEvidenceItems: CockpitVoucherEvidenceItem[] = [
    {
        id: 'identity',
        label: 'Identity evidence',
        status: 'Deferred',
        helper: 'Claim evidence requires authorized host read models.',
    },
    {
        id: 'location',
        label: 'Location evidence',
        status: 'Deferred',
        helper: 'Location facts must be redacted before operator display.',
    },
    {
        id: 'settlement-envelope',
        label: 'Settlement envelope evidence',
        status: 'Deferred',
        helper: 'Settlement Envelope remains a readiness participant, not the engine.',
    },
];

export const cockpitVoucherDistributionItems: CockpitVoucherDistributionItem[] = [
    {
        id: 'sms',
        channel: 'SMS',
        status: 'Pending read model',
        helper: 'SMS state should come from x-feedback delivery records later.',
    },
    {
        id: 'email',
        channel: 'Email',
        status: 'Pending read model',
        helper: 'Email state should come from x-feedback delivery records later.',
    },
    {
        id: 'in-app',
        channel: 'In-app',
        status: 'Pending read model',
        helper: 'In-app notification state remains x-feedback presentation state.',
    },
];

export const cockpitVoucherAuditItems: CockpitVoucherAuditItem[] = [
    {
        id: 'journal',
        label: 'Journal read model',
        status: 'Not wired',
        helper: 'Cockpit must compose with x-journal visibility and redaction later.',
    },
    {
        id: 'actions',
        label: 'Action handoff',
        status: 'Not wired',
        helper: 'x-action can describe next steps but does not execute money.',
    },
    {
        id: 'provider-callbacks',
        label: 'Provider callbacks',
        status: 'Not wired',
        helper: 'Provider facts must not become settlement truth without domain processing.',
    },
];

export const cockpitVoucherDetailActions: CockpitVoucherDetailAction[] = [
    {
        key: 'open-claim',
        label: 'Open claim flow',
        disabled: true,
        reason: 'Claim UX wiring is not part of Cockpit Slice 5.',
    },
    {
        key: 'resend-feedback',
        label: 'Resend feedback',
        disabled: true,
        reason: 'Feedback delivery must be explicitly routed through x-feedback later.',
    },
    {
        key: 'reconcile',
        label: 'Reconcile',
        disabled: true,
        reason: 'Reconciliation commands require existing host APIs and authorization.',
    },
    {
        key: 'cancel',
        label: 'Cancel voucher',
        disabled: true,
        reason: 'Voucher mutation is prohibited in this foundation slice.',
    },
];
