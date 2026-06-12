import { describe, expect, it } from 'vitest';
import { resolveApprovalMetadataViewModel } from '../../resources/js/components/x-change/approvalMetadataViewModel';

describe('approval metadata view model', () => {
    it('uses default approval metadata when missing', () => {
        expect(resolveApprovalMetadataViewModel(null)).toEqual({
            provider: null,
            authorizationType: null,
            referenceId: null,
            expiresAt: null,
            otpRequired: false,
            pollingRequired: false,
            manualReview: false,
            message: null,
            headline: 'Approval required',
        });
    });

    it('resolves OTP approval metadata', () => {
        expect(resolveApprovalMetadataViewModel({
            provider: 'paynamics',
            authorization_type: 'otp',
            reference_id: 'AUTH-123',
            expires_at: '2026-06-08T12:00:00+08:00',
            otp_required: true,
            polling_required: false,
            manual_review: false,
            message: 'Enter the OTP sent to your mobile number.',
        })).toEqual({
            provider: 'paynamics',
            authorizationType: 'otp',
            referenceId: 'AUTH-123',
            expiresAt: '2026-06-08T12:00:00+08:00',
            otpRequired: true,
            pollingRequired: false,
            manualReview: false,
            message: 'Enter the OTP sent to your mobile number.',
            headline: 'OTP verification required',
        });
    });

    it('prefers OTP headline over polling and manual review', () => {
        expect(resolveApprovalMetadataViewModel({
            otp_required: true,
            polling_required: true,
            manual_review: true,
        })).toMatchObject({
            headline: 'OTP verification required',
        });
    });

    it('uses polling headline when polling is required', () => {
        expect(resolveApprovalMetadataViewModel({
            polling_required: true,
        })).toMatchObject({
            pollingRequired: true,
            headline: 'Waiting for provider confirmation',
        });
    });

    it('uses manual review headline when manual review is required', () => {
        expect(resolveApprovalMetadataViewModel({
            manual_review: true,
        })).toMatchObject({
            manualReview: true,
            headline: 'Manual review required',
        });
    });

    it('coerces scalar display values to strings', () => {
        expect(resolveApprovalMetadataViewModel({
            provider: 123 as unknown as string,
            authorization_type: 456 as unknown as string,
            reference_id: 789 as unknown as string,
            expires_at: '',
            message: '',
        })).toMatchObject({
            provider: '123',
            authorizationType: '456',
            referenceId: '789',
            expiresAt: null,
            message: null,
        });
    });
});
