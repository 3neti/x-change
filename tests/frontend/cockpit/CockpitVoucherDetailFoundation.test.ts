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

        expect(wrapper.text()).toContain('Voucher read-model placeholder');
        expect(wrapper.text()).toContain('Pay Code');
        expect(wrapper.text()).toContain('PC-READY-001');
        expect(wrapper.text()).toContain('Execution ID');
        expect(wrapper.text()).toContain('Deferred');
        expect(wrapper.findAll('[data-testid="cockpit-voucher-overview-item"]')).toHaveLength(6);
    });

    it('renders lifecycle timeline placeholders without invoking execution', () => {
        const wrapper = mount(CockpitVoucherTimelinePanel, {
            props: {
                items: cockpitVoucherTimelineItems,
            },
        });

        expect(wrapper.text()).toContain('Lifecycle facts placeholder');
        expect(wrapper.text()).toContain('Issued');
        expect(wrapper.text()).toContain('Claim started');
        expect(wrapper.text()).toContain('Execution outcome');
        expect(wrapper.text()).toContain('Feedback status');
        expect(wrapper.text()).toContain('no driver is invoked here');
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

        expect(evidence.text()).toContain('Evidence tab placeholder');
        expect(evidence.text()).toContain('Identity evidence');
        expect(evidence.text()).toContain('Settlement envelope evidence');
        expect(evidence.findAll('[data-testid="cockpit-voucher-evidence-item"]')).toHaveLength(3);

        expect(distribution.text()).toContain('Distribution tab placeholder');
        expect(distribution.text()).toContain('SMS');
        expect(distribution.text()).toContain('Email');
        expect(distribution.text()).toContain('In-app');
        expect(distribution.findAll('[data-testid="cockpit-voucher-distribution-item"]')).toHaveLength(3);
    });

    it('renders audit placeholders and disabled actions without command behavior', () => {
        const wrapper = mount(CockpitVoucherAuditPanel, {
            props: {
                audits: cockpitVoucherAuditItems,
                actions: cockpitVoucherDetailActions,
            },
        });

        expect(wrapper.text()).toContain('Audit tab placeholder');
        expect(wrapper.text()).toContain('Journal read model');
        expect(wrapper.text()).toContain('Action handoff');
        expect(wrapper.text()).toContain('Provider callbacks');
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
        expect(wrapper.text()).toContain('Voucher Detail Foundation');
        expect(wrapper.text()).toContain('Overview');
        expect(wrapper.text()).toContain('Timeline');
        expect(wrapper.text()).toContain('Evidence');
        expect(wrapper.text()).toContain('Distribution');
        expect(wrapper.text()).toContain('Audit');
        expect(wrapper.find('[aria-current="page"]').text()).toContain('Pay Codes');
        expect(wrapper.text()).toContain('does not mutate vouchers');
        expect(wrapper.text()).toContain('execute drivers');
        expect(wrapper.text()).toContain('write journal entries');
        expect(wrapper.text()).toContain('send feedback');
        expect(wrapper.text()).toContain('call providers');
        expect(wrapper.text()).toContain('move money');
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
        expect(wrapper.text()).toContain('recipient-wave-46');
        expect(wrapper.text()).toContain('pay_code_detail');
        expect(wrapper.text()).toContain('campaign-navigation-read-only');
        expect(wrapper.text()).toContain('navigation-context-only');
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
