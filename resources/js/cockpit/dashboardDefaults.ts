import type {
    CockpitActivityItem,
    CockpitDashboardMetric,
    CockpitPipelineStage,
    CockpitRiskSignal,
} from './types';

export const cockpitDashboardMetrics: CockpitDashboardMetric[] = [
    {
        key: 'internal-balance',
        label: 'Internal Balance',
        value: 'Pending wallet read model',
        helper: 'Read-only placeholder',
        tone: 'neutral',
    },
    {
        key: 'live-balance',
        label: 'Live Balance',
        value: 'Pending provider read model',
        helper: 'No provider call in Slice 2',
        tone: 'neutral',
    },
    {
        key: 'reserved-funds',
        label: 'Reserved Funds',
        value: 'Pending reservation model',
        helper: 'No money movement',
        tone: 'warning',
    },
    {
        key: 'available-to-issue',
        label: 'Available To Issue',
        value: 'Pending funding policy',
        helper: 'Presentation only',
        tone: 'healthy',
    },
];

export const cockpitRedemptionPipelineStages: CockpitPipelineStage[] = [
    { key: 'issued', label: 'Issued', value: '—', tone: 'neutral' },
    { key: 'shared', label: 'Shared', value: '—', tone: 'neutral' },
    { key: 'opened', label: 'Opened', value: '—', tone: 'neutral' },
    { key: 'claim-started', label: 'Claim Started', value: '—', tone: 'neutral' },
    { key: 'redeemed', label: 'Redeemed', value: '—', tone: 'healthy' },
    { key: 'disbursed', label: 'Disbursed', value: '—', tone: 'healthy' },
    { key: 'reconciled', label: 'Reconciled', value: '—', tone: 'neutral' },
];

export const cockpitRiskSignals: CockpitRiskSignal[] = [
    {
        key: 'expiring-today',
        label: 'Expiring Today',
        value: 'Pending expiry read model',
        severity: 'watch',
    },
    {
        key: 'funding-runway',
        label: 'Funding Runway',
        value: 'Pending liquidity forecast',
        severity: 'warning',
    },
    {
        key: 'stuck-settlements',
        label: 'Stuck Settlements',
        value: 'Pending settlement read model',
        severity: 'critical',
    },
];

export const cockpitRecentActivityItems: CockpitActivityItem[] = [
    {
        id: 'execution-read-model',
        label: 'Execution activity',
        description: 'Execution outcome summaries require host read-model wiring.',
        timestamp: 'Deferred',
        source: 'execution',
    },
    {
        id: 'journal-read-model',
        label: 'Journal activity',
        description: 'Journal facts require authorization and redaction before display.',
        timestamp: 'Deferred',
        source: 'journal',
    },
    {
        id: 'feedback-read-model',
        label: 'Feedback activity',
        description: 'Delivery status is communication state, not lifecycle truth.',
        timestamp: 'Deferred',
        source: 'feedback',
    },
];

