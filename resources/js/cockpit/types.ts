export type CockpitNavigationGroup = 'primary' | 'secondary';

export type CockpitNavigationItem = {
    key: string;
    label: string;
    href: string;
    group: CockpitNavigationGroup;
    badge?: string;
    description?: string;
};

export type CockpitBalanceMetric = {
    key: string;
    label: string;
    value: string;
    tone?: 'neutral' | 'healthy' | 'warning' | 'critical';
};

export type CockpitShellContext = {
    institution: string;
    operatingIdentity: string;
    connectivity: string;
    balances: CockpitBalanceMetric[];
};

export type CockpitDashboardMetric = {
    key: string;
    label: string;
    value: string;
    helper?: string;
    tone?: 'neutral' | 'healthy' | 'warning' | 'critical';
};

export type CockpitPipelineStage = {
    key: string;
    label: string;
    value: string;
    tone?: 'neutral' | 'healthy' | 'warning' | 'critical';
};

export type CockpitRiskSignal = {
    key: string;
    label: string;
    value: string;
    severity: 'watch' | 'warning' | 'critical';
};

export type CockpitActivityItem = {
    id: string;
    label: string;
    description: string;
    timestamp: string;
    source: 'execution' | 'journal' | 'action' | 'feedback' | 'system';
};

export type CockpitQuickGenerateTemplate = {
    key: string;
    name: string;
    description: string;
    profile: string;
    estimatedTime: string;
    disabled?: boolean;
};

export type CockpitRuntimeInput = {
    key: string;
    label: string;
    value: string;
    helper: string;
};

export type CockpitPricingFundingSummary = {
    key: string;
    label: string;
    value: string;
    helper: string;
};

export type CockpitPayCodeExplorerFilter = {
    key: string;
    label: string;
    value: string;
    helper: string;
};

export type CockpitPayCodeExplorerRecord = {
    code: string;
    template: string;
    amount: string;
    status: string;
    owner: string;
    lastActivity: string;
};

export type CockpitPayCodeRowAction = {
    key: string;
    label: string;
    disabled: boolean;
    reason: string;
};

export type CockpitVoucherOverviewItem = {
    key: string;
    label: string;
    value: string;
    helper?: string;
};

export type CockpitVoucherTimelineItem = {
    id: string;
    label: string;
    description: string;
    timestamp: string;
    source: 'execution' | 'journal' | 'feedback' | 'system';
};

export type CockpitVoucherEvidenceItem = {
    id: string;
    label: string;
    status: string;
    helper: string;
};

export type CockpitVoucherDistributionItem = {
    id: string;
    channel: string;
    status: string;
    helper: string;
};

export type CockpitVoucherAuditItem = {
    id: string;
    label: string;
    status: string;
    helper: string;
};

export type CockpitVoucherDetailAction = {
    key: string;
    label: string;
    disabled: boolean;
    reason: string;
};
