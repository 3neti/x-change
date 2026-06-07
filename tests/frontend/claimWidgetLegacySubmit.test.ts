import { describe, expect, it, vi } from 'vitest';
import {
    normalizeClaimCode,
    shouldPreserveClaimStartState,
    submitLegacyClaimStart,
    type LegacyClaimStartForm,
} from '../../resources/js/components/x-change/claimWidgetLegacySubmit';

describe('claim widget legacy claim start submit helper', () => {
    it('normalizes claim code', () => {
        expect(normalizeClaimCode(' test123 ')).toBe('TEST123');
        expect(normalizeClaimCode('')).toBe('');
        expect(normalizeClaimCode(null)).toBe('');
        expect(normalizeClaimCode(undefined)).toBe('');
    });

    it('preserves state when claim start response has no errors', () => {
        expect(shouldPreserveClaimStartState({
            props: {
                errors: {},
            },
        })).toBe(true);

        expect(shouldPreserveClaimStartState({
            props: {},
        })).toBe(true);
    });

    it('does not preserve state when claim start response has errors', () => {
        expect(shouldPreserveClaimStartState({
            props: {
                errors: {
                    code: 'Invalid Pay Code.',
                },
            },
        })).toBe(false);
    });

    it('submits normalized entered code to legacy claim start route', () => {
        const form: LegacyClaimStartForm = {
            code: '',
            get: vi.fn(),
        };

        submitLegacyClaimStart(form, ' test123 ');

        expect(form.code).toBe('TEST123');

        expect(form.get).toHaveBeenCalledWith('/x/claim', {
            preserveState: shouldPreserveClaimStartState,
            preserveScroll: true,
        });
    });

    it('falls back to existing form code when entered code is empty', () => {
        const form: LegacyClaimStartForm = {
            code: ' existing123 ',
            get: vi.fn(),
        };

        submitLegacyClaimStart(form, '');

        expect(form.code).toBe('EXISTING123');

        expect(form.get).toHaveBeenCalledWith('/x/claim', {
            preserveState: shouldPreserveClaimStartState,
            preserveScroll: true,
        });
    });
});
