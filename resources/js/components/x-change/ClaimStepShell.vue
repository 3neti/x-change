<script setup lang="ts">
import { computed } from 'vue';

type ClaimStepTone = 'neutral' | 'success' | 'warning' | 'danger';
type ClaimStepWidth = 'sm' | 'md' | 'lg';

const props = withDefaults(
    defineProps<{
        tone?: ClaimStepTone;
        width?: ClaimStepWidth;
        centered?: boolean;
    }>(),
    {
        tone: 'neutral',
        width: 'md',
        centered: true,
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
        class="min-h-svh bg-gradient-to-b px-5 py-8 text-foreground"
        :class="toneClass"
    >
        <div
            class="mx-auto flex min-h-[calc(100svh-4rem)] w-full flex-col"
            :class="[widthClass, centered ? 'justify-center' : 'justify-start']"
        >
            <section
                data-testid="claim-step-panel"
                class="w-full rounded-lg border border-border/60 bg-card/85 p-6 shadow-sm backdrop-blur-sm"
            >
                <slot />
            </section>
        </div>
    </main>
</template>
