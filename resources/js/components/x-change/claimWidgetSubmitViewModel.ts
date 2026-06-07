export type ClaimWidgetSubmitViewModelInput = {
    hasCompiledForm: boolean;
    compiledFormValid: boolean;
    processing: boolean;
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
        label: input.processing ? 'Checking...' : 'Start Claim',
    };
}
