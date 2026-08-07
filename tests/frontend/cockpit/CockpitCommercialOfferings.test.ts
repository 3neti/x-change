import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import CommercialOfferings from '../../../resources/js/cockpit/pages/CommercialOfferings.vue';

vi.mock('@inertiajs/vue3', async (importOriginal) => ({
    ...(await importOriginal<typeof import('@inertiajs/vue3')>()),
    Head: { template: '<div><slot /></div>' },
}));

const active = {
    reference: 'commercial-offering:pay_code',
    version: 1,
    effective_at: '2026-08-07T00:00:00+00:00',
    catalog: {
        reference: 'pay-code',
        version: 3,
        currency: 'PHP',
        items: [
            {
                reference: 'cash.amount',
                label: 'Transaction Fee',
                category: 'base',
                currency: 'PHP',
                unit_price_minor: 1500,
            },
            {
                reference: 'inputs.fields.otp',
                label: 'OTP Verification',
                category: 'input_fields',
                currency: 'PHP',
                unit_price_minor: 200,
            },
        ],
    },
    waterfall_policy: {
        reference: 'pay-code-commercial-waterfall',
        version: 1,
        currency: 'PHP',
        rules: [
            {
                reference: 'provider-transfer-cost',
                sequence: 10,
                line_type: 'deduction' as const,
                category: 'provider_cost',
                recipient_reference: 'provider:settlement-rail',
                fixed_amount_minor: 1000,
                basis_points: null,
                minimum_amount_minor: null,
                maximum_amount_minor: null,
                participant_role: null,
            },
            {
                reference: 'commercial-residual',
                sequence: 40,
                line_type: 'residual' as const,
                category: 'commercial_revenue',
                recipient_reference: 'operator:x-change',
                fixed_amount_minor: null,
                basis_points: null,
                minimum_amount_minor: null,
                maximum_amount_minor: null,
                participant_role: null,
            },
        ],
    },
    legal_trace: {
        jurisdiction: 'PH',
        profile: 'treasury-settlement-ph-v1',
        decision: 'advisory_review_required',
    },
};

describe('Cockpit Commercial Offering administration', () => {
    it('keeps the Price List primary and exposes the Waterfall without mixing their meaning', async () => {
        const wrapper = mount(CommercialOfferings, {
            props: {
                commercialOffering: {
                    profile: 'pay_code',
                    active,
                    source: 'package_default',
                    can_manage: true,
                    can_approve: false,
                    pending: [],
                },
            },
            global: { stubs: { Head: true } },
        });

        expect(wrapper.text()).toContain('Price List & Waterfall');
        expect(wrapper.text()).toContain('Transaction Fee');
        expect(wrapper.text()).toContain('OTP Verification');
        expect(wrapper.text()).toContain('Submit New Version');

        await wrapper.get('button:nth-of-type(2)').trigger('click');

        expect(wrapper.text()).toContain('provider cost');
        expect(wrapper.text()).toContain('commercial revenue');
        expect(wrapper.text()).toContain('Independent Maker–Checker');
    });

    it('shows pending publication as a checker action without exposing edits', () => {
        const wrapper = mount(CommercialOfferings, {
            props: {
                commercialOffering: {
                    profile: 'pay_code',
                    active,
                    source: 'published',
                    can_manage: false,
                    can_approve: true,
                    pending: [
                        {
                            id: 7,
                            reference: 'commercial-offering:pay_code',
                            version: 2,
                            snapshot_hash: 'a'.repeat(64),
                            effective_at: '2026-08-07T00:00:00+00:00',
                            submitted_at: '2026-08-07T00:00:00+00:00',
                            maker: { type: 'App\\Models\\User', id: 5 },
                        },
                    ],
                },
            },
            global: { stubs: { Head: true } },
        });

        expect(wrapper.text()).toContain('Independent Approval');
        expect(wrapper.text()).toContain('Approve & Publish');
        expect(wrapper.text()).not.toContain('Submit New Version');
    });
});
