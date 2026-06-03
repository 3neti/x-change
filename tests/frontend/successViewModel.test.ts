import { describe, expect, it } from 'vitest';
import { resolveSuccessViewModel } from '../../resources/js/components/x-change/successViewModel';

describe('success view model', () => {
    it('marks success visual stages when present', () => {
        expect(resolveSuccessViewModel({
            successVisualStageCount: 1,
            redirectRuntimeStageCount: 0,
            hasRiderMessage: false,
            hasRedirect: false,
        }).hasSuccessVisualStages).toBe(true);
    });

    it('marks redirect runtime stages when present', () => {
        expect(resolveSuccessViewModel({
            successVisualStageCount: 0,
            redirectRuntimeStageCount: 1,
            hasRiderMessage: false,
            hasRedirect: false,
        }).hasRedirectRuntimeStages).toBe(true);
    });

    it('renders fallback only when no success stages, rider message, or redirect exist', () => {
        expect(resolveSuccessViewModel({
            successVisualStageCount: 0,
            redirectRuntimeStageCount: 0,
            hasRiderMessage: false,
            hasRedirect: false,
        }).shouldRenderFallback).toBe(true);
    });

    it('does not render fallback when redirect exists', () => {
        expect(resolveSuccessViewModel({
            successVisualStageCount: 0,
            redirectRuntimeStageCount: 0,
            hasRiderMessage: false,
            hasRedirect: true,
        }).shouldRenderFallback).toBe(false);
    });

    it('shows voucher code badge only when no success stages or rider message exist', () => {
        expect(resolveSuccessViewModel({
            successVisualStageCount: 0,
            redirectRuntimeStageCount: 0,
            hasRiderMessage: false,
            hasRedirect: true,
        }).shouldShowVoucherCodeBadge).toBe(true);

        expect(resolveSuccessViewModel({
            successVisualStageCount: 1,
            redirectRuntimeStageCount: 0,
            hasRiderMessage: false,
            hasRedirect: false,
        }).shouldShowVoucherCodeBadge).toBe(false);
    });
});
