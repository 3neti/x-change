export type CompiledFormSubmitState =
    | 'idle'
    | 'submitting'
    | 'submitted'
    | 'failed';

export type CompiledFormSubmitStateInput = {
    submitError?: string | null;
    submitted?: boolean | null;
    submitting?: boolean | null;
};

export function resolveCompiledFormSubmitState(
    input: CompiledFormSubmitStateInput,
): CompiledFormSubmitState {
    if (input.submitError) {
        return 'failed';
    }

    if (input.submitted) {
        return 'submitted';
    }

    return input.submitting ? 'submitting' : 'idle';
}
