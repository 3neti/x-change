import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import MobileVerification from '../../resources/js/pages/x-change/onboarding/MobileVerification.vue';

vi.mock('@inertiajs/vue3', async (importOriginal) => ({
    ...(await importOriginal<typeof import('@inertiajs/vue3')>()),
    Head: { template: '<div><slot /></div>' },
}));

describe('mobile verification onboarding', () => {
    it('explains the funding boundary and exposes the local simulation code safely', () => {
        const wrapper = mount(MobileVerification, {
            props: {
                mobile: '63••••••1987',
                verified: false,
                challenge: {
                    status: 'pending',
                    attempts: 0,
                    expires_at: '2026-07-23T20:00:00+08:00',
                },
                local_code: '000000',
            },
        });

        expect(
            wrapper.get('[data-testid="mobile-verification-page"]').text(),
        ).toContain('Verify your mobile');
        expect(wrapper.text()).toContain('63••••••1987');
        expect(wrapper.text()).toContain('Local simulation code: 000000');
        expect(wrapper.text()).toContain('A webhook never creates a user');
        expect(wrapper.text()).not.toContain('639173011987');
    });

    it('renders a verified completion state without the OTP form', () => {
        const wrapper = mount(MobileVerification, {
            props: {
                mobile: '63••••••1987',
                verified: true,
            },
        });

        expect(wrapper.text()).toContain('Verified');
        expect(wrapper.text()).toContain(
            'can now resolve your Account during provider-confirmed QR Ph funding',
        );
        expect(
            wrapper.find('[data-testid="mobile-verification-code"]').exists(),
        ).toBe(false);
    });
});
