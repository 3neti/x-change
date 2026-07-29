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
