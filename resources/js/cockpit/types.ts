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

