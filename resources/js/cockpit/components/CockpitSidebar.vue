<script setup lang="ts">
import {
    cockpitPrimaryNavigation,
    cockpitSecondaryNavigation,
} from '../navigation';
import type { CockpitNavigationItem } from '../types';

const props = withDefaults(defineProps<{
    activeKey?: string;
    primaryItems?: CockpitNavigationItem[];
    secondaryItems?: CockpitNavigationItem[];
}>(), {
    activeKey: 'dashboard',
    primaryItems: () => cockpitPrimaryNavigation,
    secondaryItems: () => cockpitSecondaryNavigation,
});

const isEnabled = (item: CockpitNavigationItem): boolean => item.enabled !== false;

const navItemClass = (item: CockpitNavigationItem, activeKey?: string): string[] => [
    'group flex items-center justify-between gap-3 rounded-lg px-3 py-2 text-sm transition',
    item.key === activeKey && isEnabled(item)
        ? 'bg-white text-slate-950 shadow-sm'
        : isEnabled(item)
            ? 'text-slate-300 hover:bg-white/10 hover:text-white'
            : 'cursor-not-allowed text-slate-500 opacity-75',
];

</script>

<template>
    <aside
        class="hidden w-72 shrink-0 border-r border-slate-200 bg-slate-950 text-slate-100 lg:flex lg:flex-col"
        data-testid="cockpit-sidebar"
    >
        <div class="border-b border-white/10 px-5 py-4">
            <p class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-400">
                Command Center
            </p>
            <p class="mt-1 text-sm text-slate-300">
                Money · Pay Codes · Operations
            </p>
        </div>

        <nav class="flex flex-1 flex-col gap-6 overflow-y-auto px-3 py-4" aria-label="Cockpit navigation">
            <div class="space-y-1">
                <template
                    v-for="item in primaryItems"
                    :key="item.key"
                >
                    <a
                        v-if="isEnabled(item)"
                        :href="item.href"
                        :aria-current="item.key === activeKey ? 'page' : undefined"
                        :class="navItemClass(item, activeKey)"
                        data-testid="cockpit-nav-item"
                    >
                        <span>
                            <span class="block font-medium">{{ item.label }}</span>
                            <span class="block text-xs opacity-70">{{ item.description }}</span>
                        </span>
                        <span
                            v-if="item.badge"
                            class="rounded-full bg-amber-300 px-2 py-0.5 text-[0.65rem] font-semibold uppercase tracking-wide text-amber-950"
                        >
                            {{ item.badge }}
                        </span>
                    </a>
                    <span
                        v-else
                        role="link"
                        aria-disabled="true"
                        :title="item.disabledReason"
                        :class="navItemClass(item, activeKey)"
                        data-testid="cockpit-nav-item-disabled"
                    >
                        <span>
                            <span class="block font-medium">{{ item.label }}</span>
                            <span class="block text-xs opacity-70">{{ item.description }}</span>
                        </span>
                        <span
                            class="rounded-full bg-slate-700 px-2 py-0.5 text-[0.65rem] font-semibold uppercase tracking-wide text-slate-300"
                        >
                            {{ item.disabledLabel ?? 'Coming soon' }}
                        </span>
                    </span>
                </template>
            </div>

            <div class="mt-auto space-y-1 border-t border-white/10 pt-4">
                <p class="px-3 text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">
                    Controls
                </p>
                <template
                    v-for="item in secondaryItems"
                    :key="item.key"
                >
                    <a
                        v-if="isEnabled(item)"
                        :href="item.href"
                        :aria-current="item.key === activeKey ? 'page' : undefined"
                        :class="navItemClass(item, activeKey)"
                        data-testid="cockpit-nav-item"
                    >
                        <span class="font-medium">{{ item.label }}</span>
                        <span
                            v-if="item.badge"
                            class="rounded-full bg-sky-300 px-2 py-0.5 text-[0.65rem] font-semibold uppercase tracking-wide text-sky-950"
                        >
                            {{ item.badge }}
                        </span>
                    </a>
                    <span
                        v-else
                        role="link"
                        aria-disabled="true"
                        :title="item.disabledReason"
                        :class="navItemClass(item, activeKey)"
                        data-testid="cockpit-nav-item-disabled"
                    >
                        <span class="font-medium">{{ item.label }}</span>
                        <span
                            class="rounded-full bg-slate-700 px-2 py-0.5 text-[0.65rem] font-semibold uppercase tracking-wide text-slate-300"
                        >
                            {{ item.disabledLabel ?? 'Coming soon' }}
                        </span>
                    </span>
                </template>
            </div>
        </nav>
    </aside>
</template>
