<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import CockpitClaimLiveScreen from '../../../cockpit/components/CockpitClaimLiveScreen.vue';
import type { CockpitClaimExperiencePreviewStep } from '../../../cockpit/types';

defineOptions({ layout: null });

defineProps<{
    step: CockpitClaimExperiencePreviewStep;
    viewport: {
        profile: string;
        width: number;
        height: number;
    };
}>();
</script>

<template>
    <Head :title="`${step.title} · Claim Preview`" />
    <main
        class="h-svh w-screen overflow-hidden bg-slate-950"
        data-testid="cockpit-claim-preview-step-page"
        :data-viewport-profile="viewport.profile"
    >
        <img
            v-if="step.frame"
            :src="step.frame.url"
            :alt="`${step.title} claim preview`"
            class="size-full object-contain"
            data-testid="cockpit-claim-experience-frame"
        />
        <CockpitClaimLiveScreen
            v-else-if="step.screen"
            :screen="step.screen"
            presentation="viewport"
        />
    </main>
</template>
