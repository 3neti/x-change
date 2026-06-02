<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { ref } from 'vue';
import ClaimWidget from '@/components/x-change/ClaimWidget.vue';
import {
    submitCompiledClaimForm,
    toCompiledClaimFormPayload

} from '@/components/x-change/compiledClaimFormSubmission';
import type {RawCompiledClaimFormPayload} from '@/components/x-change/compiledClaimFormSubmission';

defineOptions({ layout: null });

defineProps<{
    initial_code?: string | null;
    claim_experience?: Record<string, unknown> | null;
}>();

const compiledFormSubmitted = ref(false);
const compiledFormSubmitError = ref<string | null>(null);

function submitCompiledForm(payload: RawCompiledClaimFormPayload): void {
    compiledFormSubmitted.value = false;
    compiledFormSubmitError.value = null;

    submitCompiledClaimForm(toCompiledClaimFormPayload(payload), {
        onSuccess: () => {
            compiledFormSubmitted.value = true;
        },
        onError: () => {
            compiledFormSubmitError.value = 'Submission failed.';
        },
    });
}
</script>

<template>
    <Head title="Claim Pay Code" />
    <div class="flex min-h-svh flex-col items-center justify-center gap-6 bg-gradient-to-b from-primary/5 via-background to-background p-6 md:p-10">
        <div class="w-full max-w-sm">
            <ClaimWidget
                :initial-code="initial_code"
                :claim-experience="claim_experience"
                :compiled-form-submitted="compiledFormSubmitted"
                :compiled-form-submit-error="compiledFormSubmitError"
                @submit:compiled-form="submitCompiledForm"
            />
        </div>
    </div>
</template>
