import { computed, ref, type ComputedRef } from 'vue';
import { resolveCompiledFormFlowPhase } from '@/components/x-change/compiledFormFlow';
import { buildCompiledFormPayload, type CompiledFormPayload } from '@/components/x-change/compiledFormPayload';
import { resolveCompiledFormSubmitEvent } from '@/components/x-change/compiledFormSubmit';
import { resolveCompiledFormViewModel } from '@/components/x-change/compiledFormViewModel';
import { resolveFormFlowBoundary } from '@/components/x-change/formFlowBoundary';

export type UseCompiledClaimFormInput = {
    initialCode?: string | null;
    claimExperience: ComputedRef<Record<string, unknown> | null | undefined>;
    submitted: ComputedRef<boolean | null | undefined>;
    submitError: ComputedRef<string | null | undefined>;
    emitSubmit: (payload: CompiledFormPayload) => void;
    emitUpdateValues: (values: Record<string, unknown>) => void;
};

export function useCompiledClaimForm(input: UseCompiledClaimFormInput) {
    const values = ref<Record<string, unknown>>({});
    const submitting = ref(false);

    const compiledFormFlowPhase = computed<Record<string, any> | null>(() =>
        resolveCompiledFormFlowPhase(input.claimExperience.value)
    );

    const boundary = computed(() =>
        resolveFormFlowBoundary(compiledFormFlowPhase.value)
    );

    const viewModel = computed(() =>
        resolveCompiledFormViewModel({
            boundary: boundary.value,
            values: values.value,
            submitError: input.submitError.value,
            submitted: input.submitted.value,
            submitting: submitting.value,
        })
    );

    const usesLegacyFlow = computed(() =>
        viewModel.value.usesLegacyFormFlow
    );

    const normalizedFlow = computed(() =>
        viewModel.value.normalizedCompiledFormFlow
    );

    const isValid = computed(() =>
        viewModel.value.isValid
    );

    const payload = computed(() =>
        buildCompiledFormPayload(
            input.initialCode,
            values.value,
        )
    );

    function updateValues(nextValues: Record<string, unknown>): void {
        values.value = nextValues;
        input.emitUpdateValues(nextValues);
    }

    function submit(): void {
        const submitEvent = resolveCompiledFormSubmitEvent(
            normalizedFlow.value !== null,
            isValid.value,
            payload.value,
        );

        if (submitEvent.intent === 'blocked') {
            return;
        }

        submitting.value = submitEvent.submitting;

        input.emitSubmit(submitEvent.payload);
    }

    return {
        values,
        submitting,
        boundary,
        viewModel,
        usesLegacyFlow,
        normalizedFlow,
        isValid,
        payload,
        updateValues,
        submit,
    };
}

