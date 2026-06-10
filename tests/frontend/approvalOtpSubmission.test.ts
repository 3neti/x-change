import { describe, expect, it } from 'vitest';
import { resolveApprovalOtpSubmission } from '../../resources/js/components/x-change/approvalOtpSubmission';

describe('approval OTP submission', () => {
    it('blocks empty OTP submissions', () => {
        expect(resolveApprovalOtpSubmission({
            otp: '   ',
            referenceId: 'AUTH-123',
            provider: 'payanamics',
        })).toEqual({
            intent: 'blocked',
            payload: null,
            error: 'OTP is required.',
        });
    });

    it('builds OTP submission payload', () => {
        expect(resolveApprovalOtpSubmission({
            otp: ' 123456 ',
            referenceId: 'AUTH-123',
            provider: 'payanamics',
        })).toEqual({
            intent: 'submit',
            payload: {
                otp: '123456',
                referenceId: 'AUTH-123',
                provider: 'payanamics',
            },
            error: null,
        });
    });

    it('defaults optional provider metadata to null', () => {
        expect(resolveApprovalOtpSubmission({
            otp: '123456',
        })).toEqual({
            intent: 'submit',
            payload: {
                otp: '123456',
                referenceId: null,
                provider: null,
            },
            error: null,
        });
    });
});
