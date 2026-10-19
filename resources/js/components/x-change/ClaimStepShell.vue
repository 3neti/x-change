<script setup lang="ts">
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import FormFlowPresentation from '@/pages/form-flow/core/components/FormFlowPresentation.vue';
import FormFlowVersionStrip from '@/pages/form-flow/core/components/FormFlowVersionStrip.vue';
import {
    presentationForScreen,
    type FormFlowPresentation as PresentationPolicy,
} from '@/pages/form-flow/core/components/formFlowPresentation';
import PayCodeLogo from '@/components/x-change/PayCodeLogo.vue';
import ExperienceThemePicker from '@/components/x-change/ExperienceThemePicker.vue';

type ClaimStepTone = 'neutral' | 'success' | 'warning' | 'danger';
type ClaimStepWidth = 'sm' | 'md' | 'lg';
type ClaimBrandPlacement = 'top_left' | 'center';
type ClaimBrandSize = 'micro' | 'header' | 'brand' | 'display';
type ClaimBrandVariant = 'mark' | 'logo' | 'lockup';

const props = withDefaults(
    defineProps<{
        profile?: PresentationPolicy | null;
        screen?: string;
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

const page = usePage();
const screenKey = computed(
    () =>
        props.screen ??
        (
            {
                Entry: 'entry',
                Confirm: 'confirmation',
                Success: 'success',
                Approval: 'approval',
                AuthRequired: 'auth_required',
                Error: 'error',
                Payment: 'payment',
                PaymentHandoff: 'payment_handoff',
            } as Record<string, string>
        )[(page.component ?? '').split('/').pop() ?? ''],
);
const profile = computed(() =>
    presentationForScreen(
        props.profile ?? (page.props?.claim_ui as PresentationPolicy | null),
        screenKey.value,
    ),
);
const panelStyle = computed(() => ({
    maxWidth: 'var(--flow-content-width)',
}));
const brandStyle = computed(() =>
    profile.value
        ? {
              height: `${profile.value.brand.logo_height}px`,
              maxWidth: `${profile.value.brand.logo_max_width}px`,
          }
        : undefined,
);
const hasFixedClaimPalette = computed(
    () =>
        typeof document !== 'undefined' &&
        Boolean(document.documentElement.dataset.xChangeClaimAppearance),
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
    (profile.value?.brand.placement ?? props.brandPlacement) === 'center'
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
    <FormFlowPresentation
        :presentation="profile"
        :screen="screenKey"
        :show-header="false"
    >
        <main
            data-testid="claim-step-shell"
            class="x-experience-surface x-claim-canvas relative min-h-svh bg-gradient-to-b px-5 py-8 text-foreground"
            :class="toneClass"
            :data-claim-density="profile?.layout.density"
        >
            <ExperienceThemePicker
                v-if="showThemePicker && !hasFixedClaimPalette"
                compact
                class="absolute right-4 top-4 sm:right-6 sm:top-6"
                data-testid="claim-theme-picker"
            />
            <div
                class="mx-auto flex min-h-[calc(100svh-4rem)] w-full flex-col"
                :style="panelStyle"
                data-flow-content
                :class="[
                    widthClass,
                    centered ? 'justify-center' : 'justify-start',
                ]"
            >
                <section
                    data-testid="claim-step-panel"
                    data-flow-panel
                    data-flow-stack
                    class="w-full rounded-lg border border-border/60 bg-card/85 p-6 shadow-sm"
                >
                    <div
                        v-if="showBrand"
                        data-testid="claim-brand-header"
                        class="flex"
                        :class="brandPlacementClass"
                    >
                        <img
                            v-if="profile?.brand.logo"
                            :src="profile.brand.logo"
                            :alt="profile.brand.name ?? 'Logo'"
                            :style="brandStyle"
                            class="w-auto object-contain"
                            data-testid="claim-configured-brand"
                        />
                        <span v-else-if="profile?.brand.name">{{
                            profile.brand.name
                        }}</span>
                        <PayCodeLogo
                            v-else
                            :variant="brandVariant"
                            :size="brandSize"
                            data-testid="claim-brand-logo"
                        />
                    </div>

                    <FormFlowVersionStrip
                        v-if="profile?.diagnostics.show_versions"
                        :show="true"
                        :package-versions="
                            page.props?.claim_ui_versions as Record<
                                string,
                                string
                            >
                        "
                        context="claim"
                    />
                    <slot />
                </section>
            </div>
        </main>
    </FormFlowPresentation>
</template>
<style scoped>
[data-testid='claim-step-shell'] {
    padding-inline: min(1.25rem, 5vw);
}
[data-testid='claim-step-panel'] {
    padding: min(1.5rem, 6vw, 24px);
}
[data-claim-density='compact'] {
    padding-block: 1.5rem;
}
[data-claim-density='immersive'] {
    padding: 0.75rem;
}
[data-claim-density='immersive'] [data-testid='claim-step-panel'] {
    padding: 1rem;
}
</style>
