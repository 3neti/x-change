import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import PayoutRecovery from '../../resources/js/pages/x-change/claim/PayoutRecovery.vue';

vi.mock('@inertiajs/vue3', () => ({
    Head: { template: '<div><slot /></div>' },
    Form: {
        props: ['action', 'method'],
        template:
            '<form :action="action" :method="method"><slot :errors="{}" :processing="false" /></form>',
    },
}));

vi.mock('lucide-vue-next', () => ({
    CircleCheck: { template: '<span />' },
    CircleOff: { template: '<span />' },
    KeyRound: { template: '<span />' },
    Landmark: { template: '<span />' },
    ShieldCheck: { template: '<span />' },
}));

vi.mock('@/components/x-change/ClaimStepShell.vue', () => ({
    default: {
        props: ['tone', 'width', 'centered'],
        template: '<main data-testid="claim-step-shell"><slot /></main>',
    },
}));

vi.mock('@/components/financial/BankEMISelect.vue', () => ({
    default: {
        props: ['modelValue', 'settlementRail', 'disabled'],
        emits: ['update:modelValue'],
        template:
            '<button type="button" data-testid="bank-selector" @click="$emit(\'update:modelValue\', \'BDO\')">Choose destination</button>',
    },
}));

const baseProps = {
    code: 'SAFE',
    amount: { minor: 2500, currency: 'PHP' },
    settlement_rail: 'INSTAPAY',
    expires_at: '2026-09-01T00:00:00+08:00',
};

describe('campaign payout recovery page', () => {
    it('starts OTP only through the scoped recovery route', () => {
        const wrapper = mount(PayoutRecovery, {
            props: { ...baseProps, status: 'available' },
        });

        expect(wrapper.get('[data-testid="recovery-amount"]').text()).toContain('₱25.00');
        expect(wrapper.text()).not.toContain('0722');
        expect(wrapper.text()).not.toContain('01JRECOVERY');
        expect(wrapper.get('[data-testid="recovery-start-step"] form').attributes('action')).toBe(
            '/x/claim/SAFE/payout-recovery/challenge',
        );
        expect(wrapper.find('[data-testid="recovery-destination-step"]').exists()).toBe(false);
    });

    it('requires OTP before showing correction fields', () => {
        const wrapper = mount(PayoutRecovery, {
            props: { ...baseProps, status: 'otp_pending' },
        });

        expect(wrapper.get('[data-testid="recovery-otp-code"]').attributes('maxlength')).toBe('6');
        expect(wrapper.get('[data-testid="recovery-otp-step"] form').attributes('action')).toContain(
            '/verification',
        );
        expect(wrapper.find('[data-testid="recovery-account-number"]').exists()).toBe(false);
    });

    it('submits a corrected destination only after verification', async () => {
        const wrapper = mount(PayoutRecovery, {
            props: { ...baseProps, status: 'verified' },
        });

        expect(wrapper.get('[data-testid="recovery-submit-destination"]').attributes()).toHaveProperty(
            'disabled',
        );

        await wrapper.get('[data-testid="bank-selector"]').trigger('click');

        expect(wrapper.get('input[name="bank_code"]').attributes('value')).toBe('BDO');
        expect(wrapper.get('[data-testid="recovery-submit-destination"]').attributes()).not.toHaveProperty(
            'disabled',
        );
        expect(wrapper.get('[data-testid="recovery-destination-step"] form').attributes('action')).toContain(
            '/destination',
        );
    });

    it('does not expose destination controls after the one-time grant is consumed', () => {
        const wrapper = mount(PayoutRecovery, {
            props: { ...baseProps, status: 'consumed' },
        });

        expect(wrapper.get('[data-testid="recovery-complete-step"]').text()).toContain(
            'Pay Code claim has been completed',
        );
        expect(wrapper.find('[data-testid="recovery-account-number"]').exists()).toBe(false);
    });
});
