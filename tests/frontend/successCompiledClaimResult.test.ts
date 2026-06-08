import { describe, expect, it } from 'vitest';
import { resolveSuccessCompiledClaimResultViewModel } from '../../resources/js/components/x-change/successCompiledClaimResult';

describe('success compiled claim result view model', () => {
    it('is hidden when no compiled claim result exists', () => {
        expect(resolveSuccessCompiledClaimResultViewModel(null)).toEqual({
            visible: false,
            status: null,
            title: '',
            messages: [],
        });
    });

    it('renders completed claim result', () => {
        expect(resolveSuccessCompiledClaimResultViewModel({
            status: 'success',
            messages: ['Claim successful.'],
        })).toEqual({
            visible: true,
            status: 'success',
            title: 'Claim completed',
            messages: ['Claim successful.'],
        });
    });

    it('renders pending claim result', () => {
        expect(resolveSuccessCompiledClaimResultViewModel({
            status: 'pending',
            messages: ['Approval required.'],
        })).toEqual({
            visible: true,
            status: 'pending',
            title: 'Claim submitted for processing',
            messages: ['Approval required.'],
        });
    });

    it('defaults messages to an empty array', () => {
        expect(resolveSuccessCompiledClaimResultViewModel({
            status: 'success',
            messages: null,
        })).toMatchObject({
            visible: true,
            messages: [],
        });
    });
});
