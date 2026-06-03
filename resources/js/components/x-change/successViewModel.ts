export type SuccessViewModelInput = {
    successVisualStageCount: number;
    redirectRuntimeStageCount: number;
    hasRiderMessage: boolean;
    hasRedirect: boolean;
};

export type SuccessViewModel = {
    hasSuccessVisualStages: boolean;
    hasRedirectRuntimeStages: boolean;
    shouldRenderFallback: boolean;
    shouldShowVoucherCodeBadge: boolean;
};

export function resolveSuccessViewModel(input: SuccessViewModelInput): SuccessViewModel {
    const hasSuccessVisualStages = input.successVisualStageCount > 0;
    const hasRedirectRuntimeStages = input.redirectRuntimeStageCount > 0;

    return {
        hasSuccessVisualStages,
        hasRedirectRuntimeStages,
        shouldRenderFallback: !hasSuccessVisualStages
            && !input.hasRiderMessage
            && !input.hasRedirect,
        shouldShowVoucherCodeBadge: !hasSuccessVisualStages
            && !input.hasRiderMessage,
    };
}
