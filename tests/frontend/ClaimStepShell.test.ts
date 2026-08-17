import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import ClaimStepShell from '../../resources/js/components/x-change/ClaimStepShell.vue';

describe('ClaimStepShell', () => {
    it('renders a consistent claim page frame around content', () => {
        const wrapper = mount(ClaimStepShell, {
            slots: {
                default: '<p data-testid="shell-content">Claim content</p>',
            },
        });

        expect(wrapper.find('[data-testid="claim-step-shell"]').exists()).toBe(
            true,
        );
        expect(wrapper.find('[data-testid="claim-step-panel"]').exists()).toBe(
            true,
        );
        expect(wrapper.find('[data-testid="shell-content"]').text()).toBe(
            'Claim content',
        );
        expect(
            wrapper.find('[data-testid="claim-theme-picker"]').exists(),
        ).toBe(true);
    });

    it('renders the Pay Code brand header by default', () => {
        const wrapper = mount(ClaimStepShell, {
            slots: {
                default: '<p>Claim content</p>',
            },
        });

        expect(
            wrapper.find('[data-testid="claim-brand-header"]').exists(),
        ).toBe(true);
        expect(wrapper.find('[data-testid="claim-brand-logo"]').exists()).toBe(
            true,
        );
        expect(
            wrapper.find('[data-testid="claim-brand-logo"]').attributes('src'),
        ).toBe('/vendor/x-change/images/pay-code/pay-code-mark.svg');
        expect(
            wrapper
                .find('[data-testid="claim-brand-logo"]')
                .attributes('style'),
        ).toContain('height: 2rem');
    });

    it('supports centered brand treatment for the entry page', () => {
        const wrapper = mount(ClaimStepShell, {
            props: {
                brandPlacement: 'center',
                brandSize: 'display',
                brandVariant: 'mark',
            },
            slots: {
                default: '<p>Claim content</p>',
            },
        });

        expect(
            wrapper.find('[data-testid="claim-brand-header"]').classes(),
        ).toContain('justify-center');
        expect(
            wrapper
                .find('[data-testid="claim-brand-logo"]')
                .attributes('style'),
        ).toContain('height: 5rem');
        expect(
            wrapper.find('[data-testid="claim-brand-logo"]').attributes('src'),
        ).toBe('/vendor/x-change/images/pay-code/pay-code-mark.svg');
    });

    it('can hide the brand header for embedded use', () => {
        const wrapper = mount(ClaimStepShell, {
            props: {
                showBrand: false,
            },
            slots: {
                default: '<p>Claim content</p>',
            },
        });

        expect(
            wrapper.find('[data-testid="claim-brand-header"]').exists(),
        ).toBe(false);
    });

    it('supports claim outcome tones without changing slotted behavior', () => {
        const wrapper = mount(ClaimStepShell, {
            props: {
                tone: 'warning',
                width: 'sm',
            },
            slots: {
                default: '<button data-testid="claim-action">Continue</button>',
            },
        });

        expect(
            wrapper.find('[data-testid="claim-step-shell"]').classes(),
        ).toContain('from-amber-500/10');
        expect(wrapper.find('[data-testid="claim-step-panel"]').exists()).toBe(
            true,
        );
        expect(wrapper.find('[data-testid="claim-action"]').text()).toBe(
            'Continue',
        );
    });
});
