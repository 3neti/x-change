import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import Approval from '../../resources/js/pages/x-change/claim/Approval.vue';

const { post } = vi.hoisted(() => ({
    post: vi.fn(),
}));

const pageProps = vi.hoisted(() => ({
    value: {
        errors: {},
    } as {
        errors: Record<string, string>;
    },
}));

vi.mock('@inertiajs/vue3', () => ({
    Head: {
        template: '<div><slot /></div>',
    },
    usePage: () => ({
        props: pageProps.value,
    }),
    router: {
        post,
    },
}));

vi.mock('@/components/ui/card', () => ({
    Card: {
        template: '<div><slot /></div>',
    },
    CardContent: {
        template: '<div><slot /></div>',
    },
}));

vi.mock('lucide-vue-next', () => ({
    Clock3: {
        template: '<span data-testid="approval-clock-icon" />',
    },
}));

const submitApprovalOtp = vi.fn();

vi.mock('../../resources/js/components/x-change/approvalOtpSubmitAdapter', () => ({
    submitApprovalOtp: (...args: unknown[]) => submitApprovalOtp(...args),
}));

beforeEach(() => {
    post.mockClear();
    submitApprovalOtp.mockClear();
    pageProps.value.errors = {};
});

describe('Claim approval page', () => {
    it('renders default pending approval copy', () => {
        const wrapper = mount(Approval, {
            props: {
                voucher: {
                    code: 'TEST123',
                },
                compiled_claim_result: null,
                message: null,
            },
        });

        expect(wrapper.find('[data-testid="approval-clock-icon"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="approval-title"]').text()).toBe('Claim submitted for processing');
        expect(wrapper.find('[data-testid="approval-message"]').text()).toBe('Your claim has been submitted and is awaiting approval.');
        expect(wrapper.find('[data-testid="approval-voucher-code"]').text()).toBe('TEST123');
        expect(wrapper.find('[data-testid="approval-amount"]').exists()).toBe(false);
        expect(wrapper.find('[data-testid="approval-messages"]').exists()).toBe(false);
    });

    it('renders pending compiled claim result details', () => {
        const wrapper = mount(Approval, {
            props: {
                voucher: {
                    code: 'TEST123',
                },
                compiled_claim_result: {
                    status: 'pending',
                    claim_type: 'withdraw',
                    voucher_code: 'TEST123',
                    claimed: false,
                    requested_amount: null,
                    disbursed_amount: 1000,
                    currency: 'PHP',
                    remaining_balance: null,
                    fully_claimed: false,
                    messages: ['Approval required.'],
                },
                message: 'Please wait while your claim is reviewed.',
            },
        });

        expect(wrapper.find('[data-testid="approval-title"]').text()).toBe('Claim submitted for processing');
        expect(wrapper.find('[data-testid="approval-message"]').text()).toBe('Please wait while your claim is reviewed.');
        expect(wrapper.find('[data-testid="approval-voucher-code"]').text()).toBe('TEST123');
        expect(wrapper.find('[data-testid="approval-amount"]').text()).toBe('PHP 1,000.00');
        expect(wrapper.find('[data-testid="approval-messages"]').text()).toContain('Approval required.');
    });

    it('renders OTP approval form when OTP is required', () => {
        const wrapper = mount(Approval, {
            props: {
                voucher: {
                    code: 'TEST123',
                },
                compiled_claim_result: {
                    status: 'pending',
                    approval_metadata: {
                        otp_required: true,
                    },
                },
                message: null,
            },
        });

        expect(wrapper.find('[data-testid="approval-otp-form"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="approval-otp-input"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="approval-otp-submit"]').text()).toBe('Verify OTP');
        expect(wrapper.find('[data-testid="approval-polling-notice"]').exists()).toBe(false);
        expect(wrapper.find('[data-testid="approval-manual-review-notice"]').exists()).toBe(false);
    });

    it('renders polling notice when polling is required', () => {
        const wrapper = mount(Approval, {
            props: {
                voucher: {
                    code: 'TEST123',
                },
                compiled_claim_result: {
                    status: 'pending',
                    approval_metadata: {
                        polling_required: true,
                    },
                },
                message: null,
            },
        });

        expect(wrapper.find('[data-testid="approval-polling-notice"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="approval-otp-form"]').exists()).toBe(false);
        expect(wrapper.find('[data-testid="approval-manual-review-notice"]').exists()).toBe(false);
    });

    it('renders manual review notice when manual review is required', () => {
        const wrapper = mount(Approval, {
            props: {
                voucher: {
                    code: 'TEST123',
                },
                compiled_claim_result: {
                    status: 'pending',
                    approval_metadata: {
                        manual_review: true,
                    },
                },
                message: null,
            },
        });

        expect(wrapper.find('[data-testid="approval-manual-review-notice"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="approval-otp-form"]').exists()).toBe(false);
        expect(wrapper.find('[data-testid="approval-polling-notice"]').exists()).toBe(false);
    });

    it('emits OTP submission payload when OTP form is submitted', async () => {
        const wrapper = mount(Approval, {
            props: {
                voucher: {
                    code: 'TEST123',
                },
                compiled_claim_result: {
                    status: 'pending',
                    approval_metadata: {
                        provider: 'paynamics',
                        reference_id: 'AUTH-123',
                        otp_required: true,
                    },
                },
                message: null,
            },
        });

        await wrapper
            .find('[data-testid="approval-otp-input"]')
            .setValue(' 123456 ');

        await wrapper
            .find('[data-testid="approval-otp-form"]')
            .trigger('submit');

        expect(wrapper.emitted('submit:otp')?.[0]).toEqual([
            {
                otp: '123456',
                referenceId: 'AUTH-123',
                provider: 'paynamics',
            },
        ]);

        expect(submitApprovalOtp).toHaveBeenCalledWith(
            expect.objectContaining({
                code: 'TEST123',
                otp: '123456',
                referenceId: 'AUTH-123',
                provider: 'paynamics',
            }),
            expect.objectContaining({
                onFinish: expect.any(Function),
            }),
        );
    });

    it('does not emit OTP submission when OTP is empty', async () => {
        const wrapper = mount(Approval, {
            props: {
                voucher: {
                    code: 'TEST123',
                },
                compiled_claim_result: {
                    status: 'pending',
                    approval_metadata: {
                        otp_required: true,
                    },
                },
                message: null,
            },
        });

        await wrapper
            .find('[data-testid="approval-otp-form"]')
            .trigger('submit');

        expect(wrapper.emitted('submit:otp')).toBeUndefined();
        expect(wrapper.find('[data-testid="approval-otp-error"]').text()).toBe('OTP is required.');
        expect(submitApprovalOtp).not.toHaveBeenCalled();
    });

    it('blocks empty OTP submission and shows inline error', async () => {
        const wrapper = mount(Approval, {
            props: {
                voucher: {
                    code: 'TEST123',
                },
                approval: {
                    required: true,
                    provider: 'paynamics',
                    authorization_type: 'otp',
                    reference_id: 'TEST123-09173011987',
                    otp_required: true,
                    message: 'Paynamics payout OTP is pending.',
                },
                compiled_claim_result: null,
                message: null,
            },
        });

        await wrapper.find('[data-testid="approval-otp-submit"]').trigger('submit');

        expect(wrapper.find('[data-testid="approval-otp-error"]').text()).toBe('OTP is required.');
        expect(wrapper.emitted('submit:otp')).toBeUndefined();
        expect(submitApprovalOtp).not.toHaveBeenCalled();
    });

    it('submits valid OTP payload through emit and adapter', async () => {
        const wrapper = mount(Approval, {
            props: {
                voucher: {
                    code: 'TEST123',
                },
                approval: {
                    required: true,
                    provider: 'paynamics',
                    authorization_type: 'otp',
                    reference_id: 'TEST123-09173011987',
                    otp_required: true,
                    message: 'Paynamics payout OTP is pending.',
                },
                compiled_claim_result: null,
                message: null,
            },
        });

        await wrapper.find('[data-testid="approval-otp-input"]').setValue('123456');
        await wrapper.find('[data-testid="approval-otp-form"]').trigger('submit');

        expect(wrapper.emitted('submit:otp')?.[0]?.[0]).toEqual({
            otp: '123456',
            referenceId: 'TEST123-09173011987',
            provider: 'paynamics',
        });

        expect(submitApprovalOtp).toHaveBeenCalledWith(
            expect.objectContaining({
                code: 'TEST123',
                otp: '123456',
                referenceId: 'TEST123-09173011987',
                provider: 'paynamics',
            }),
            expect.objectContaining({
                onFinish: expect.any(Function),
            }),
        );
    });

    it('renders server-side OTP validation error from Inertia errors', () => {
        pageProps.value.errors = {
            otp: 'Invalid OTP.',
        };

        const wrapper = mount(Approval, {
            props: {
                voucher: {
                    code: 'TEST123',
                },
                approval: {
                    required: true,
                    provider: 'paynamics',
                    authorization_type: 'otp',
                    reference_id: 'TEST123-09173011987',
                    otp_required: true,
                    message: 'Paynamics payout OTP is pending.',
                },
                compiled_claim_result: null,
                message: null,
            },
        });

        expect(wrapper.find('[data-testid="approval-otp-error"]').text()).toBe('Invalid OTP.');
    });

    it('disables OTP form while submission is processing', async () => {
        let finish: (() => void) | undefined;

        submitApprovalOtp.mockImplementationOnce((_payload, options) => {
            finish = options?.onFinish;
        });

        const wrapper = mount(Approval, {
            props: {
                voucher: { code: 'TEST123' },
                approval: {
                    required: true,
                    provider: 'paynamics',
                    authorization_type: 'otp',
                    reference_id: 'TEST123-09173011987',
                    otp_required: true,
                    message: 'Paynamics payout OTP is pending.',
                },
                compiled_claim_result: null,
                message: null,
            },
        });

        await wrapper.find('[data-testid="approval-otp-input"]').setValue('123456');
        await wrapper.find('[data-testid="approval-otp-form"]').trigger('submit');

        expect((wrapper.find('[data-testid="approval-otp-input"]').element as HTMLInputElement).disabled).toBe(true);
        expect((wrapper.find('[data-testid="approval-otp-submit"]').element as HTMLButtonElement).disabled).toBe(true);
        expect(wrapper.find('[data-testid="approval-otp-submit"]').text()).toBe('Verifying...');

        finish?.();
        await wrapper.vm.$nextTick();

        expect((wrapper.find('[data-testid="approval-otp-input"]').element as HTMLInputElement).disabled).toBe(false);
        expect((wrapper.find('[data-testid="approval-otp-submit"]').element as HTMLButtonElement).disabled).toBe(false);
        expect(wrapper.find('[data-testid="approval-otp-submit"]').text()).toBe('Verify OTP');
    });

    it('keeps OTP retry form visible after failed OTP result', () => {
        const wrapper = mount(Approval, {
            props: {
                voucher: {
                    code: 'TEST123',
                },
                compiled_claim_result: {
                    status: 'failed',
                    voucher_code: 'TEST123',
                    messages: ['Invalid OTP.'],
                    approval_metadata: {
                        provider: 'paynamics',
                        authorization_type: 'otp',
                        reference_id: 'AUTH-123',
                        otp_required: true,
                        expires_at: null,
                        polling_required: false,
                        manual_review: false,
                        message: 'Paynamics payout OTP is pending.',
                    },
                },
                message: null,
            },
        });

        expect(wrapper.find('[data-testid="approval-otp-form"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="approval-reference-id"]').text()).toContain('AUTH-123');
        expect(wrapper.find('[data-testid="approval-messages"]').text()).toContain('Invalid OTP.');
        expect(wrapper.find('[data-testid="approval-otp-submit"]').text()).toBe('Verify OTP');
    });

    it('dismisses server-side OTP error when user edits OTP', async () => {
        pageProps.value.errors = {
            otp: 'Invalid OTP.',
        };

        const wrapper = mount(Approval, {
            props: {
                voucher: {
                    code: 'TEST123',
                },
                approval: {
                    required: true,
                    provider: 'paynamics',
                    authorization_type: 'otp',
                    reference_id: 'TEST123-09173011987',
                    otp_required: true,
                    message: 'Paynamics payout OTP is pending.',
                },
                compiled_claim_result: null,
                message: null,
            },
        });

        expect(wrapper.find('[data-testid="approval-otp-error"]').text()).toBe('Invalid OTP.');

        await wrapper.find('[data-testid="approval-otp-input"]').setValue('123456');

        expect(wrapper.find('[data-testid="approval-otp-error"]').exists()).toBe(false);
    });
});
