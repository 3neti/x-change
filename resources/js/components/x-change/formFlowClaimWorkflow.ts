export interface ClaimWorkflowReview {
    authorization_reference?: string | null;
    worksheet_reference?: string | null;
    beneficiary_count?: number | string | null;
    principal_minor?: number | string | null;
    currency?: string | null;
}

export interface ClaimWorkflowMetadata {
    key: string;
    title?: string | null;
    description?: string | null;
    confirmation_label?: string | null;
    requires_authenticated_officer?: boolean | null;
    requires_mobile?: boolean | null;
    requires_destination?: boolean | null;
    requires_amount?: boolean | null;
    skip_form_flow_splash?: boolean | null;
    review?: ClaimWorkflowReview | null;
}

export interface ClaimWorkflowLayoutGuidance {
    density: 'default' | 'compact' | 'immersive';
    capture_surface: 'default' | 'edge_to_edge';
    minimize_scroll: boolean;
}

export interface ClaimWorkflowReviewItem {
    label: string;
    value: string;
}

export function normalizeClaimWorkflow(input: unknown): ClaimWorkflowMetadata | null {
    if (!input || typeof input !== 'object' || Array.isArray(input)) {
        return null;
    }

    const payload = input as Record<string, unknown>;
    const key = typeof payload.key === 'string' ? payload.key.trim() : '';

    if (!key) {
        return null;
    }

    return {
        key,
        title: optionalString(payload.title),
        description: optionalString(payload.description),
        confirmation_label: optionalString(payload.confirmation_label),
        requires_authenticated_officer: optionalBoolean(payload.requires_authenticated_officer),
        requires_mobile: optionalBoolean(payload.requires_mobile),
        requires_destination: optionalBoolean(payload.requires_destination),
        requires_amount: optionalBoolean(payload.requires_amount),
        skip_form_flow_splash: optionalBoolean(payload.skip_form_flow_splash),
        review: normalizeReview(payload.review),
    };
}

export function claimWorkflowConfirmationLabel(
    workflow: ClaimWorkflowMetadata | null,
    fallback = 'Continue',
): string {
    return workflow?.confirmation_label?.trim() || fallback;
}

export function claimWorkflowSummaryText(workflow: ClaimWorkflowMetadata | null): string | null {
    if (!workflow) {
        return null;
    }

    if (workflow.requires_authenticated_officer) {
        return 'Authorization only. No beneficiary payout is sent by this form.';
    }

    if (workflow.requires_destination) {
        return 'Payout details will be collected before redemption continues.';
    }

    return null;
}

export function claimWorkflowLayoutGuidance(input: unknown): ClaimWorkflowLayoutGuidance {
    if (!input || typeof input !== 'object' || Array.isArray(input)) {
        return defaultLayoutGuidance();
    }

    const payload = input as Record<string, unknown>;
    const density = optionalString(payload.density);
    const captureSurface = optionalString(payload.capture_surface);

    return {
        density: density === 'default' || density === 'compact' || density === 'immersive'
            ? density
            : 'compact',
        capture_surface: captureSurface === 'edge_to_edge' ? 'edge_to_edge' : 'default',
        minimize_scroll: optionalBoolean(payload.minimize_scroll) ?? true,
    };
}

export function claimWorkflowLayoutClasses(layout: ClaimWorkflowLayoutGuidance): string[] {
    return [
        layout.density === 'immersive' ? 'claim-flow--immersive' : null,
        layout.density === 'compact' ? 'claim-flow--compact' : null,
        layout.capture_surface === 'edge_to_edge' ? 'claim-flow--edge-capture' : null,
        layout.minimize_scroll ? 'claim-flow--minimize-scroll' : null,
    ].filter((value): value is string => Boolean(value));
}

export function claimWorkflowReviewItems(workflow: ClaimWorkflowMetadata | null): ClaimWorkflowReviewItem[] {
    const review = workflow?.review;

    if (!review) {
        return [];
    }

    const items: ClaimWorkflowReviewItem[] = [];

    if (review.beneficiary_count !== undefined && review.beneficiary_count !== null && review.beneficiary_count !== '') {
        items.push({
            label: 'Beneficiaries',
            value: String(review.beneficiary_count),
        });
    }

    const principal = parseMinorAmount(review.principal_minor);
    const currency = review.currency || 'PHP';

    if (principal !== null) {
        items.push({
            label: 'Total',
            value: formatMinorCurrency(principal, currency),
        });
    }

    if (review.authorization_reference) {
        items.push({
            label: 'Authorization',
            value: review.authorization_reference,
        });
    }

    if (review.worksheet_reference) {
        items.push({
            label: 'Worksheet',
            value: review.worksheet_reference,
        });
    }

    return items;
}

function defaultLayoutGuidance(): ClaimWorkflowLayoutGuidance {
    return {
        density: 'compact',
        capture_surface: 'edge_to_edge',
        minimize_scroll: true,
    };
}

function optionalString(value: unknown): string | null {
    return typeof value === 'string' ? value : null;
}

function optionalBoolean(value: unknown): boolean | null {
    return typeof value === 'boolean' ? value : null;
}

function normalizeReview(input: unknown): ClaimWorkflowReview | null {
    if (!input || typeof input !== 'object' || Array.isArray(input)) {
        return null;
    }

    const review = input as Record<string, unknown>;

    return {
        authorization_reference: optionalString(review.authorization_reference),
        worksheet_reference: optionalString(review.worksheet_reference),
        beneficiary_count: optionalString(review.beneficiary_count) ?? optionalNumber(review.beneficiary_count),
        principal_minor: optionalString(review.principal_minor) ?? optionalNumber(review.principal_minor),
        currency: optionalString(review.currency),
    };
}

function optionalNumber(value: unknown): number | null {
    return typeof value === 'number' && Number.isFinite(value) ? value : null;
}

function parseMinorAmount(value: unknown): number | null {
    if (typeof value === 'number' && Number.isFinite(value)) {
        return value;
    }

    if (typeof value === 'string' && value.trim() !== '') {
        const parsed = Number(value);

        return Number.isFinite(parsed) ? parsed : null;
    }

    return null;
}

function formatMinorCurrency(minor: number, currency: string): string {
    return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency,
    }).format(minor / 100);
}
