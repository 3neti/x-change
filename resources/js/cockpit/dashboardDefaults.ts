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
        value: 'Summary not connected',
        helper: 'Waiting for balance source',
        tone: 'neutral',
    },
    {
        key: 'live-balance',
        label: 'Live Balance',
        value: 'Provider not connected',
        helper: 'No provider call from dashboard',
        tone: 'neutral',
    },
    {
        key: 'reserved-funds',
        label: 'Reserved Funds',
        value: 'Reservation summary not connected',
        helper: 'No money movement',
        tone: 'warning',
    },
    {
        key: 'available-to-issue',
        label: 'Available To Issue',
        value: 'Funding policy not connected',
        helper: 'Read-only',
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
        value: 'Expiry summary not connected',
        severity: 'watch',
    },
    {
        key: 'funding-runway',
        label: 'Funding Runway',
        value: 'Liquidity forecast not connected',
        severity: 'warning',
    },
    {
        key: 'stuck-settlements',
        label: 'Stuck Settlements',
        value: 'Settlement summary not connected',
        severity: 'critical',
    },
];

export const cockpitRecentActivityItems: CockpitActivityItem[] = [
    {
        id: 'execution-read-model',
        label: 'Settlement activity',
        description: 'Settlement outcome summaries are not connected yet.',
        timestamp: 'Deferred',
        source: 'execution',
    },
    {
        id: 'journal-read-model',
        label: 'Journal activity',
        description: 'Audit facts require authorization and redaction before display.',
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
