import { describe, expect, it } from 'vitest';
import { resolveClaimWidgetSubmitViewModel } from '../../resources/js/components/x-change/claimWidgetSubmitViewModel';

describe('claim widget submit view model', () => {
    it('enables legacy claim start submit by default', () => {
        expect(resolveClaimWidgetSubmitViewModel({
            hasCompiledForm: false,
            compiledFormValid: false,
            processing: false,
        })).toEqual({
            disabled: false,
            label: 'Start Claim',
        });
    });

    it('disables submit when compiled form exists and is invalid', () => {
        expect(resolveClaimWidgetSubmitViewModel({
            hasCompiledForm: true,
            compiledFormValid: false,
            processing: false,
        })).toEqual({
            disabled: true,
            label: 'Start Claim',
        });
    });

    it('enables submit when compiled form exists and is valid', () => {
        expect(resolveClaimWidgetSubmitViewModel({
            hasCompiledForm: true,
            compiledFormValid: true,
            processing: false,
        })).toEqual({
            disabled: false,
            label: 'Start Claim',
        });
    });

    it('uses checking label while processing', () => {
        expect(resolveClaimWidgetSubmitViewModel({
            hasCompiledForm: false,
            compiledFormValid: false,
            processing: true,
        })).toEqual({
            disabled: false,
            label: 'Checking...',
        });
    });
});
