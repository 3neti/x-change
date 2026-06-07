export type ClaimWidgetFormFlowSectionViewModelInput = {
    hasCompiledFlow: boolean;
    usesLegacyFlow: boolean;
};

export type ClaimWidgetFormFlowSectionViewModel = {
    visible: boolean;
    compiledVisible: boolean;
    className: string;
};

export function resolveClaimWidgetFormFlowSectionViewModel(
    input: ClaimWidgetFormFlowSectionViewModelInput,
): ClaimWidgetFormFlowSectionViewModel {
    return {
        visible: input.hasCompiledFlow || input.usesLegacyFlow,
        compiledVisible: input.hasCompiledFlow,
        className: input.hasCompiledFlow ? 'space-y-4' : 'sr-only',
    };
}
