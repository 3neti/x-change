<script setup lang="ts">
import ClaimWidget from '@/components/x-change/ClaimWidget.vue';
import ClaimStepShell from '@/components/x-change/ClaimStepShell.vue';
import ProvisioningSetup from '../../../components/x-change/ProvisioningSetup.vue';
import {
    normalizeProvisioningRequirement,
    type ProvisioningRequirement,
} from '../../../components/x-change/provisioningRequirement';
import { Head, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import {
    compiledClaimFormErrorMessage,
    submitCompiledClaimForm,
    toCompiledClaimFormPayload,
} from '@/components/x-change/compiledClaimFormSubmission';
import type { RawCompiledClaimFormPayload } from '@/components/x-change/compiledClaimFormSubmission';
import { store as startClaimFlow } from '@/routes/x-change/claim/flows';

defineOptions({ layout: null });

const props = defineProps<{
    initial_code?: string | null;
    claim_experience?: Record<string, unknown> | null;
    provisioning_requirement?: ProvisioningRequirement | null;
    claim_surface?: Record<string, unknown> | null;
}>();

const compiledFormSubmitted = ref(false);
const compiledFormSubmitError = ref<string | null>(null);
const provisioningRequirement = computed(() =>
    normalizeProvisioningRequirement(props.provisioning_requirement),
);
const hasProvisioningRequirement = computed(
    () => provisioningRequirement.value !== null,
);
function resumeClaimFlow(): void {
    const code =
        typeof props.initial_code === 'string' ? props.initial_code.trim() : '';
    const reference = provisioningRequirement.value?.onboarding?.reference;

    if (code === '') {
        return;
    }

    router.post(startClaimFlow.url(code), {
        onboarding_reference:
            typeof reference === 'string' && reference.trim() !== ''
                ? reference.trim()
                : null,
    });
}

function submitCompiledForm(payload: RawCompiledClaimFormPayload): void {
    compiledFormSubmitted.value = false;
    compiledFormSubmitError.value = null;

    submitCompiledClaimForm(toCompiledClaimFormPayload(payload), {
        onSuccess: () => {
            compiledFormSubmitted.value = true;
        },
        onError: (errors) => {
            compiledFormSubmitError.value =
                compiledClaimFormErrorMessage(errors);
        },
    });
}

function resetCompiledFormSubmitState(): void {
    compiledFormSubmitted.value = false;
    compiledFormSubmitError.value = null;
}
</script>

<template>
    <Head title="Claim Pay Code" />
    <ClaimStepShell
        brand-placement="center"
        brand-size="display"
        brand-variant="mark"
    >
        <div class="space-y-4">
            <ProvisioningSetup
                :requirement="provisioningRequirement"
                resume-label="Continue setup"
                @resume="resumeClaimFlow"
            />
            <ClaimWidget
                v-if="!hasProvisioningRequirement"
                :initial-code="initial_code"
                :claim-experience="claim_experience"
                :claim-surface="claim_surface"
                :compiled-form-submitted="compiledFormSubmitted"
                :compiled-form-submit-error="compiledFormSubmitError"
                @update:compiled-form-values="resetCompiledFormSubmitState"
                @submit:compiled-form="submitCompiledForm"
            />
        </div>
    </ClaimStepShell>
</template>
