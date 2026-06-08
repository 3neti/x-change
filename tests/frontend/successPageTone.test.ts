import { describe, expect, it } from 'vitest';
import { resolveSuccessPageTone } from '../../resources/js/components/x-change/successPageTone';

describe('success page tone', () => {
    it('uses success tone by default', () => {
        expect(resolveSuccessPageTone({})).toEqual({
            isPending: false,
            iconClass: 'text-green-500',
        });
    });

    it('uses pending tone when compiled claim result is pending', () => {
        expect(resolveSuccessPageTone({
            compiledClaimStatus: 'pending',
            claimOutcome: 'success',
            riderState: 'success',
        })).toEqual({
            isPending: true,
            iconClass: 'text-amber-500',
        });
    });

    it('uses pending tone when legacy claim outcome is pending', () => {
        expect(resolveSuccessPageTone({
            compiledClaimStatus: 'success',
            claimOutcome: 'pending',
            riderState: 'success',
        })).toMatchObject({
            isPending: true,
            iconClass: 'text-amber-500',
        });
    });

    it('uses pending tone when rider state is pending', () => {
        expect(resolveSuccessPageTone({
            compiledClaimStatus: 'success',
            claimOutcome: 'success',
            riderState: 'pending',
        })).toMatchObject({
            isPending: true,
            iconClass: 'text-amber-500',
        });
    });

    it('uses success tone when all known statuses are successful', () => {
        expect(resolveSuccessPageTone({
            compiledClaimStatus: 'completed',
            claimOutcome: 'success',
            riderState: 'completed',
        })).toEqual({
            isPending: false,
            iconClass: 'text-green-500',
        });
    });
});
