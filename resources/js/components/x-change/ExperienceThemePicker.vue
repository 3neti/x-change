<script setup lang="ts">
import { ref } from 'vue';
import { useTheme } from '../../composables/useTheme';
import type { ExperienceThemeId } from '../../experience/themes';

withDefaults(
    defineProps<{
        align?: 'left' | 'right';
        compact?: boolean;
    }>(),
    {
        align: 'right',
        compact: false,
    },
);

const picker = ref<HTMLDetailsElement | null>(null);
const isOpen = ref(false);
const { availableThemes, currentTheme, setTheme } = useTheme();

function selectTheme(theme: ExperienceThemeId): void {
    setTheme(theme);

    if (picker.value !== null) {
        picker.value.open = false;
    }
}

function synchronizeOpenState(event: Event): void {
    isOpen.value = (event.currentTarget as HTMLDetailsElement).open;
}
</script>

<template>
    <details
        ref="picker"
        class="group relative z-50"
        data-testid="experience-theme-picker"
        @toggle="synchronizeOpenState"
    >
        <summary
            class="inline-flex min-h-11 min-w-11 cursor-pointer list-none items-center justify-center gap-2 rounded-full border border-border bg-card/90 px-3 text-card-foreground shadow-sm transition hover:bg-accent hover:text-accent-foreground focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring [&::-webkit-details-marker]:hidden"
            aria-label="Choose appearance theme"
            title="Choose appearance theme"
        >
            <span class="grid size-4 grid-cols-2 gap-0.5" aria-hidden="true">
                <span class="rounded-full bg-orange-500" />
                <span class="rounded-full bg-amber-700" />
                <span class="col-span-2 rounded-full bg-slate-700" />
            </span>
            <span v-if="!compact" class="text-xs font-semibold">Theme</span>
        </summary>

        <div
            v-if="isOpen"
            class="absolute mt-2 w-72 rounded-2xl border border-border bg-popover p-2 text-popover-foreground shadow-xl"
            :class="align === 'right' ? 'right-0' : 'left-0'"
            role="group"
            aria-label="Appearance themes"
        >
            <p
                class="px-3 pb-2 pt-1 text-xs font-semibold uppercase tracking-[0.16em] text-muted-foreground"
            >
                Your appearance
            </p>
            <button
                v-for="theme in availableThemes"
                :key="theme.id"
                type="button"
                class="flex min-h-14 w-full items-center gap-3 rounded-xl px-3 py-2 text-left transition hover:bg-accent focus-visible:outline-2 focus-visible:outline-offset-[-2px] focus-visible:outline-ring"
                :class="currentTheme === theme.id ? 'bg-accent' : ''"
                :aria-pressed="currentTheme === theme.id"
                :data-testid="`experience-theme-option-${theme.id}`"
                @click="selectTheme(theme.id)"
            >
                <span
                    class="relative size-9 shrink-0 overflow-hidden rounded-full border border-black/10 shadow-inner"
                    :style="{ backgroundColor: theme.preview.background }"
                    aria-hidden="true"
                >
                    <span
                        class="absolute inset-x-1 bottom-1 h-2 rounded-full"
                        :style="{ backgroundColor: theme.preview.accent }"
                    />
                    <span
                        class="absolute left-2 top-2 size-2 rounded-full"
                        :style="{ backgroundColor: theme.preview.foreground }"
                    />
                </span>
                <span class="min-w-0 flex-1">
                    <span class="block text-sm font-semibold">{{
                        theme.name
                    }}</span>
                    <span class="block text-xs text-muted-foreground">{{
                        theme.description
                    }}</span>
                </span>
                <span
                    v-if="currentTheme === theme.id"
                    class="shrink-0 text-base font-bold text-primary"
                    aria-hidden="true"
                    >✓</span
                >
            </button>
            <p
                class="px-3 pb-1 pt-2 text-[11px] leading-4 text-muted-foreground"
            >
                Saved on this device. It does not change your Pay Code
                instructions.
            </p>
        </div>
    </details>
</template>
