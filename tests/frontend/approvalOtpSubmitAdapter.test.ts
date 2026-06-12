import { beforeEach, describe, expect, it, vi } from 'vitest';
import {
    approvalOtpEndpoint,
    submitApprovalOtp,
    toApprovalOtpPostPayload,
} from '../../resources/js/components/x-change/approvalOtpSubmitAdapter';

const { post } = vi.hoisted(() => ({
    post: vi.fn(),
}));

vi.mock('@inertiajs/vue3', () => ({
    router: {
        post,
    },
}));

beforeEach(() => {
    post.mockClear();
});

describe('approval OTP submit adapter', () => {
    it('builds approval OTP endpoint from code', () => {
        expect(approvalOtpEndpoint('TEST123')).toBe('/x/claim/TEST123/approval/otp');
    });

    it('encodes approval OTP endpoint code', () => {
        expect(approvalOtpEndpoint('TEST 123')).toBe('/x/claim/TEST%20123/approval/otp');
    });

    it('maps frontend payload to POST payload', () => {
        expect(toApprovalOtpPostPayload({
            code: 'TEST123',
            otp: '123456',
            referenceId: 'AUTH-123',
            provider: 'paynamics',
        })).toEqual({
            otp: '123456',
            reference_id: 'AUTH-123',
            provider: 'paynamics',
        });
    });

    it('posts approval OTP payload to endpoint', () => {
        const onSuccess = vi.fn();
        const onError = vi.fn();
        const onFinish = vi.fn();

        submitApprovalOtp({
            code: 'TEST123',
            otp: '123456',
            referenceId: 'AUTH-123',
            provider: 'paynamics',
        }, {
            onSuccess,
            onError,
            onFinish,
        });

        expect(post).toHaveBeenCalledWith(
            '/x/claim/TEST123/approval/otp',
            {
                otp: '123456',
                reference_id: 'AUTH-123',
                provider: 'paynamics',
            },
            expect.objectContaining({
                preserveScroll: true,
                onSuccess,
                onError,
                onFinish,
            }),
        );
    });
});
