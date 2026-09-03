import { describe, expect, it } from 'vitest';
import {
    claimWorkflowConfirmationLabel,
    claimWorkflowConfirmationTitle,
    claimWorkflowLayoutClasses,
    claimWorkflowLayoutGuidance,
    claimWorkflowReviewItems,
    claimWorkflowSummaryText,
    normalizeClaimWorkflow,
} from '../../resources/js/components/x-change/formFlowClaimWorkflow';

describe('formFlowClaimWorkflow', () => {
    it('ignores missing or unkeyed workflow metadata', () => {
        expect(normalizeClaimWorkflow(null)).toBeNull();
        expect(normalizeClaimWorkflow({ title: 'Untethered payload' })).toBeNull();
        expect(claimWorkflowConfirmationLabel(null)).toBe('Continue');
        expect(claimWorkflowSummaryText(null)).toBeNull();
    });

    it('normalizes campaign officer authorization metadata', () => {
        const workflow = normalizeClaimWorkflow({
            key: 'campaign.officer-authorization.v1',
            title: 'Campaign Officer Authorization',
            description: 'Review and authorize this campaign worksheet.',
            confirmation_label: 'Authorize Campaign',
            confirmation_title: 'Review campaign',
            requires_authenticated_officer: true,
            review: {
                authorization_reference: 'authorization-01',
                worksheet_reference: 'worksheet-01',
                beneficiary_count: 2,
                principal_minor: 12500,
                currency: 'PHP',
            },
        });

        expect(workflow?.key).toBe('campaign.officer-authorization.v1');
        expect(claimWorkflowConfirmationLabel(workflow)).toBe('Authorize Campaign');
        expect(claimWorkflowConfirmationTitle(workflow)).toBe('Review campaign');
        expect(claimWorkflowSummaryText(workflow)).toBe(
            'Authorization only. No beneficiary payout is sent by this form.',
        );
        expect(claimWorkflowReviewItems(workflow)).toEqual([
            { label: 'Beneficiaries', value: '2' },
            { label: 'Total', value: '₱125.00' },
            { label: 'Authorization', value: 'authorization-01' },
            { label: 'Worksheet', value: 'worksheet-01' },
        ]);
    });

    it('summarizes payout collection workflows without changing the default submit label', () => {
        const workflow = normalizeClaimWorkflow({
            key: 'disbursement.v1',
            requires_destination: true,
            review: null,
        });

        expect(claimWorkflowConfirmationLabel(workflow)).toBe('Continue');
        expect(claimWorkflowSummaryText(workflow)).toBe(
            'Payout details will be collected before redemption continues.',
        );
        expect(claimWorkflowReviewItems(workflow)).toEqual([]);
    });

    it('normalizes QA-driven layout guidance into stable classes', () => {
        const layout = claimWorkflowLayoutGuidance({
            density: 'immersive',
            capture_surface: 'edge_to_edge',
            minimize_scroll: true,
        });

        expect(layout).toEqual({
            density: 'immersive',
            capture_surface: 'edge_to_edge',
            minimize_scroll: true,
        });
        expect(claimWorkflowLayoutClasses(layout)).toEqual([
            'claim-flow--immersive',
            'claim-flow--edge-capture',
            'claim-flow--minimize-scroll',
        ]);
        expect(claimWorkflowLayoutClasses(claimWorkflowLayoutGuidance({}))).toEqual([
            'claim-flow--compact',
            'claim-flow--minimize-scroll',
        ]);
    });
});
