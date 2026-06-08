import { describe, expect, it } from 'vitest';
import {
    DEFAULT_APPROVAL_MESSAGE,
    resolveApprovalPageViewModel,
} from '../../resources/js/components/x-change/approvalPageViewModel';

describe('approval page view model', () => {
    it('uses default approval copy without compiled claim result', () => {
        expect(resolveApprovalPageViewModel({
            compiledClaimResult: null,
            message: null,
        })).toEqual({
            title: 'Claim submitted for processing',
            status: 'pending',
            message: DEFAULT_APPROVAL_MESSAGE,
            amountText: null,
            messages: [],
        });
    });

    it('uses provided approval message', () => {
        expect(resolveApprovalPageViewModel({
            compiledClaimResult: null,
            message: 'Please wait while your claim is reviewed.',
        })).toMatchObject({
            message: 'Please wait while your claim is reviewed.',
        });
    });

    it('resolves pending compiled claim result details', () => {
        expect(resolveApprovalPageViewModel({
            compiledClaimResult: {
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
            message: null,
        })).toEqual({
            title: 'Claim submitted for processing',
            status: 'pending',
            message: DEFAULT_APPROVAL_MESSAGE,
            amountText: 'PHP 1,000.00',
            messages: ['Approval required.'],
        });
    });

    it('falls back to pending status when compiled result has no status', () => {
        expect(resolveApprovalPageViewModel({
            compiledClaimResult: {
                messages: ['Waiting for provider confirmation.'],
            },
            message: null,
        })).toMatchObject({
            status: 'pending',
            messages: ['Waiting for provider confirmation.'],
        });
    });
});
