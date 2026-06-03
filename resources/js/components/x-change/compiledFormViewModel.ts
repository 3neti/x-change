import { normalizeCompiledFormFlowPhase } from '@/components/x-change/compiledFormFlow';
import {
    isCompiledFormValid,
    missingRequiredCompiledFormFields,
} from '@/components/x-change/compiledFormValidation';
import { resolveCompiledFormSubmitState } from '@/components/x-change/compiledFormSubmitState';
import type { FormFlowBoundary } from '@/components/x-change/formFlowBoundary';

export type CompiledFormViewModelInput = {
    boundary: FormFlowBoundary;
    values: Record<string, unknown>;
    submitError?: string | null;
    submitted?: boolean | null;
    submitting?: boolean | null;
};

export function resolveCompiledFormViewModel(input: CompiledFormViewModelInput) {
    const usesCompiledFormFlow = input.boundary.mode === 'compiled';
    const usesLegacyFormFlow = input.boundary.mode === 'legacy';

    const normalizedCompiledFormFlow = usesCompiledFormFlow
        ? normalizeCompiledFormFlowPhase(input.boundary.phase)
        : null;

    const missingRequiredFields = missingRequiredCompiledFormFields(
        normalizedCompiledFormFlow?.fields,
        input.values,
    );

    const valid = isCompiledFormValid(
        normalizedCompiledFormFlow?.fields,
        input.values,
    );

    const submitState = resolveCompiledFormSubmitState({
        submitError: input.submitError,
        submitted: input.submitted,
        submitting: input.submitting,
    });

    return {
        usesCompiledFormFlow,
        usesLegacyFormFlow,
        normalizedCompiledFormFlow,
        missingRequiredFields,
        isValid: valid,
        submitState,
    };
}
