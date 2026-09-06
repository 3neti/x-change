<script setup lang="ts">
import { computed } from 'vue';
import PayCodeLogo from '@/components/x-change/PayCodeLogo.vue';
import ExperienceThemePicker from '@/components/x-change/ExperienceThemePicker.vue';

type ClaimStepTone = 'neutral' | 'success' | 'warning' | 'danger';
type ClaimStepWidth = 'sm' | 'md' | 'lg';
type ClaimBrandPlacement = 'top_left' | 'center';
type ClaimBrandSize = 'micro' | 'header' | 'brand' | 'display';
type ClaimBrandVariant = 'mark' | 'logo' | 'lockup';

const props = withDefaults(
    defineProps<{
        tone?: ClaimStepTone;
        width?: ClaimStepWidth;
        centered?: boolean;
        showBrand?: boolean;
        showThemePicker?: boolean;
        brandPlacement?: ClaimBrandPlacement;
        brandSize?: ClaimBrandSize;
        brandVariant?: ClaimBrandVariant;
    }>(),
    {
        tone: 'neutral',
        width: 'md',
        centered: true,
        showBrand: true,
        showThemePicker: true,
        brandPlacement: 'top_left',
        brandSize: 'header',
        brandVariant: 'mark',
    },
);

const toneClass = computed(() => {
    if (props.tone === 'success') {
        return 'from-emerald-500/10 via-background to-background';
    }

    if (props.tone === 'warning') {
        return 'from-amber-500/10 via-background to-background';
    }

    if (props.tone === 'danger') {
        return 'from-destructive/10 via-background to-background';
    }

    return 'from-primary/5 via-background to-background';
});

const brandPlacementClass = computed(() =>
    props.brandPlacement === 'center'
        ? 'mb-5 justify-center text-center'
        : 'mb-5 justify-start text-left',
);

const widthClass = computed(() => {
    if (props.width === 'sm') {
        return 'max-w-sm';
    }

    if (props.width === 'lg') {
        return 'max-w-lg';
    }

    return 'max-w-md';
});
</script>

<template>
    <main
        data-testid="claim-step-shell"
        class="x-experience-surface relative min-h-svh bg-gradient-to-b px-5 py-8 text-foreground"
        :class="toneClass"
    >
        <ExperienceThemePicker
            v-if="showThemePicker"
            compact
            class="absolute right-4 top-4 sm:right-6 sm:top-6"
            data-testid="claim-theme-picker"
        />
        <div
            class="mx-auto flex min-h-[calc(100svh-4rem)] w-full flex-col"
            :class="[widthClass, centered ? 'justify-center' : 'justify-start']"
        >
            <section
                data-testid="claim-step-panel"
                class="w-full rounded-lg border border-border/60 bg-card/85 p-6 shadow-sm"
            >
                <div
                    v-if="showBrand"
                    data-testid="claim-brand-header"
                    class="flex"
                    :class="brandPlacementClass"
                >
                    <PayCodeLogo
                        :variant="brandVariant"
                        :size="brandSize"
                        data-testid="claim-brand-logo"
                    />
                </div>

                <slot />
            </section>
        </div>
    </main>
</template>
