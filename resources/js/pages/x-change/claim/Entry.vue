<script setup lang="ts">
import ClaimWidget from '@/components/x-change/ClaimWidget.vue';
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
import type {RawCompiledClaimFormPayload} from '@/components/x-change/compiledClaimFormSubmission';
import { useXChangeRoutes } from '@/composables/useXChangeRoutes';

defineOptions({ layout: null });

const props = defineProps<{
    initial_code?: string | null;
    claim_experience?: Record<string, unknown> | null;
    provisioning_requirement?: ProvisioningRequirement | null;
}>();

const compiledFormSubmitted = ref(false);
const compiledFormSubmitError = ref<string | null>(null);
const provisioningRequirement = computed(() =>
    normalizeProvisioningRequirement(props.provisioning_requirement),
);
const hasProvisioningRequirement = computed(() => provisioningRequirement.value !== null);
const routes = useXChangeRoutes();

function resumeClaimFlow(): void {
    const code = typeof props.initial_code === 'string' ? props.initial_code.trim() : '';
    const reference = provisioningRequirement.value?.onboarding?.reference;

    if (code === '') {
        return;
    }

    const params = new URLSearchParams({
        code,
    });

    if (typeof reference === 'string' && reference.trim() !== '') {
        params.set('onboarding_reference', reference);
    }

    router.visit(`${routes.claim.start()}?${params.toString()}`);
}

function submitCompiledForm(payload: RawCompiledClaimFormPayload): void {
    compiledFormSubmitted.value = false;
    compiledFormSubmitError.value = null;

    submitCompiledClaimForm(toCompiledClaimFormPayload(payload), {
        onSuccess: () => {
            compiledFormSubmitted.value = true;
        },
        onError: (errors) => {
            compiledFormSubmitError.value = compiledClaimFormErrorMessage(errors);
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
    <div class="flex min-h-svh flex-col items-center justify-center gap-6 bg-gradient-to-b from-primary/5 via-background to-background p-6 md:p-10">
        <div class="w-full max-w-md space-y-4">
            <ProvisioningSetup
                :requirement="provisioningRequirement"
                resume-label="Continue setup"
                @resume="resumeClaimFlow"
            />
            <ClaimWidget
                v-if="!hasProvisioningRequirement"
                :initial-code="initial_code"
                :claim-experience="claim_experience"
                :compiled-form-submitted="compiledFormSubmitted"
                :compiled-form-submit-error="compiledFormSubmitError"
                @update:compiled-form-values="resetCompiledFormSubmitState"
                @submit:compiled-form="submitCompiledForm"
            />
        </div>
    </div>
</template>
