import type {
    CockpitDistributionAction,
    CockpitDistributionChannel,
    CockpitDistributionMetric,
    CockpitPrintTemplate,
    CockpitShareAsset,
} from './types';

export const cockpitDistributionChannels: CockpitDistributionChannel[] = [
    {
        key: 'sms',
        label: 'SMS handoff',
        status: 'Planning only',
        helper: 'SMS delivery must be routed through x-feedback after explicit host wiring.',
    },
    {
        key: 'email',
        label: 'Email handoff',
        status: 'Planning only',
        helper: 'Email delivery state is not inferred from this workspace.',
    },
    {
        key: 'in-app',
        label: 'In-app notification handoff',
        status: 'Planning only',
        helper: 'In-app notification state remains x-feedback presentation state.',
    },
    {
        key: 'manual',
        label: 'Manual branch release',
        status: 'Planning only',
        helper: 'Manual release requires authorized host workflows before use.',
    },
];

export const cockpitPrintTemplates: CockpitPrintTemplate[] = [
    {
        key: 'receipt-card',
        label: 'Receipt card',
        format: 'A6 print placeholder',
        helper: 'Print assets are not generated or persisted in Slice 6.',
    },
    {
        key: 'branch-sheet',
        label: 'Branch release sheet',
        format: 'A4 print placeholder',
        helper: 'Bulk printing must wait for explicit distribution and campaign APIs.',
    },
    {
        key: 'counter-slip',
        label: 'Counter slip',
        format: 'Thermal print placeholder',
        helper: 'Printer integration is outside this frontend foundation.',
    },
];

export const cockpitShareAssets: CockpitShareAsset[] = [
    {
        key: 'qr',
        label: 'QR asset',
        value: 'Deferred',
        helper: 'QR generation must use an approved Pay Code representation later.',
    },
    {
        key: 'short-link',
        label: 'Short link',
        value: 'Deferred',
        helper: 'Short-link creation requires host routing, expiration, and redaction policy.',
    },
    {
        key: 'copy-text',
        label: 'Copy text',
        value: 'Preview only',
        helper: 'Copy text must not expose secret claim material without policy checks.',
    },
];

export const cockpitDistributionMetrics: CockpitDistributionMetric[] = [
    {
        key: 'planned',
        label: 'Planned sends',
        value: '0',
        helper: 'No distribution plan has been persisted.',
    },
    {
        key: 'printed',
        label: 'Printed assets',
        value: '0',
        helper: 'Print output is not generated in this slice.',
    },
    {
        key: 'delivery-state',
        label: 'Delivery state',
        value: 'Not wired',
        helper: 'Delivery truth must come from x-feedback records later.',
    },
    {
        key: 'campaign-state',
        label: 'Campaign state',
        value: 'Out of scope',
        helper: 'Campaign behavior is deferred until Wave 5.',
    },
];

export const cockpitDistributionActions: CockpitDistributionAction[] = [
    {
        key: 'send-now',
        label: 'Send now',
        disabled: true,
        reason: 'Distribution dispatch is not part of Cockpit Slice 6.',
    },
    {
        key: 'generate-print',
        label: 'Generate print assets',
        disabled: true,
        reason: 'Print artifact generation requires explicit host services.',
    },
    {
        key: 'create-qr',
        label: 'Create QR',
        disabled: true,
        reason: 'QR generation must use approved Pay Code representation and policy.',
    },
    {
        key: 'create-campaign',
        label: 'Create campaign',
        disabled: true,
        reason: 'Campaign behavior is deferred until Wave 5.',
    },
];
