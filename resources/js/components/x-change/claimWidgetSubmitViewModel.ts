export type ClaimWidgetSubmitViewModelInput = {
    hasCompiledForm: boolean;
    compiledFormValid: boolean;
    processing: boolean;
    label?: string | null;
};

export type ClaimWidgetSubmitViewModel = {
    disabled: boolean;
    label: string;
};

export function resolveClaimWidgetSubmitViewModel(
    input: ClaimWidgetSubmitViewModelInput,
): ClaimWidgetSubmitViewModel {
    return {
        disabled: input.hasCompiledForm ? !input.compiledFormValid : false,
        label: input.processing
            ? 'Checking...'
            : input.label?.trim() || 'Start Claim',
    };
}
