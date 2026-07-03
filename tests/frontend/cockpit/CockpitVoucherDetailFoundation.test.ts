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
});
