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

export type CockpitReadModelRedactions = {
    payloads?: string;
    [key: string]: unknown;
};

export type CockpitVoucherReadModel = {
    code?: string | null;
    status: string;
    summary?: Record<string, unknown>;
    redactions?: CockpitReadModelRedactions;
    authorized?: boolean;
};

export type CockpitDependentReadModel = {
    status: string;
    authorized?: boolean;
    redactions?: CockpitReadModelRedactions;
    [key: string]: unknown;
};

export type CockpitReadModelBundle = {
    code?: string | null;
    voucher?: CockpitVoucherReadModel;
    execution?: CockpitDependentReadModel;
    journal?: CockpitDependentReadModel;
    actions?: CockpitDependentReadModel;
    feedback?: CockpitDependentReadModel;
};

export type CockpitPageAuthorization = {
    view_cockpit?: boolean;
    mutate_vouchers?: boolean;
    execute_drivers?: boolean;
    write_journal_entries?: boolean;
    send_feedback?: boolean;
    call_providers?: boolean;
    move_money?: boolean;
    [key: string]: boolean | undefined;
};

export type CockpitVoucherDetailPageProps = {
    context?: {
        code?: string | null;
    };
    can?: CockpitPageAuthorization;
    redaction?: CockpitReadModelRedactions & {
        policy?: string;
    };
    read_model?: CockpitReadModelBundle;
};

export type CockpitPayCodeExplorerReadModelRecord = {
    code?: string | null;
    template?: string | null;
    amount?: string | number | null;
    currency?: string | null;
    status?: string | null;
    display_status?: string | null;
    owner?: string | null;
    last_activity?: string | null;
    [key: string]: unknown;
};

export type CockpitPayCodeExplorerReadModel = {
    status: string;
    authorized?: boolean;
    query?: string | null;
    records?: CockpitPayCodeExplorerReadModelRecord[];
    redactions?: CockpitReadModelRedactions;
};

export type CockpitPayCodeExplorerPageProps = {
    pay_codes_read_model?: CockpitPayCodeExplorerReadModel;
};

export type CockpitDistributionChannel = {
    key: string;
    label: string;
    status: string;
    helper: string;
};

export type CockpitPrintTemplate = {
    key: string;
    label: string;
    format: string;
    helper: string;
};

export type CockpitShareAsset = {
    key: string;
    label: string;
    value: string;
    helper: string;
};

export type CockpitDistributionMetric = {
    key: string;
    label: string;
    value: string;
    helper: string;
};

export type CockpitDistributionAction = {
    key: string;
    label: string;
    disabled: boolean;
    reason: string;
};
