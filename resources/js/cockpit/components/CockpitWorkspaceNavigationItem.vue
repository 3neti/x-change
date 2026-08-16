<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import type { InertiaLinkProps } from '@inertiajs/vue3';
import { CircleHelp, X } from 'lucide-vue-next';
import type { Component } from 'vue';
import { onBeforeUnmount, ref } from 'vue';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import type { CockpitWorkspaceGuide } from '../workspaceNavigationGuides';

const HOLD_DURATION_MILLISECONDS = 650;
const HOLD_MOVEMENT_TOLERANCE = 8;

const props = defineProps<{
    title: string;
    description: string;
    href: InertiaLinkProps['href'];
    icon: Component;
    active: boolean;
    guide: CockpitWorkspaceGuide;
    guideHref: InertiaLinkProps['href'];
    step?: string;
    branch?: boolean;
    dividerBefore?: boolean;
}>();

const guideOpen = ref(false);
const holding = ref(false);
const suppressNextClick = ref(false);
const pointerStart = ref<{ x: number; y: number } | null>(null);
let holdTimer: ReturnType<typeof setTimeout> | null = null;

function supportsHold(event: PointerEvent): boolean {
    return (
        event.button === 0 &&
        event.pointerType !== 'touch' &&
        window.matchMedia?.('(pointer: fine)').matches === true
    );
}

function startHold(event: PointerEvent): void {
    if (!supportsHold(event)) {
        return;
    }

    clearHold();
    pointerStart.value = { x: event.clientX, y: event.clientY };
    holding.value = true;
    holdTimer = setTimeout(() => {
        holding.value = false;
        suppressNextClick.value = true;
        guideOpen.value = true;
    }, HOLD_DURATION_MILLISECONDS);
}

function trackHold(event: PointerEvent): void {
    if (!pointerStart.value) {
        return;
    }

    if (
        Math.abs(event.clientX - pointerStart.value.x) >
            HOLD_MOVEMENT_TOLERANCE ||
        Math.abs(event.clientY - pointerStart.value.y) > HOLD_MOVEMENT_TOLERANCE
    ) {
        clearHold();
    }
}

function clearHold(): void {
    if (holdTimer !== null) {
        clearTimeout(holdTimer);
        holdTimer = null;
    }

    pointerStart.value = null;
    holding.value = false;
}

function handleClick(event: MouseEvent): void {
    if (!suppressNextClick.value) {
        return;
    }

    event.preventDefault();
    event.stopPropagation();
    suppressNextClick.value = false;
}

function handleKeyboardGuide(event: KeyboardEvent): void {
    if (event.shiftKey && event.key === 'F1') {
        event.preventDefault();
        clearHold();
        guideOpen.value = true;
    }
}

function openGuide(): void {
    clearHold();
    guideOpen.value = true;
}

onBeforeUnmount(clearHold);
</script>

<template>
    <SidebarMenuItem
        class="relative"
        :class="dividerBefore ? 'mt-2 border-t border-sidebar-border pt-2' : ''"
        :data-testid="
            dividerBefore ? 'workspace-navigation-divider' : undefined
        "
    >
        <SidebarMenuButton
            as-child
            size="lg"
            :is-active="active"
            :tooltip="`Open ${title} · hold for page guide`"
        >
            <Link
                :href="href"
                prefetch
                class="pr-11"
                :aria-describedby="`${title.toLowerCase().replaceAll(' ', '-')}-guide-hint`"
                @pointerdown="startHold"
                @pointermove="trackHold"
                @pointerup="clearHold"
                @pointercancel="clearHold"
                @pointerleave="clearHold"
                @click="handleClick"
                @keydown="handleKeyboardGuide"
            >
                <span
                    v-if="step || branch"
                    class="relative flex h-6 w-6 shrink-0 items-center justify-center overflow-hidden rounded-full border text-[0.7rem] font-bold"
                    :class="
                        branch
                            ? 'border-orange-200 bg-orange-50 text-orange-700 dark:border-orange-900 dark:bg-orange-950/40 dark:text-orange-300'
                            : 'border-sidebar-border bg-sidebar-accent text-sidebar-accent-foreground'
                    "
                    aria-hidden="true"
                >
                    {{ branch ? '↳' : step }}
                    <span
                        class="absolute inset-x-0 bottom-0 h-0.5 origin-left bg-orange-500 transition-transform duration-[650ms] ease-linear"
                        :class="
                            holding ? 'scale-x-100' : 'scale-x-0 duration-0'
                        "
                    />
                </span>
                <component v-else :is="icon" class="mt-0.5" />
                <span
                    class="min-w-0 flex-1 group-data-[collapsible=icon]:hidden"
                >
                    <span class="block truncate font-medium">{{ title }}</span>
                    <span
                        class="block truncate text-[0.7rem] leading-4 text-sidebar-foreground/60"
                    >
                        {{ description }}
                    </span>
                </span>
                <span
                    :id="`${title.toLowerCase().replaceAll(' ', '-')}-guide-hint`"
                    class="sr-only"
                >
                    Hold for 650 milliseconds or press Shift plus F1 for the
                    page guide.
                </span>
            </Link>
        </SidebarMenuButton>

        <button
            type="button"
            class="absolute right-0 top-1/2 z-10 flex h-11 w-11 -translate-y-1/2 items-center justify-center rounded-md text-sidebar-foreground/55 hover:bg-sidebar-accent hover:text-sidebar-accent-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-sidebar-ring pointer-fine:h-8 pointer-fine:w-8 group-data-[collapsible=icon]:hidden"
            :aria-label="`How to use ${title}`"
            @click="openGuide"
        >
            <CircleHelp class="h-4 w-4" aria-hidden="true" />
        </button>
        <Dialog v-model:open="guideOpen">
            <DialogContent
                class="bottom-0 top-auto w-full max-w-none translate-x-[-50%] translate-y-0 rounded-b-none rounded-t-2xl border-sidebar-border p-0 sm:bottom-auto sm:top-1/2 sm:max-w-md sm:translate-y-[-50%] sm:rounded-2xl"
                data-testid="workspace-navigation-guide"
                :show-close-button="false"
            >
                <DialogHeader
                    class="border-b border-slate-200 px-5 py-4 text-left dark:border-slate-800"
                >
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p
                                class="text-xs font-bold uppercase tracking-[0.16em] text-orange-600"
                            >
                                {{
                                    branch
                                        ? 'Bulk branch'
                                        : step
                                          ? `Step ${step}`
                                          : 'Reference'
                                }}
                            </p>
                            <DialogTitle class="mt-1 text-xl">{{
                                title
                            }}</DialogTitle>
                        </div>
                        <button
                            type="button"
                            class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full text-slate-500 hover:bg-slate-100 hover:text-slate-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-orange-500 pointer-fine:h-9 pointer-fine:w-9 dark:hover:bg-slate-800 dark:hover:text-white"
                            aria-label="Close page guide"
                            @click="guideOpen = false"
                        >
                            <X class="h-4 w-4" aria-hidden="true" />
                        </button>
                    </div>
                    <DialogDescription
                        class="mt-2 text-sm leading-6 text-slate-600 dark:text-slate-300"
                    >
                        {{ guide.purpose }}
                    </DialogDescription>
                </DialogHeader>

                <div class="space-y-4 px-5 py-4 text-sm">
                    <div>
                        <p class="font-semibold text-slate-950 dark:text-white">
                            Before you begin
                        </p>
                        <p
                            class="mt-1 leading-6 text-slate-600 dark:text-slate-300"
                        >
                            {{ guide.beforeYouBegin }}
                        </p>
                    </div>
                    <ol class="space-y-2">
                        <li
                            v-for="(instruction, index) in guide.steps"
                            :key="instruction"
                            class="flex gap-3"
                        >
                            <span
                                class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-slate-950 text-xs font-bold text-white dark:bg-white dark:text-slate-950"
                            >
                                {{ index + 1 }}
                            </span>
                            <span
                                class="pt-0.5 leading-5 text-slate-700 dark:text-slate-200"
                                >{{ instruction }}</span
                            >
                        </li>
                    </ol>
                    <p
                        class="rounded-xl bg-orange-50 px-3 py-2.5 leading-5 text-orange-950 dark:bg-orange-950/40 dark:text-orange-100"
                    >
                        <strong>Next:</strong> {{ guide.next }}
                    </p>
                </div>

                <div
                    class="flex flex-col-reverse gap-2 border-t border-slate-200 px-5 py-4 sm:flex-row sm:justify-end dark:border-slate-800"
                >
                    <Link
                        :href="guideHref"
                        class="inline-flex min-h-11 items-center justify-center rounded-lg px-4 text-sm font-semibold text-slate-700 hover:bg-slate-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-orange-500 dark:text-slate-200 dark:hover:bg-slate-800"
                    >
                        Full guide
                    </Link>
                    <Link
                        :href="href"
                        class="inline-flex min-h-11 items-center justify-center rounded-lg bg-orange-600 px-4 text-sm font-semibold text-white hover:bg-orange-500 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-orange-500 focus-visible:ring-offset-2"
                    >
                        Open {{ title }}
                    </Link>
                </div>
            </DialogContent>
        </Dialog>
    </SidebarMenuItem>
</template>
