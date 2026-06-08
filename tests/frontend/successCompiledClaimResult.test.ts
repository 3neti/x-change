import { describe, expect, it } from 'vitest';
import { resolveSuccessCompiledClaimResultViewModel } from '../../resources/js/components/x-change/successCompiledClaimResult';

describe('success compiled claim result view model', () => {
    it('is hidden when no compiled claim result exists', () => {
        expect(resolveSuccessCompiledClaimResultViewModel(null)).toEqual({
            visible: false,
            status: null,
            title: '',
            messages: [],
            amountText: null,
            isPending: false,
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
            amountText: null,
            isPending: false,
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
            amountText: null,
            isPending: true,
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

    it('formats disbursed amount with currency', () => {
        expect(resolveSuccessCompiledClaimResultViewModel({
            status: 'success',
            disbursed_amount: 1000,
            currency: 'PHP',
        })).toMatchObject({
            amountText: 'PHP 1,000.00',
        });
    });

    it('does not show amount when disbursed amount or currency is missing', () => {
        expect(resolveSuccessCompiledClaimResultViewModel({
            status: 'success',
            disbursed_amount: 1000,
            currency: null,
        })).toMatchObject({
            amountText: null,
        });
    });
});
