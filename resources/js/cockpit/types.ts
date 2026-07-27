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
    helper?: string;
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
    projection_badge?: string;
    projection_status?: string;
    projection_detail?: string;
    projection_targets?: string[];
    metadata?: Record<string, unknown>;
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
        campaign_attribution?: {
            schema?: string | null;
            status?: string | null;
            read_only?: boolean | null;
            mutates_campaign?: boolean | null;
            planning_key?: string | null;
            execution_id?: string | null;
            campaign_id?: string | null;
            audience_id?: string | null;
            recipient_id?: string | null;
            source?: string | null;
            generated_code?: string | null;
            template_key?: string | null;
            amount?: string | number | null;
            currency?: string | null;
            recipient_reference?: string | number | null;
            purpose?: string | null;
            redactions?: Record<string, unknown>;
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
    quick_generate_link?: CockpitCampaignQuickGenerateLink;
    recipient_quick_generate_links?: CockpitCampaignQuickGenerateLink[];
    redactions?: CockpitReadModelRedactions;
    [key: string]: unknown;
};

export type CockpitCampaignQuickGenerateLink = {
    schema?: string;
    status?: string;
    enabled?: boolean;
    label?: string;
    href?: string | null;
    route?: string;
    read_only?: boolean;
    mutates_campaign?: boolean;
    planning_key?: string | null;
    execution_id?: string | null;
    campaign_id?: string | null;
    audience_id?: string | null;
    recipient_id?: string | null;
    source?: string | null;
    draft?: Record<string, unknown> | null;
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
    campaign_id?: string | null;
    audience_id?: string | null;
    recipient_id?: string | null;
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

export type CockpitHeaderReadModel = {
    schema?: string;
    status?: string;
    authorized?: boolean;
    read_only?: boolean;
    operating_identity?: 'Account holder' | 'System Treasury';
    balances?: CockpitBalanceMetric[];
    vocabulary?: Record<string, CockpitVocabularyTerm>;
    redactions?: CockpitReadModelRedactions;
};

export type CockpitVocabularyTerm = {
    key: string;
    label: string;
    description: string;
    source: string;
    profile: string;
    profile_version: string;
    approved_for_public_display: boolean;
};

export type CockpitHeaderPageProps = {
    cockpit_header_read_model?: CockpitHeaderReadModel;
};

export type CockpitFundingDestinationSummary = {
    status: string;
    display_reference?: string | null;
    managed_by?: string;
};

export type CockpitDedicatedFundingDestinationSummary = {
    configured: boolean;
    display_reference?: string | null;
    status: string;
    verification_status: string;
    verified_at?: string | null;
    last_synced_at?: string | null;
    can_activate: boolean;
    can_rotate_token: boolean;
    ownership_verification_required: boolean;
};

export type CockpitAccountProvider = {
    code: 'netbank' | 'paynamics_constellation';
    label: string;
    mode: 'shared' | 'dedicated';
    shared: CockpitFundingDestinationSummary;
    dedicated: CockpitDedicatedFundingDestinationSummary;
};

export type CockpitAccountConnectionHistory = {
    id: string;
    provider: string;
    display_reference?: string | null;
    status: string;
    verification_status: string;
    created_at?: string | null;
    disabled_at?: string | null;
};

export type CockpitAccountReadModel = {
    schema: string;
    status: string;
    account: {
        reference: string;
        currency: string;
        ledger_authority: string;
        funding_credit_policy: string;
    };
    providers: CockpitAccountProvider[];
    connection_history: CockpitAccountConnectionHistory[];
    controls: {
        shared_is_default: boolean;
        dedicated_fallback_enabled: boolean;
        pin_confirmation_required: boolean;
        manual_balance_adjustment_enabled: boolean;
        provider_webhook_settlement_required: boolean;
    };
    redactions: {
        account_numbers: string;
        wallet_ids: string;
        routing_tokens: string;
        credentials_exposed: boolean;
    };
};

export type CockpitFundingQrMerchantProfile = {
    name: string;
    city: string;
    merchant_category_code: string;
    merchant_name_template: string;
    category_options: Array<{
        code: string;
        label: string;
    }>;
    presentation_only: true;
    controls_routing: false;
    controls_settlement: false;
};

export type CockpitAccountsPageProps = CockpitHeaderPageProps & {
    account_read_model: CockpitAccountReadModel;
    funding_account_notice?: string | null;
    funding_qr_merchant_profile: CockpitFundingQrMerchantProfile;
    account_scenario?: {
        enabled: boolean;
        mode: 'rollback-only';
        provider_calls: boolean;
        balance_changes: boolean;
    };
};

export type CockpitAccountScenarioFact = {
    label: string;
    value: string;
};

export type CockpitAccountScenarioProvider = {
    code: 'netbank' | 'paynamics_constellation';
    label: string;
    mode: string;
    shared: {
        status: string;
        display_reference?: string | null;
    };
    dedicated: {
        configured: boolean;
        display_reference?: string | null;
        status: string;
        verification_status: string;
        can_activate: boolean;
        can_rotate_token: boolean;
        ownership_verification_required: boolean;
    };
};

export type CockpitAccountScenarioStep = {
    key: string;
    label: string;
    outcome: 'ready' | 'blocked' | 'protected' | 'complete' | 'failed';
    summary: string;
    providers: CockpitAccountScenarioProvider[];
    facts: CockpitAccountScenarioFact[];
};

export type CockpitAccountScenarioResult = {
    schema: 'x-change.lifecycle.account-management-scenario.v1';
    scenario: string;
    label: string;
    mode: 'account_management';
    success: boolean;
    message: string;
    rollback_completed: boolean;
    simulation: {
        rollback_only: boolean;
        provider_calls: number;
        balance_changed: boolean;
        persisted: boolean;
        funding_instructions_issued: boolean;
        webhooks_received: boolean;
    };
    steps: CockpitAccountScenarioStep[];
};

export type CockpitDashboardPageProps = CockpitHeaderPageProps & {
    dashboard_read_model?: CockpitDashboardReadModel;
    campaign_read_model?: CockpitCampaignReadModel;
    operator_issuance_activity_read_model?: CockpitOperatorIssuanceActivityReadModel;
    read_model?: CockpitReadModelBundle;
};

export type CockpitFundingSummary = {
    awaiting_funds: number;
    settled_funding: string;
    open_suspense: number;
    recovery_outstanding: string;
};

export type CockpitFundingProvider = {
    code: string;
    label: string;
    status: string;
    authoritative_verification: boolean;
    destination_mode?: 'shared' | 'dedicated';
    destination_status?: string;
    destination_reference?: string | null;
    simulation_only?: boolean;
};

export type CockpitFundingIntent = {
    reference: string;
    provider: string;
    amount: string;
    currency: string;
    status: string;
    can_check_provider: boolean;
    can_reopen_instructions: boolean;
    verification_status: string;
    last_checked_at?: string | null;
    created_at?: string | null;
    expires_at?: string | null;
    settled_at?: string | null;
};

export type CockpitFundingSuspenseCase = {
    reference: string;
    provider: string;
    reason: string;
    status: string;
    opened_at?: string | null;
    pending_approval: boolean;
    pending_action?: string | null;
    allowed_actions: string[];
};

export type CockpitFundingApproval = {
    reference: string;
    case_reference: string;
    provider: string;
    reason: string;
    action: string;
    status: string;
    requested_at?: string | null;
    requested_by_self: boolean;
    can_approve: boolean;
    amount_input_allowed: false;
    evidence_input_allowed: false;
};

export type CockpitFundingRecoveryHold = {
    reference: string;
    status: string;
    hold_status: string;
    outstanding: string;
    currency: string;
    opened_at?: string | null;
};

export type CockpitTreasuryPosition = {
    provider: string;
    currency: string;
    status: string;
    recognized: string;
    has_treasury_facts: boolean;
};

export type CockpitFundingTreasuryConnection = {
    provider: string;
    provider_label: string;
    mode: string;
    currency: string;
    status: string;
    client_funds_minor: number;
    client_funds: string;
    pay_code_reserve_minor: number;
    pay_code_reserve: string;
    account_position_minor: number;
    account_position: string;
    provider_inventory_minor: number | null;
    provider_inventory: string | null;
    provider_liquidity_minor: number | null;
    provider_liquidity: string | null;
    provider_liquidity_status: string;
    provider_liquidity_is_stale: boolean;
    provider_liquidity_checked_at?: string | null;
    issuance_capacity_minor: number | null;
    issuance_capacity: string | null;
    inventory_matches_positions: boolean | null;
    control_status: string;
    provider_calls: false;
};

export type CockpitFundingTreasuryPortfolio = {
    schema: string;
    status: string;
    read_only: true;
    provider_calls: false;
    currency: string;
    vocabulary: Record<
        string,
        {
            label: string;
            description: string;
        }
    >;
    totals: {
        client_funds_minor: number;
        client_funds: string;
        pay_code_reserve_minor: number;
        pay_code_reserve: string;
        account_position_minor: number;
        account_position: string;
        provider_inventory_minor: number | null;
        provider_inventory: string | null;
        issuance_capacity_minor: number | null;
        issuance_capacity: string | null;
    };
    connections: CockpitFundingTreasuryConnection[];
    accounting_boundary: {
        provider_outflow: 'provider_principal_only';
        sender_system_charge: 'deferred_accounting_wave';
        provider_liquidity_source: 'cached_projection_only';
    };
    redactions: Record<string, boolean>;
};

export type CockpitFundingReadModel = {
    schema: string;
    status: string;
    authorized: boolean;
    read_only: boolean;
    summary: CockpitFundingSummary;
    providers: CockpitFundingProvider[];
    intents: CockpitFundingIntent[];
    suspense_cases: CockpitFundingSuspenseCase[];
    approval_queue: CockpitFundingApproval[];
    recovery_holds: CockpitFundingRecoveryHold[];
    treasury_positions: CockpitTreasuryPosition[];
    treasury_portfolio?: CockpitFundingTreasuryPortfolio;
    controls: Record<string, boolean | string>;
    redactions: CockpitReadModelRedactions;
};

export type CockpitReviewedFundingPayCode = {
    request_reference: string;
    code: string;
    display_code: string;
    last_four: string;
    status: string;
    amount: string;
    voucher_type: string;
    collection_mode: 'system_treasury' | 'recipient_claim';
    can_claim: boolean;
    can_copy: boolean;
    expires_at?: string | null;
};

export type CockpitFundingEvidenceDocument = {
    id: number;
    type: string;
    filename: string;
    mime_type: string;
    size: number;
    review_status: string;
    url?: string;
};

export type CockpitFundingEvidenceSummary = {
    attachment_count: number;
    pending_count?: number;
    accepted_count?: number;
    envelope_status?: string | null;
    documents: CockpitFundingEvidenceDocument[];
};

export type CockpitFundingRequest = {
    reference: string;
    type: string;
    type_label: string;
    requested_value: string;
    recognized_value?: string | null;
    currency: string;
    status: string;
    receipt_status: string;
    receipt_status_label: string;
    description: string;
    transfer?: {
        provider: string;
        target_label: string;
        reference_hint?: string | null;
        verification_status:
            | 'ready_to_check'
            | 'awaiting_provider_evidence'
            | 'approval_required'
            | 'review_required'
            | 'credited';
        window: 'recent' | 'last_hour' | 'today';
        window_label: string;
        requested_amount: string;
        matching_adjustment?: string | null;
        expected_amount: string;
        instruction_status?: string | null;
        instruction_expires_at?: string | null;
        full_expected_amount_is_credited: boolean;
        last_checked_at?: string | null;
        can_check: boolean;
        provider_authority_required: true;
    } | null;
    submitted_at?: string | null;
    completed_at?: string | null;
    evidence?: CockpitFundingEvidenceSummary;
    pay_code?: CockpitReviewedFundingPayCode | null;
};

export type CockpitFundingRequestReviewItem = {
    reference: string;
    type: string;
    type_label: string;
    requested_value: string;
    recognized_value?: string | null;
    requested_value_minor: number;
    currency: string;
    status: string;
    description: string;
    evidence_reference?: string | null;
    connection_reference?: string | null;
    maker_id?: string | null;
    evidence?: CockpitFundingEvidenceSummary;
    can_prepare: boolean;
    can_approve: boolean;
};

export type CockpitFundingRequestReadModel = {
    schema: string;
    requests: CockpitFundingRequest[];
    notices: Array<{
        reference: string;
        type: string;
        title: string;
        message: string;
        action?: Record<string, string> | null;
        read: boolean;
        created_at?: string | null;
    }>;
    review_queue: CockpitFundingRequestReviewItem[];
    bank_transfer: {
        enabled: boolean;
        provider: string;
        institution: string;
        account_name: string;
        account_number: string;
        currency: string;
        minimum_requested_amount_minor: number;
        minimum_requested_amount: string;
        reserved_exact_amounts_enabled: boolean;
        minimum_adjustment: string;
        maximum_adjustment: string;
        instruction_valid_for_minutes: number;
        full_expected_amount_is_credited: true;
        automatic_credit_window_minutes: number;
        windows: Array<{
            value: 'recent' | 'last_hour' | 'today';
            label: string;
            automatic: boolean;
        }>;
        sender_reference_authority: false;
    };
    controls: {
        attachments_enabled: boolean;
        evidence_authorizes_credit: boolean;
        maker_checker_required: boolean;
        reviewer: boolean;
        provider_payout_enabled: boolean;
    };
    redactions: Record<string, boolean>;
};

export type CockpitFundingActivityItem = {
    key: string;
    source: 'funding_request' | 'standing_funding_receipt';
    reference: string;
    display_reference: string;
    method: 'qr_ph' | 'bank_transfer' | 'pay_code' | 'reviewed_value';
    method_label: string;
    amount: string;
    status:
        | 'awaiting_payment'
        | 'checking_provider'
        | 'under_review'
        | 'pay_code_ready'
        | 'processing'
        | 'recognized'
        | 'needs_attention'
        | 'declined'
        | 'expired'
        | 'cancelled'
        | 'reversed';
    status_label: string;
    updated_at?: string | null;
    timestamps: {
        requested_at?: string | null;
        observed_at?: string | null;
        recognized_at?: string | null;
    };
    summary: string;
    action_keys: Array<
        | 'view_instructions'
        | 'check_provider'
        | 'copy_pay_code'
        | 'approve_receipt'
    >;
    request_reference?: string;
    approval_reference?: string | null;
    provisional?: boolean;
    pay_code?: CockpitReviewedFundingPayCode | null;
    transfer?: CockpitFundingRequest['transfer'];
};

export type CockpitFundingActivityReadModel = {
    schema: 'x-change.cockpit.funding-activity.v1';
    items: CockpitFundingActivityItem[];
    filters: Array<{
        key: 'all' | 'qr_ph' | 'bank_transfer' | 'pay_code' | 'reviewed_value';
        label: string;
    }>;
    redactions: {
        payer_identity_exposed: false;
        provider_transaction_id_exposed: false;
        raw_evidence_exposed: false;
    };
};

export type CockpitPayCodeFundingPreview = {
    eligible: boolean;
    status: string;
    message: string;
    code_hint?: string | null;
    amount?: string | null;
    currency?: string | null;
    expires_at?: string | null;
    provider_calls: false;
    inspection_token?: string | null;
};

export type CockpitFundingPageProps = CockpitHeaderPageProps & {
    funding_read_model: CockpitFundingReadModel;
    funding_requests?: CockpitFundingRequestReadModel;
    funding_activity?: CockpitFundingActivityReadModel;
    funding_instruction?: CockpitFundingInstruction | null;
    funding_notice?: string | null;
    funding_request_submitted_reference?: string | null;
    funding_workspace_mode?:
        | 'self_top_up'
        | 'bank_transfer'
        | 'pay_code'
        | 'reviewed_value';
    funding_poll_interval?: number;
    funding_realtime?: {
        enabled: boolean;
        channel: string;
        event: '.FundingProjectionChanged';
        workflow_event: '.FundingRequestChanged';
    };
    funding_simulation?: CockpitQrPhFundingSimulation;
    standing_funding_address?: CockpitStandingFundingAddressAvailability;
    funding_qr_merchant_profile?: CockpitFundingQrMerchantProfile;
    pay_code_funding_preview?: CockpitPayCodeFundingPreview | null;
};

export type CockpitStandingFundingAddressAvailability = {
    enabled: boolean;
    available: boolean;
    status: string;
    provider: 'netbank';
    exists: boolean;
    address_scheme?: string | null;
    scheme_label: string;
    scheme_warning?: string | null;
    production_safe: boolean;
    purpose: 'account_funding';
    recognition_mode: 'observe_only' | 'supervised' | 'automatic';
    address_status?: 'active' | 'suspended' | 'retired' | null;
    temporary: false;
    provider_calls: true;
    funding_intent_created: false;
    automatic_credit_enabled: boolean;
    minimum_amount_minor: number;
    maximum_amount_minor: number;
    daily_limit_minor: number;
};

export type CockpitStandingFundingAddress = {
    reference: string;
    provider: 'netbank';
    funding_address: string;
    masked_funding_address: string;
    purpose: 'account_funding';
    recognition_mode: 'observe_only' | 'supervised' | 'automatic';
    status: 'active' | 'suspended' | 'retired';
    currency: string;
    institution: string;
    merchant_name: string;
    qr_code: string;
    qr_mode: 'static';
    transaction_type: 'p2m';
    embedded_amount: false;
    provider_generated: true;
    temporary: false;
    funding_intent_created: false;
    automatic_credit_enabled: boolean;
    minimum_amount_minor: number | null;
    maximum_amount_minor: number | null;
    daily_limit_minor: number | null;
};

export type CockpitStandingFundingReceipt = {
    reference: string;
    gross_amount_minor: number;
    fee_amount_minor: number;
    net_amount_minor: number;
    gross_amount: string;
    net_amount: string;
    currency: string;
    status: string;
    provider_status: string;
    applied: boolean;
    applied_amount_minor: number;
    applied_amount: string;
    applied_at?: string | null;
    provisional: boolean;
    can_approve: boolean;
    approval_reference?: string | null;
    occurred_at?: string | null;
    provider_settled_at?: string | null;
};

export type CockpitQrPhFundingSimulation = {
    enabled: boolean;
    mode: 'rollback-only';
    provider_calls: false;
    balance_changes: false;
    amount: string;
    mobile_ready: boolean;
    qr_code: string;
};

export type CockpitQrPhFundingSimulationStep = {
    key: string;
    label: string;
    outcome: string;
    facts: Array<{
        label: string;
        value: string;
    }>;
};

export type CockpitQrPhFundingSimulationResult = {
    schema: 'x-change.lifecycle.qrph-funding-simulation.v1';
    scenario: string;
    label: string;
    mode: 'qrph_funding_simulation';
    success: boolean;
    message: string;
    rollback_completed: boolean;
    simulation: {
        rollback_only: true;
        provider_calls: 0;
        simulated_provider_ledger: true;
        signed_webhook: true;
        authoritative_verification: true;
        persisted: false;
    };
    balance: {
        before_minor: number;
        after_minor: number;
        credited_minor: number;
        after_replay_minor: number;
    };
    steps: CockpitQrPhFundingSimulationStep[];
};

export type CockpitFundingInstruction = {
    reference: string;
    provider: string;
    amount: string;
    currency: string;
    status: string;
    expires_at?: string | null;
    funding_address?: string | null;
    action_url?: string | null;
    institution?: string | null;
    account_name?: string | null;
    delivery?: string | null;
    qr_code?: string | null;
    qr_mode?: string | null;
    transaction_type?: string | null;
    embedded_amount?: boolean;
    provider_generated?: boolean;
    balance_changed: false;
    simulation_only?: boolean;
    sensitive: boolean;
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

export type CockpitRuntimeProfilePageProps = CockpitHeaderPageProps & {
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

export type CockpitQuickGenerateCampaignAttribution = {
    schema?: string | null;
    status?: string | null;
    available?: boolean | null;
    read_only?: boolean | null;
    mutates_campaign?: boolean | null;
    planning_key?: string | null;
    execution_id?: string | null;
    campaign_id?: string | null;
    audience_id?: string | null;
    recipient_id?: string | null;
    source?: string | null;
    generated_code?: string | null;
    template_key?: string | null;
    amount?: string | number | null;
    currency?: string | null;
    recipient_reference?: string | null;
    purpose?: string | null;
    redactions?: CockpitReadModelRedactions;
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

export type CockpitQuickGeneratePageProps = CockpitHeaderPageProps & {
    quick_generate_read_model?: CockpitQuickGenerateReadModel;
    feedback_defaults?: CockpitQuickGenerateFeedbackDefaults;
    last_instructions?: CockpitQuickGenerateLastInstructions | null;
    saved_templates?: CockpitSavedPayCodeTemplate[];
};

export type CockpitSavedPayCodeTemplate = {
    reference: string;
    name: string;
    description?: string | null;
    base_template_key: string;
    instructions: Record<string, unknown>;
    include_amount: boolean;
    include_purpose: boolean;
    updated_at?: string | null;
};

export type CockpitQuickGenerateLastInstructions = {
    schema: string;
    saved_at: string;
    instructions: Record<string, unknown>;
};

export type CockpitQuickGenerateFeedbackDefaults = {
    schema?: string;
    email?: string | null;
    mobile?: string | null;
    webhook?: string | null;
    source?: string;
    read_only?: boolean;
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
    capability: {
        key: string;
        label: string;
        voucherTypeLabel: string;
    };
    instructionBadges: Array<{
        key: string;
        label: string;
    }>;
    amount: string;
    status: string;
    party: {
        state: string;
        label: string;
        primary: string;
        secondary: string | null;
        masked: boolean;
    };
    timing: {
        createdAt: string | null;
        startsAt: string | null;
        expiresAt: string | null;
        redeemedAt: string | null;
    };
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
    distribution_links?: Record<string, unknown>;
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

export type CockpitVoucherDetailPageProps = CockpitHeaderPageProps & {
    context?: {
        code?: string | null;
    };
    can?: CockpitPageAuthorization;
    redaction?: CockpitReadModelRedactions & {
        policy?: string;
    };
    campaign_navigation_context?: CockpitCampaignNavigationContext;
    read_model?: CockpitReadModelBundle;
};

export type CockpitPayCodeExplorerReadModelRecord = {
    code?: string | null;
    template?: string | null;
    amount?: string | number | null;
    currency?: string | null;
    status?: string | null;
    display_status?: string | null;
    capability?: {
        key?: string | null;
        label?: string | null;
        voucher_type_label?: string | null;
        [key: string]: unknown;
    };
    instruction_badges?: Array<{
        key?: string | null;
        label?: string | null;
        [key: string]: unknown;
    }>;
    party?: {
        state?: string | null;
        label?: string | null;
        primary?: string | null;
        secondary?: string | null;
        masked?: boolean;
        [key: string]: unknown;
    };
    timing?: {
        created_at?: string | null;
        starts_at?: string | null;
        expires_at?: string | null;
        redeemed_at?: string | null;
        [key: string]: unknown;
    };
    owner?: string | null;
    last_activity?: string | null;
    created_at?: string | null;
    expires_at?: string | null;
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

export type CockpitPayCodeExplorerPageProps = CockpitHeaderPageProps & {
    can?: CockpitPageAuthorization;
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
    metadata?: Record<string, unknown>;
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
    metadata?: Record<string, unknown>;
};

export type CockpitDistributionAction = {
    key: string;
    label: string;
    disabled: boolean;
    reason: string;
    metadata?: Record<string, unknown>;
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
    distribution_links?: Record<string, unknown>;
    share_assets?: CockpitDistributionWorkspaceItem[];
    channels?: CockpitDistributionWorkspaceItem[];
    print_templates?: CockpitDistributionWorkspaceItem[];
    analytics?: CockpitDistributionWorkspaceItem[];
    actions?: CockpitDistributionWorkspaceItem[];
    redactions?: CockpitReadModelRedactions;
    [key: string]: unknown;
};

export type CockpitDistributionWorkspacePageProps = CockpitHeaderPageProps & {
    context?: {
        code?: string | null;
    };
    can?: CockpitPageAuthorization;
    redaction?: CockpitReadModelRedactions & {
        policy?: string;
    };
    read_model?: CockpitReadModelBundle;
    campaign_navigation_context?: CockpitCampaignNavigationContext;
    distribution_workspace_read_model?: CockpitDistributionWorkspaceReadModel;
};
