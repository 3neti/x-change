import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import CockpitFundingMethodSelector from '../../../resources/js/cockpit/components/CockpitFundingMethodSelector.vue';
import type { CockpitFundingMethodSelectorReadModel } from '../../../resources/js/cockpit/types';

const selector: CockpitFundingMethodSelectorReadModel = {
    schema: 'x-change.cockpit.funding-method-selector.v1',
    context: 'account_funding',
    intent_reference: null,
    amount: null,
    expires_at: null,
    status: 'ready',
    notice: 'Funds appear in your Account only after confirmation from the bank or payment provider.',
    methods: [
        {
            key: 'qr_ph',
            workspace_mode: 'self_top_up',
            label: 'QR Ph',
            description: 'Scan the QR.',
            available: true,
            selectable: true,
            unavailable_reason: null,
        },
        {
            key: 'bank_transfer',
            workspace_mode: 'bank_transfer',
            label: 'Bank Transfer',
            description: 'Transfer to the bank.',
            available: false,
            selectable: true,
            unavailable_reason: 'Bank transfer is not configured.',
        },
        {
            key: 'pay_code',
            workspace_mode: 'pay_code',
            label: 'Pay Code',
            description: 'Use a Pay Code.',
            available: true,
            selectable: true,
            unavailable_reason: null,
        },
    ],
    bank_transfer: {
        reconciliation_reference: {
            mode: 'disabled',
            value: null,
            label: 'Transfer reference',
            instructions: 'No sender-entered reference is authoritative.',
        },
        matching_strategies: [
            'reserved_exact_amount',
            'destination_account',
            'currency',
            'observation_window',
        ],
    },
};

describe('Cockpit Funding Method Selector', () => {
    it('renders server-authored methods and selects without making provider calls', async () => {
        const wrapper = mount(CockpitFundingMethodSelector, {
            props: {
                selector,
                modelValue: 'self_top_up',
            },
        });

        expect(wrapper.text()).toContain('QR Ph');
        expect(wrapper.text()).toContain('Bank Transfer');
        expect(wrapper.text()).toContain('Pay Code');
        expect(
            wrapper
                .get('[data-testid="funding-mode-bank_transfer"]')
                .attributes('aria-describedby'),
        ).toBe('funding-method-bank_transfer-availability');

        await wrapper
            .get('[data-testid="funding-mode-bank_transfer"]')
            .trigger('click');

        expect(wrapper.emitted('update:modelValue')?.[0]).toEqual([
            'bank_transfer',
        ]);
    });

    it('supports tab keyboard navigation and skips non-selectable methods', async () => {
        const wrapper = mount(CockpitFundingMethodSelector, {
            attachTo: document.body,
            props: {
                selector: {
                    ...selector,
                    methods: selector.methods.map((method) =>
                        method.key === 'bank_transfer'
                            ? { ...method, selectable: false }
                            : method,
                    ),
                },
                modelValue: 'self_top_up',
            },
        });

        await wrapper
            .get('[data-testid="funding-mode-self_top_up"]')
            .trigger('keydown', { key: 'ArrowRight' });

        expect(wrapper.emitted('update:modelValue')?.[0]).toEqual(['pay_code']);
        expect(
            wrapper
                .get('[data-testid="funding-mode-bank_transfer"]')
                .attributes('disabled'),
        ).toBeDefined();

        wrapper.unmount();
    });
});
