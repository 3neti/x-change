import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import CockpitBankTransferReconciliationReference from '../../../resources/js/cockpit/components/CockpitBankTransferReconciliationReference.vue';

describe('Cockpit Bank Transfer Reconciliation Reference', () => {
    it.each([
        ['required', 'Required'],
        ['optional', 'Optional'],
    ] as const)(
        'renders the %s server-authored reference mode',
        async (mode, label) => {
            const wrapper = mount(CockpitBankTransferReconciliationReference, {
                props: {
                    reference: {
                        mode,
                        value: 'FUND-7K2P',
                        label: 'Transfer reference',
                        instructions: 'Include this reference exactly.',
                    },
                },
            });

            expect(wrapper.text()).toContain(label);
            expect(wrapper.text()).toContain('FUND-7K2P');
            expect(wrapper.text()).toContain('Include this reference exactly.');

            await wrapper
                .get(
                    '[data-testid="copy-bank-transfer-reconciliation-reference"]',
                )
                .trigger('click');

            expect(wrapper.emitted('copy')?.[0]).toEqual(['FUND-7K2P']);
        },
    );

    it('renders nothing when sender references are disabled', () => {
        const wrapper = mount(CockpitBankTransferReconciliationReference, {
            props: {
                reference: {
                    mode: 'disabled',
                    value: null,
                    label: 'Transfer reference',
                    instructions:
                        'No sender-entered reference is authoritative.',
                },
            },
        });

        expect(wrapper.html()).toBe('<!--v-if-->');
    });
});
