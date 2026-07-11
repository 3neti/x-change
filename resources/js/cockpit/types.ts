export type CockpitNavigationGroup = 'primary' | 'secondary';

export type CockpitNavigationItem = {
    key: string;
    label: string;
    href: string;
    group: CockpitNavigationGroup;
    badge?: string;
    description?: string;
    enabled?: boolean;
    disabledLabel?: string;
    disabledReason?: string;
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

export type CockpitDashboardReadModel = {
    status: string;
    authorized?: boolean;
    metrics?: CockpitDashboardMetric[];
    pipeline?: CockpitPipelineStage[];
    risk_signals?: CockpitRiskSignal[];
    activity?: CockpitActivityItem[];
    redactions?: CockpitReadModelRedactions;
    [key: string]: unknown;
};

export type CockpitOperatorIssuanceActivityPresentation = {
    id?: string | null;
    code?: string | null;
    title?: string | null;
    subtitle?: string | null;
    status?: string | null;
    detail_href?: string | null;
    correlation_id?: string | null;
    handoffs?: {
        journal?: string | null;
        action?: string | null;
        feedback?: string | null;
        [key: string]: unknown;
    };
    safety?: {
        presentation_only?: boolean | null;
        writes_journal?: boolean | null;
        executes_actions?: boolean | null;
        sends_feedback?: boolean | null;
        moves_money?: boolean | null;
        owns_lifecycle_truth?: boolean | null;
        [key: string]: unknown;
    };
    metadata?: {
        journal_handoff?: {
            status?: string | null;
            journal_entry_id?: string | null;
            writes_journal?: boolean | null;
            source?: string | null;
            reason?: string | null;
            diagnostic?: {
                classification?: string | null;
                tone?: string | null;
                label?: string | null;
                description?: string | null;
                operator_action?: string | null;
                read_only?: boolean | null;
                retry_enabled?: boolean | null;
                mutation_enabled?: boolean | null;
                raw_payloads_exposed?: boolean | null;
                [key: string]: unknown;
            };
            metadata?: {
                reference_number?: string | null;
                event_type?: string | null;
                idempotency_key?: string | null;
                exception?: string | null;
                [key: string]: unknown;
            };
            [key: string]: unknown;
        };
        action_handoff?: {
            status?: string | null;
            action_hint_id?: string | null;
            action_run_id?: string | null;
            action_required?: boolean | null;
            executes_action?: boolean | null;
            source?: string | null;
            reason?: string | null;
            metadata?: {
                event_or_state?: string | null;
                actions?: Array<{
                    key?: string | null;
                    label?: string | null;
                    run_id?: string | null;
                    [key: string]: unknown;
                }>;
                composition?: Record<string, unknown>;
                safe_diagnostics?: Array<Record<string, unknown>>;
                exception?: string | null;
                [key: string]: unknown;
            };
            [key: string]: unknown;
        };
        feedback_handoff?: {
            status?: string | null;
            feedback_intent_id?: string | null;
            delivery_plan_id?: string | null;
            delivery_receipt_id?: string | null;
            feedback_required?: boolean | null;
            sends_feedback?: boolean | null;
            source?: string | null;
            reason?: string | null;
            metadata?: {
                intent_key?: string | null;
                event_type?: string | null;
                delivery_boundary?: string | null;
                planned_deliveries?: number | string | null;
                channels?: Array<string | null>;
                plan_items?: Array<{
                    intent_key?: string | null;
                    recipient_type?: string | null;
                    recipient_id?: string | number | null;
                    channel?: string | null;
                    status?: string | null;
                    priority?: number | string | null;
                    [key: string]: unknown;
                }>;
                composition?: Record<string, unknown>;
                exception?: string | null;
                [key: string]: unknown;
            };
            [key: string]: unknown;
        };
        [key: string]: unknown;
    };
    [key: string]: unknown;
};

export type CockpitOperatorIssuanceActivityReadModel = {
    schema?: string;
    status: string;
    authorized?: boolean;
    source?: string;
    presentations?: CockpitOperatorIssuanceActivityPresentation[];
    empty_state?: {
        title?: string | null;
        description?: string | null;
        [key: string]: unknown;
    };
    search_filters?: {
        schema?: string;
        status?: string;
        read_only?: boolean;
        search?: string | null;
        statuses?: string[];
        handoff_statuses?: string[];
        available_statuses?: string[];
        available_handoff_statuses?: string[];
        safety?: Record<string, unknown>;
        [key: string]: unknown;
    };
    redactions?: CockpitReadModelRedactions;
    [key: string]: unknown;
};

export type CockpitCampaignReadModel = {
    schema?: string;
    status: string;
    authorized?: boolean;
    source?: string;
    surfaces?: Array<Record<string, unknown>>;
    facts?: Record<string, unknown>;
    mutation?: Record<string, unknown>;
    redactions?: CockpitReadModelRedactions;
    [key: string]: unknown;
};

export type CockpitCampaignSurface = {
    key: string;
    status: string;
    enabled: boolean;
    read_only: boolean;
    reason?: string;
};

export type CockpitCampaignPanelStatus = {
    key: string;
    status: string;
};

export type CockpitCampaignActionStatus = {
    key: string;
    status: 'available' | 'blocked';
};

export type CockpitCampaignNavigationContext = {
    schema?: string;
    status: string;
    authorized?: boolean;
    source?: string;
    planning_key?: string | null;
    execution_id?: string | null;
    destination?: string;
    read_only?: boolean;
    mutation?: Record<string, unknown>;
    redactions?: CockpitReadModelRedactions;
    [key: string]: unknown;
};

export type CockpitActivityNavigationContext = {
    schema?: string;
    status: string;
    authorized?: boolean;
    source?: string;
    code?: string | null;
    destination?: string;
    read_only?: boolean;
    mutation?: Record<string, unknown>;
    redactions?: CockpitReadModelRedactions;
    [key: string]: unknown;
};

export type CockpitDashboardPageProps = {
    dashboard_read_model?: CockpitDashboardReadModel;
    campaign_read_model?: CockpitCampaignReadModel;
    operator_issuance_activity_read_model?: CockpitOperatorIssuanceActivityReadModel;
    read_model?: CockpitReadModelBundle;
};

export type CockpitRuntimeProfileComponent = {
    key: string;
    configured?: string | null;
    enabled: boolean;
    resolved_class: string;
    fallback_class: string;
    uses_fallback: boolean;
    purpose: string;
};

export type CockpitRuntimeProfile = {
    schema: string;
    status: string;
    repository_enabled: boolean;
    recorder_enabled: boolean;
    journal_handoff_enabled: boolean;
    action_handoff_enabled: boolean;
    feedback_handoff_enabled: boolean;
    components: CockpitRuntimeProfileComponent[];
    safety: Record<string, unknown>;
};

export type CockpitRuntimeProfileReadModel = {
    schema: string;
    status: string;
    authorized: boolean;
    read_only: boolean;
    profile: CockpitRuntimeProfile;
    copy: {
        eyebrow: string;
        title: string;
        description: string;
    };
    safety: Record<string, unknown>;
    redactions: CockpitReadModelRedactions;
};

export type CockpitRuntimeProfilePageProps = {
    runtime_profile_read_model: CockpitRuntimeProfileReadModel;
    read_model?: CockpitReadModelBundle;
};

export type CockpitQuickGenerateTemplate = {
    key: string;
    name: string;
    description: string;
    profile: string;
    estimatedTime: string;
    disabled?: boolean;
};

export type CockpitQuickGenerateReadModelTemplate = {
    key?: string | null;
    name?: string | null;
    description?: string | null;
    profile?: string | null;
    estimated_time?: string | null;
    disabled?: boolean | null;
    [key: string]: unknown;
};

export type CockpitRuntimeInput = {
    key: string;
    label: string;
    value: string;
    helper: string;
};

export type CockpitQuickGenerateReadModelRuntimeInput = {
    key?: string | null;
    label?: string | null;
    value?: string | null;
    helper?: string | null;
    [key: string]: unknown;
};

export type CockpitPricingFundingSummary = {
    key: string;
    label: string;
    value: string;
    helper: string;
};

export type CockpitQuickGenerateReadModelPricingSummary = {
    key?: string | null;
    label?: string | null;
    value?: string | null;
    helper?: string | null;
    [key: string]: unknown;
};

export type CockpitQuickGeneratePricingGateCheck = {
    key?: string | null;
    label?: string | null;
    status?: string | null;
    reason?: string | null;
    [key: string]: unknown;
};

export type CockpitQuickGeneratePricingGate = {
    status?: string | null;
    checks?: CockpitQuickGeneratePricingGateCheck[];
    redactions?: CockpitReadModelRedactions;
    [key: string]: unknown;
};

export type CockpitQuickGenerateFundingGateCheck = {
    key?: string | null;
    label?: string | null;
    status?: string | null;
    reason?: string | null;
    [key: string]: unknown;
};

export type CockpitQuickGenerateFundingGate = {
    status?: string | null;
    checks?: CockpitQuickGenerateFundingGateCheck[];
    redactions?: CockpitReadModelRedactions;
    [key: string]: unknown;
};

export type CockpitQuickGenerateIdempotencyGateCheck = {
    key?: string | null;
    label?: string | null;
    status?: string | null;
    reason?: string | null;
    [key: string]: unknown;
};

export type CockpitQuickGenerateIdempotencyGate = {
    status?: string | null;
    checks?: CockpitQuickGenerateIdempotencyGateCheck[];
    redactions?: CockpitReadModelRedactions;
    [key: string]: unknown;
};

export type CockpitQuickGenerateValidationRedactionGateCheck = {
    key?: string | null;
    label?: string | null;
    status?: string | null;
    reason?: string | null;
    [key: string]: unknown;
};

export type CockpitQuickGenerateValidationRedactionGate = {
    status?: string | null;
    checks?: CockpitQuickGenerateValidationRedactionGateCheck[];
    redactions?: CockpitReadModelRedactions;
    [key: string]: unknown;
};

export type CockpitQuickGenerateMutationHandoffPlanStep = {
    key?: string | null;
    label?: string | null;
    status?: string | null;
    reason?: string | null;
    [key: string]: unknown;
};

export type CockpitQuickGenerateMutationHandoffPlan = {
    status?: string | null;
    steps?: CockpitQuickGenerateMutationHandoffPlanStep[];
    redactions?: CockpitReadModelRedactions;
    [key: string]: unknown;
};

export type CockpitQuickGenerateMutationPreconditionsReviewItem = {
    key?: string | null;
    label?: string | null;
    status?: string | null;
    reason?: string | null;
    [key: string]: unknown;
};

export type CockpitQuickGenerateMutationPreconditionsReview = {
    status?: string | null;
    recommendation?: string | null;
    items?: CockpitQuickGenerateMutationPreconditionsReviewItem[];
    redactions?: CockpitReadModelRedactions;
    [key: string]: unknown;
};

export type CockpitQuickGenerateMutationAuthorizationDecision = {
    status?: string | null;
    decision?: string | null;
    required_approval?: string | null;
    rationale?: string | null;
    next_step?: string | null;
    redactions?: CockpitReadModelRedactions;
    [key: string]: unknown;
};

export type CockpitQuickGenerateMutationContractGate = {
    key?: string | null;
    label?: string | null;
    status?: string | null;
    decision?: string | null;
    reason?: string | null;
    [key: string]: unknown;
};

export type CockpitQuickGenerateMutationContract = {
    schema?: string | null;
    status?: string | null;
    authorization?: string | null;
    route?: string | null;
    route_url?: string | null;
    request_adapter?: string | null;
    issuance_owner?: string | null;
    idempotency?: string | null;
    response_contract?: string | null;
    runtime_enabled?: boolean | null;
    gates?: CockpitQuickGenerateMutationContractGate[];
    allowed_methods?: string[];
    redactions?: CockpitReadModelRedactions;
    [key: string]: unknown;
};

export type CockpitQuickGenerateDraftContract = {
    schema?: string | null;
    status?: string | null;
    template_key?: string | null;
    amount?: string | number | null;
    currency?: string | null;
    recipient_reference?: string | null;
    purpose?: string | null;
    idempotency_key?: string | null;
    redactions?: CockpitReadModelRedactions;
    [key: string]: unknown;
};

export type CockpitQuickGenerateCampaignContextDraft = {
    schema?: string | null;
    status?: string | null;
    template_key?: string | null;
    amount?: string | number | null;
    currency?: string | null;
    count?: string | number | null;
    recipient_reference?: string | null;
    purpose?: string | null;
    idempotency_key?: string | null;
    correlation_id?: string | null;
    campaign?: {
        planning_key?: string | null;
        execution_id?: string | null;
        campaign_id?: string | null;
        audience_id?: string | null;
        recipient_id?: string | null;
        source?: string | null;
        [key: string]: unknown;
    } | null;
    [key: string]: unknown;
};

export type CockpitQuickGenerateCampaignContext = {
    schema?: string | null;
    status?: string | null;
    authorized?: boolean | null;
    read_only?: boolean | null;
    mutates_campaign?: boolean | null;
    planning_key?: string | null;
    execution_id?: string | null;
    campaign_id?: string | null;
    audience_id?: string | null;
    recipient_id?: string | null;
    source?: string | null;
    draft?: CockpitQuickGenerateCampaignContextDraft | null;
    redactions?: CockpitReadModelRedactions;
    [key: string]: unknown;
};

export type CockpitQuickGenerateRuntimePricingPreflight = {
    status?: string | null;
    currency?: string | null;
    base_fee?: string | number | null;
    total?: string | number | null;
    blocking?: boolean | null;
    source?: string | null;
    reason?: string | null;
    components?: Record<string, string | number | null>;
    [key: string]: unknown;
};

export type CockpitQuickGenerateRuntimeFundingPreflight = {
    status?: string | null;
    provider?: string | null;
    topology?: string | null;
    authority?: string | null;
    sync_status?: string | null;
    blocking?: boolean | null;
    source?: string | null;
    reason?: string | null;
    authoritative?: {
        key?: string | null;
        authority?: string | null;
        source?: string | null;
        balance?: string | number | null;
        currency?: string | null;
        is_stale?: boolean | null;
        [key: string]: unknown;
    };
    [key: string]: unknown;
};

export type CockpitQuickGenerateRuntimePreflight = {
    pricing?: CockpitQuickGenerateRuntimePricingPreflight;
    funding?: CockpitQuickGenerateRuntimeFundingPreflight;
    [key: string]: unknown;
};

export type CockpitQuickGenerateRuntimeDraft = {
    status?: string | null;
    factory?: string | null;
    compiler?: string | null;
    source?: string | null;
    [key: string]: unknown;
};

export type CockpitQuickGenerateRuntimeActivity = {
    schema?: string | null;
    status?: string | null;
    source?: string | null;
    presentation_only?: boolean | null;
    metadata_alignment?: string | null;
    [key: string]: unknown;
};

export type CockpitQuickGeneratePostIssuanceNavigationItem = {
    key?: string | null;
    label?: string | null;
    href?: string | null;
    status?: string | null;
    enabled?: boolean | null;
    read_only?: boolean | null;
    reason?: string | null;
    [key: string]: unknown;
};

export type CockpitQuickGeneratePostIssuanceNavigation = {
    schema?: string | null;
    status?: string | null;
    auto_redirect?: boolean | null;
    items?: CockpitQuickGeneratePostIssuanceNavigationItem[];
    redactions?: CockpitReadModelRedactions;
    [key: string]: unknown;
};

export type CockpitQuickGenerateAuthorizationGate = {
    key?: string | null;
    label?: string | null;
    status?: string | null;
    reason?: string | null;
    [key: string]: unknown;
};

export type CockpitQuickGenerateAuthorization = {
    status?: string | null;
    gates?: CockpitQuickGenerateAuthorizationGate[];
    redactions?: CockpitReadModelRedactions;
    [key: string]: unknown;
};

export type CockpitQuickGenerateReadModel = {
    status: string;
    authorized?: boolean;
    templates?: CockpitQuickGenerateReadModelTemplate[];
    runtime_inputs?: CockpitQuickGenerateReadModelRuntimeInput[];
    pricing_summaries?: CockpitQuickGenerateReadModelPricingSummary[];
    pricing_gate?: CockpitQuickGeneratePricingGate;
    funding_gate?: CockpitQuickGenerateFundingGate;
    idempotency_gate?: CockpitQuickGenerateIdempotencyGate;
    validation_redaction_gate?: CockpitQuickGenerateValidationRedactionGate;
    mutation_handoff_plan?: CockpitQuickGenerateMutationHandoffPlan;
    mutation_preconditions_review?: CockpitQuickGenerateMutationPreconditionsReview;
    mutation_authorization_decision?: CockpitQuickGenerateMutationAuthorizationDecision;
    mutation_contract?: CockpitQuickGenerateMutationContract;
    draft_contract?: CockpitQuickGenerateDraftContract;
    campaign_context?: CockpitQuickGenerateCampaignContext;
    authorization?: CockpitQuickGenerateAuthorization;
    post_issuance_navigation?: CockpitQuickGeneratePostIssuanceNavigation;
    action?: {
        enabled?: boolean;
        reason?: string;
        [key: string]: unknown;
    };
    redactions?: CockpitReadModelRedactions;
    [key: string]: unknown;
};

export type CockpitQuickGeneratePageProps = {
    quick_generate_read_model?: CockpitQuickGenerateReadModel;
};

export type CockpitPayCodeExplorerFilter = {
    key: string;
    label: string;
    value: string;
    helper?: string;
    active?: boolean;
    read_only?: boolean;
};

export type CockpitPayCodeExplorerStats = {
    total?: number;
    active?: number;
    awaiting_approval?: number;
    redeemed?: number;
    expired?: number;
    pending?: number;
    failed?: number;
    filtered?: number;
};

export type CockpitPayCodeExplorerRecord = {
    code: string;
    template: string;
    amount: string;
    status: string;
    owner: string;
    lastActivity: string;
    actions?: CockpitPayCodeRowAction[];
};

export type CockpitPayCodeRowAction = {
    key: string;
    label: string;
    enabled?: boolean;
    disabled?: boolean;
    read_only?: boolean;
    href?: string | null;
    reason?: string | null;
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
    source?: string | null;
    read_only?: boolean;
    available?: boolean;
};

export type CockpitVoucherEvidenceSummary = {
    key: string;
    label: string;
    status: string;
    description: string;
    read_only?: boolean;
    available?: boolean;
    source?: string | null;
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
    evidence_summary?: CockpitVoucherEvidenceSummary[];
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
    actions?: CockpitPayCodeRowAction[];
    [key: string]: unknown;
};

export type CockpitPayCodeExplorerReadModel = {
    status: string;
    authorized?: boolean;
    query?: string | null;
    status_filter?: string | null;
    stats?: CockpitPayCodeExplorerStats;
    filters?: CockpitPayCodeExplorerFilter[];
    records?: CockpitPayCodeExplorerReadModelRecord[];
    redactions?: CockpitReadModelRedactions;
};

export type CockpitPayCodeExplorerPageProps = {
    pay_codes_read_model?: CockpitPayCodeExplorerReadModel;
    campaign_navigation_context?: CockpitCampaignNavigationContext;
    activity_navigation_context?: CockpitActivityNavigationContext;
    read_model?: CockpitReadModelBundle;
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

export type CockpitDistributionWorkspaceItem = {
    key: string;
    label: string;
    status: string;
    description: string;
    read_only?: boolean;
    available?: boolean;
    source?: string | null;
    href?: string | null;
    metadata?: Record<string, unknown>;
};

export type CockpitDistributionWorkspaceReadModel = {
    schema?: string;
    status: string;
    authorized?: boolean;
    code?: string | null;
    summary?: Record<string, unknown>;
    share_assets?: CockpitDistributionWorkspaceItem[];
    channels?: CockpitDistributionWorkspaceItem[];
    print_templates?: CockpitDistributionWorkspaceItem[];
    analytics?: CockpitDistributionWorkspaceItem[];
    actions?: CockpitDistributionWorkspaceItem[];
    redactions?: CockpitReadModelRedactions;
    [key: string]: unknown;
};

export type CockpitDistributionWorkspacePageProps = {
    context?: {
        code?: string | null;
    };
    can?: CockpitPageAuthorization;
    redaction?: CockpitReadModelRedactions & {
        policy?: string;
    };
    read_model?: CockpitReadModelBundle;
    distribution_workspace_read_model?: CockpitDistributionWorkspaceReadModel;
};
