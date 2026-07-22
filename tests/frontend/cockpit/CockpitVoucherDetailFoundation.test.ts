import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import CockpitVoucherAuditPanel from '../../../resources/js/cockpit/components/CockpitVoucherAuditPanel.vue';
import CockpitVoucherDistributionPanel from '../../../resources/js/cockpit/components/CockpitVoucherDistributionPanel.vue';
import CockpitVoucherEvidencePanel from '../../../resources/js/cockpit/components/CockpitVoucherEvidencePanel.vue';
import CockpitVoucherOverviewPanel from '../../../resources/js/cockpit/components/CockpitVoucherOverviewPanel.vue';
import CockpitVoucherTimelinePanel from '../../../resources/js/cockpit/components/CockpitVoucherTimelinePanel.vue';
import VoucherDetail from '../../../resources/js/cockpit/pages/VoucherDetail.vue';
import {
    cockpitVoucherAuditItems,
    cockpitVoucherDetailActions,
    cockpitVoucherDistributionItems,
    cockpitVoucherEvidenceItems,
    cockpitVoucherOverviewItems,
    cockpitVoucherTimelineItems,
} from '../../../resources/js/cockpit/voucherDetailDefaults';

describe('Cockpit Voucher Detail foundation', () => {
    it('renders voucher overview facts from read-only defaults', () => {
        const wrapper = mount(CockpitVoucherOverviewPanel, {
            props: {
                items: cockpitVoucherOverviewItems,
            },
        });

        expect(wrapper.text()).toContain('Pay Code facts');
        expect(wrapper.text()).toContain('Pay Code');
        expect(wrapper.text()).toContain('PC-READY-001');
        expect(wrapper.text()).toContain('Execution ID');
        expect(wrapper.text()).toContain('Deferred');
        expect(wrapper.find('[data-testid="cockpit-voucher-overview-panel"]').element.tagName.toLowerCase()).toBe('details');
        expect(wrapper.find('[data-testid="cockpit-voucher-overview-panel"]').attributes('open')).toBeUndefined();
        expect(wrapper.find('summary').text()).toContain('6 facts');
        expect(wrapper.findAll('[data-testid="cockpit-voucher-overview-item"]')).toHaveLength(6);
        expect(wrapper.findAll('[data-testid="cockpit-voucher-overview-item"]')[0].classes()).toContain('p-3');
    });

    it('renders lifecycle timeline placeholders without invoking execution', () => {
        const wrapper = mount(CockpitVoucherTimelinePanel, {
            props: {
                items: cockpitVoucherTimelineItems,
            },
        });

        expect(wrapper.text()).toContain('Lifecycle timeline');
        expect(wrapper.text()).toContain('Issued');
        expect(wrapper.text()).toContain('Claim started');
        expect(wrapper.text()).toContain('Execution outcome');
        expect(wrapper.text()).toContain('Notification status');
        expect(wrapper.text()).toContain('no driver is invoked here');
        expect(wrapper.find('[data-testid="cockpit-voucher-timeline-panel"]').element.tagName.toLowerCase()).toBe('details');
        expect(wrapper.find('[data-testid="cockpit-voucher-timeline-panel"]').attributes('open')).toBeUndefined();
        expect(wrapper.find('[data-testid="cockpit-voucher-timeline-panel"]').classes()).toContain('self-start');
        expect(wrapper.find('summary').text()).toContain('4 events');
        expect(wrapper.findAll('[data-testid="cockpit-voucher-timeline-item"]')).toHaveLength(4);
    });

    it('renders evidence and distribution placeholders from supplied read models', () => {
        const evidence = mount(CockpitVoucherEvidencePanel, {
            props: {
                items: cockpitVoucherEvidenceItems,
            },
        });

        const distribution = mount(CockpitVoucherDistributionPanel, {
            props: {
                items: cockpitVoucherDistributionItems,
            },
        });

        expect(evidence.text()).toContain('Evidence status');
        expect(evidence.text()).toContain('Identity evidence');
        expect(evidence.text()).toContain('Settlement envelope evidence');
        expect(evidence.find('[data-testid="cockpit-voucher-evidence-panel"]').element.tagName.toLowerCase()).toBe('details');
        expect(evidence.find('[data-testid="cockpit-voucher-evidence-panel"]').attributes('open')).toBeUndefined();
        expect(evidence.find('summary').text()).toContain('3 facts');
        expect(evidence.find('[data-testid="cockpit-voucher-evidence-density-summary"]').text()).toContain('Evidence Facts');
        expect(evidence.find('[data-testid="cockpit-voucher-evidence-density-summary"]').text()).toContain('3');
        expect(evidence.findAll('[data-testid="cockpit-voucher-evidence-item-metadata"]')).toHaveLength(0);
        expect(evidence.findAll('[data-testid="cockpit-voucher-evidence-item"]')).toHaveLength(3);

        expect(distribution.text()).toContain('Notification status');
        expect(distribution.text()).toContain('SMS');
        expect(distribution.text()).toContain('Email');
        expect(distribution.text()).toContain('In-app');
        expect(distribution.find('[data-testid="cockpit-voucher-distribution-panel"]').element.tagName.toLowerCase()).toBe('details');
        expect(distribution.find('[data-testid="cockpit-voucher-distribution-panel"]').attributes('open')).toBeUndefined();
        expect(distribution.find('summary').text()).toContain('3 channels');
        expect(distribution.find('[data-testid="cockpit-voucher-distribution-density-summary"]').text()).toContain('Channels');
        expect(distribution.find('[data-testid="cockpit-voucher-distribution-density-summary"]').text()).toContain('Status Summary');
        expect(distribution.find('[data-testid="cockpit-voucher-distribution-density-summary"]').text()).toContain('3');
        expect(distribution.findAll('[data-testid="cockpit-voucher-distribution-item"]')).toHaveLength(3);
        expect(distribution.findAll('[data-testid="cockpit-voucher-distribution-item-disclosure"]')).toHaveLength(3);
    });

    it('renders audit placeholders and disabled actions without command behavior', () => {
        const wrapper = mount(CockpitVoucherAuditPanel, {
            props: {
                audits: cockpitVoucherAuditItems,
                actions: cockpitVoucherDetailActions,
            },
        });

        expect(wrapper.text()).toContain('Audit and follow-up details');
        expect(wrapper.text()).toContain('Audit trail');
        expect(wrapper.text()).toContain('Follow-up guidance');
        expect(wrapper.text()).toContain('Provider callbacks');
        expect(wrapper.find('[data-testid="cockpit-voucher-audit-panel"]').element.tagName.toLowerCase()).toBe('details');
        expect(wrapper.find('[data-testid="cockpit-voucher-audit-panel"]').attributes('open')).toBeUndefined();
        expect(wrapper.find('[data-testid="cockpit-voucher-audit-panel"]').classes()).toContain('py-2.5');
        expect(wrapper.find('[data-testid="cockpit-voucher-audit-summary"]').classes()).toContain('sm:flex-row');
        expect(wrapper.find('[data-testid="cockpit-voucher-audit-summary"]').text()).not.toContain('View details');
        expect(wrapper.find('[data-testid="cockpit-voucher-audit-density-summary"]').text()).toContain('evidence');
        expect(wrapper.find('[data-testid="cockpit-voucher-audit-density-summary"]').text()).toContain('3');
        expect(wrapper.find('[data-testid="cockpit-voucher-audit-density-summary"]').text()).toContain('connected');
        expect(wrapper.find('[data-testid="cockpit-voucher-audit-density-summary"]').text()).toContain('disabled follow-ups');
        expect(wrapper.find('[data-testid="cockpit-voucher-audit-density-summary"]').text()).toContain('4');
        expect(wrapper.find('[data-testid="cockpit-voucher-audit-guidance"]').text()).toContain('does not execute actions');
        expect(wrapper.find('[data-testid="cockpit-voucher-disabled-actions-disclosure"]').exists()).toBe(true);
        expect(wrapper.findAll('[data-testid="cockpit-voucher-audit-item"]')).toHaveLength(3);

        const actions = wrapper.findAll('[data-testid="cockpit-voucher-detail-action"]');

        expect(actions).toHaveLength(4);

        for (const action of actions) {
            expect(action.attributes('disabled')).toBeDefined();
            expect(action.attributes('title')).toBeTruthy();
        }
    });

    it('renders the full voucher detail page with active Pay Codes navigation and side-effect boundaries', () => {
        const wrapper = mount(VoucherDetail);

        expect(wrapper.find('[data-testid="cockpit-voucher-detail-shell"]').exists()).toBe(true);
        expect(wrapper.text()).toContain('Pay Code Detail');
        expect(wrapper.text()).toContain('Pay Code inspection');
        expect(wrapper.text()).toContain('Inspect lifecycle state, claim readiness, and connected evidence');
        expect(wrapper.text()).toContain('Operator next step');
        expect(wrapper.text()).toContain('Lifecycle guidance');
        expect(wrapper.find('[data-testid="cockpit-voucher-detail-primary-summary"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="cockpit-voucher-detail-primary-actions"]').classes()).toContain('items-start');
        expect(wrapper.find('[data-testid="cockpit-voucher-detail-lifecycle-guidance"]').classes()).toContain('self-start');
        expect(wrapper.find('[data-testid="cockpit-voucher-detail-primary-distribution-link"]').text()).toContain('Open distribution workspace');
        expect(wrapper.find('[data-testid="cockpit-voucher-detail-primary-explorer-link"]').text()).toContain('Back to Pay Codes');
        expect(wrapper.text()).toContain('Overview');
        expect(wrapper.text()).toContain('Timeline');
        expect(wrapper.text()).toContain('Evidence');
        expect(wrapper.text()).toContain('Distribution');
        expect(wrapper.text()).toContain('Audit');
        expect(wrapper.find('[data-testid="cockpit-voucher-secondary-content"]').classes()).toContain('space-y-3');
        expect(wrapper.find('[data-testid="cockpit-voucher-supporting-evidence-grid"]').classes()).toContain('gap-3');
        expect(wrapper.find('[data-testid="cockpit-voucher-supporting-evidence-grid"]').classes()).toContain('items-start');
        expect(wrapper.find('[data-testid="cockpit-voucher-supporting-evidence-stack"]').classes()).toContain('space-y-3');
        expect(wrapper.find('[aria-current="page"]').text()).toContain('Pay Codes');
        expect(wrapper.text()).toContain('Inspection only');
        expect(wrapper.text()).toContain('cannot change the Pay Code');
        expect(wrapper.text()).toContain('send messages');
        expect(wrapper.text()).toContain('call providers');
        expect(wrapper.text()).toContain('move money');
    });

    it('renders the voucher shell as a sleek operational header', () => {
        const wrapper = mount(VoucherDetail, {
            props: {
                context: { code: 'PC-DETAIL-001' },
            },
        });

        const header = wrapper.find('[data-testid="cockpit-voucher-detail-header"]');
        const headerRow = wrapper.find('[data-testid="cockpit-voucher-detail-header-row"]');
        const facts = wrapper.find('[data-testid="cockpit-voucher-detail-header-facts"]');
        const boundary = wrapper.find('[data-testid="cockpit-voucher-detail-boundary"]');

        expect(header.classes()).toContain('py-3');
        expect(header.classes()).not.toContain('p-6');
        expect(headerRow.classes()).toContain('lg:items-center');
        expect(facts.findAll('[data-testid="cockpit-voucher-detail-header-fact"]')).toHaveLength(3);
        expect(facts.classes()).toContain('p-1.5');
        expect(boundary.element.tagName.toLowerCase()).toBe('details');
        expect(boundary.find('summary').text()).toContain('Read-only limits');
        expect(boundary.text()).toContain('open or copy the claim URL');
        expect(boundary.text()).toContain('cannot change the Pay Code');
        expect(boundary.text()).not.toContain('execute drivers');
    });

    it('renders safe campaign recipient context on voucher detail without mutation controls', () => {
        const wrapper = mount(VoucherDetail, {
            props: {
                campaign_navigation_context: {
                    schema: 'x-change.cockpit.campaign-navigation.v1',
                    status: 'available',
                    authorized: true,
                    source: 'x_campaign_adapter',
                    planning_key: 'plan-wave-46',
                    execution_id: 'exec-wave-46',
                    campaign_id: 'campaign-wave-46',
                    audience_id: 'audience-wave-46',
                    recipient_id: 'recipient-wave-46',
                    destination: 'pay_code_detail',
                    read_only: true,
                    mutation: {
                        enabled: false,
                        status: 'blocked',
                        reason: 'campaign-navigation-read-only',
                    },
                    redactions: {
                        payloads: 'navigation-context-only',
                    },
                    provider_payload: 'must-not-render',
                    raw_payload: 'must-not-render',
                    wallet: 'must-not-render',
                    mutation_route: '/must-not-render',
                },
            },
        });

        expect(wrapper.find('[data-testid="cockpit-voucher-detail-campaign-navigation-context"]').exists()).toBe(true);
        expect(wrapper.text()).toContain('Campaign context');
        expect(wrapper.text()).toContain('Opened from campaign activity');
        expect(wrapper.text()).toContain('only change the read-only Cockpit view');
        expect(wrapper.text()).toContain('plan-wave-46');
        expect(wrapper.text()).toContain('exec-wave-46');
        expect(wrapper.text()).toContain('campaign-wave-46');
        expect(wrapper.text()).toContain('audience-wave-46');
        expect(wrapper.text()).toContain('recipient-wave-46');
        expect(wrapper.text()).toContain('Campaign package adapter');
        expect(wrapper.text()).toContain('Pay Code Detail');
        expect(wrapper.text()).toContain('Campaign navigation only');
        expect(wrapper.text()).toContain('Navigation context only');
        expect(wrapper.text()).not.toContain('x_campaign_adapter');
        expect(wrapper.text()).not.toContain('pay_code_detail');
        expect(wrapper.text()).not.toContain('campaign-navigation-read-only');
        expect(wrapper.text()).not.toContain('navigation-context-only');
        expect(wrapper.find('[data-testid="cockpit-voucher-detail-campaign-explorer-return-link"]').attributes('href')).toBe('/x/cockpit/pay-codes?campaign_planning_key=plan-wave-46&campaign_execution_id=exec-wave-46&campaign_id=campaign-wave-46&campaign_audience_id=audience-wave-46&campaign_recipient_id=recipient-wave-46&campaign_source=x_campaign_adapter&activity_code=Not+wired&activity_source=operator_issuance_activity');
        expect(wrapper.find('[data-testid="cockpit-voucher-detail-campaign-explorer-return-link"]').text()).toContain('Back to Explorer');
        expect(wrapper.find('[data-testid="cockpit-voucher-detail-campaign-explorer-return-link"]').text()).toContain('read-only');
        expect(wrapper.find('[data-testid="cockpit-voucher-detail-campaign-dashboard-return-link"]').attributes('href')).toBe('/x/cockpit?campaign_planning_key=plan-wave-46&campaign_execution_id=exec-wave-46&campaign_id=campaign-wave-46&campaign_audience_id=audience-wave-46&campaign_recipient_id=recipient-wave-46&campaign_source=x_campaign_adapter');
        expect(wrapper.find('[data-testid="cockpit-voucher-detail-campaign-dashboard-return-link"]').text()).toContain('Back to Campaign Dashboard');
        expect(wrapper.find('[data-testid="cockpit-voucher-detail-campaign-dashboard-return-link"]').text()).toContain('read-only');
        expect(wrapper.text()).not.toContain('must-not-render');
        expect(wrapper.text()).not.toContain('provider_payload');
        expect(wrapper.text()).not.toContain('raw_payload');
        expect(wrapper.text()).not.toContain('wallet');
        expect(wrapper.text()).not.toContain('/must-not-render');
    });

    it('does not render campaign context on voucher detail for the wrong destination', () => {
        const wrapper = mount(VoucherDetail, {
            props: {
                campaign_navigation_context: {
                    status: 'available',
                    authorized: true,
                    planning_key: 'plan-wave-46',
                    execution_id: 'exec-wave-46',
                    destination: 'distribution_workspace',
                    read_only: true,
                },
            },
        });

        expect(wrapper.find('[data-testid="cockpit-voucher-detail-campaign-navigation-context"]').exists()).toBe(false);
        expect(wrapper.find('[data-testid="cockpit-voucher-detail-campaign-explorer-return-link"]').exists()).toBe(false);
        expect(wrapper.find('[data-testid="cockpit-voucher-detail-campaign-dashboard-return-link"]').exists()).toBe(false);
        expect(wrapper.text()).not.toContain('plan-wave-46');
    });
});
