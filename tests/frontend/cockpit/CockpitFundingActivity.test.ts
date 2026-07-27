import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import CockpitFundingActivity from '../../../resources/js/cockpit/components/CockpitFundingActivity.vue';
import type { CockpitFundingActivityReadModel } from '../../../resources/js/cockpit/types';

const activity: CockpitFundingActivityReadModel = {
    schema: 'x-change.cockpit.funding-activity.v1',
    items: [
        {
            key: 'standing_receipt:AF-1001',
            source: 'standing_funding_receipt',
            reference: 'AF-1001',
            display_reference: 'AF-1001',
            method: 'qr_ph',
            method_label: 'QR Ph',
            amount: '₱50.00',
            status: 'recognized',
            status_label: 'Recognized',
            updated_at: '2026-07-27T10:00:00+08:00',
            timestamps: {
                requested_at: null,
                observed_at: '2026-07-27T09:59:00+08:00',
                recognized_at: '2026-07-27T10:00:00+08:00',
            },
            summary: 'NetBank settled',
            action_keys: [],
        },
        {
            key: 'request:TRANSFER-1001',
            source: 'funding_request',
            reference: 'TRANSFER-1001',
            display_reference: 'TRANSFER-1001',
            method: 'bank_transfer',
            method_label: 'Bank Transfer',
            amount: '₱1,005.37',
            status: 'awaiting_payment',
            status_label: 'Awaiting payment',
            updated_at: '2026-07-27T09:50:00+08:00',
            timestamps: {
                requested_at: '2026-07-27T09:50:00+08:00',
                observed_at: null,
                recognized_at: null,
            },
            summary: 'NetBank ••••0019',
            action_keys: ['view_instructions', 'check_provider'],
            request_reference: 'TRANSFER-1001',
        },
    ],
    filters: [
        { key: 'all', label: 'All' },
        { key: 'qr_ph', label: 'QR Ph' },
        { key: 'bank_transfer', label: 'Bank Transfer' },
        { key: 'pay_code', label: 'Pay Code' },
        { key: 'reviewed_value', label: 'Reviewed Value' },
    ],
    redactions: {
        payer_identity_exposed: false,
        provider_transaction_id_exposed: false,
        raw_evidence_exposed: false,
    },
};

describe('Cockpit Funding Activity', () => {
    it('renders one desktop table and one consistent mobile card list', () => {
        const wrapper = mount(CockpitFundingActivity, {
            props: { activity },
        });

        expect(wrapper.text()).toContain('Funding Activity');
        expect(wrapper.findAll('table tbody tr')).toHaveLength(2);
        expect(
            wrapper
                .get('[data-testid="funding-activity-mobile-list"]')
                .findAll('li'),
        ).toHaveLength(2);
        expect(wrapper.text()).toContain('QR Ph');
        expect(wrapper.text()).toContain('Bank Transfer');
        expect(wrapper.text()).toContain('Recognized');
        expect(wrapper.text()).toContain('Awaiting payment');
    });

    it('filters records and emits method-specific controls', async () => {
        const wrapper = mount(CockpitFundingActivity, {
            props: { activity },
        });

        await wrapper
            .get('[data-testid="funding-activity-filter-bank_transfer"]')
            .trigger('click');

        expect(wrapper.findAll('table tbody tr')).toHaveLength(1);
        expect(wrapper.get('table').text()).toContain('TRANSFER-1001');
        expect(wrapper.get('table').text()).not.toContain('AF-1001');

        await wrapper
            .get(
                '[data-testid="funding-activity-action-check_provider-request:TRANSFER-1001"]',
            )
            .trigger('click');

        expect(wrapper.emitted('checkProvider')?.[0]?.[0]).toMatchObject({
            key: 'request:TRANSFER-1001',
            method: 'bank_transfer',
        });
    });

    it('shows a stable empty state', () => {
        const wrapper = mount(CockpitFundingActivity, {
            props: {
                activity: {
                    ...activity,
                    items: [],
                },
            },
        });

        expect(wrapper.text()).toContain('No Funding Activity yet.');
    });
});
