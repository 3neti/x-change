import { describe, expect, it } from 'vitest';
import { resolveClaimWidgetFormFlowSectionViewModel } from '../../resources/js/components/x-change/claimWidgetFormFlowSectionViewModel';

describe('claim widget form flow section view model', () => {
    it('shows compiled form flow section visibly when compiled flow exists', () => {
        expect(resolveClaimWidgetFormFlowSectionViewModel({
            hasCompiledFlow: true,
            usesLegacyFlow: false,
        })).toEqual({
            visible: true,
            compiledVisible: true,
            className: 'space-y-4',
        });
    });

    it('keeps legacy form flow boundary hidden when only legacy flow is used', () => {
        expect(resolveClaimWidgetFormFlowSectionViewModel({
            hasCompiledFlow: false,
            usesLegacyFlow: true,
        })).toEqual({
            visible: true,
            compiledVisible: false,
            className: 'sr-only',
        });
    });

    it('hides form flow section when no form flow mode is active', () => {
        expect(resolveClaimWidgetFormFlowSectionViewModel({
            hasCompiledFlow: false,
            usesLegacyFlow: false,
        })).toEqual({
            visible: false,
            compiledVisible: false,
            className: 'sr-only',
        });
    });

    it('prefers compiled visibility when both compiled and legacy flags are true', () => {
        expect(resolveClaimWidgetFormFlowSectionViewModel({
            hasCompiledFlow: true,
            usesLegacyFlow: true,
        })).toEqual({
            visible: true,
            compiledVisible: true,
            className: 'space-y-4',
        });
    });
});
