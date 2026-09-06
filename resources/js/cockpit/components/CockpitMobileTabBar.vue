<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    BadgePlus,
    CircleGauge,
    FileStack,
    Landmark,
    Megaphone,
} from 'lucide-vue-next';
import type { Component } from 'vue';
import { cockpitMobilePrimaryNavigation } from '../navigation';

const props = withDefaults(
    defineProps<{
        activeKey?: string;
    }>(),
    {
        activeKey: 'dashboard',
    },
);

const navigationIcons: Record<string, Component> = {
    funding: Landmark,
    'quick-generate': BadgePlus,
    campaigns: Megaphone,
    'pay-codes': FileStack,
    dashboard: CircleGauge,
};
</script>

<template>
    <nav
        class="border-border bg-card/95 fixed inset-x-0 bottom-0 z-50 border-t pb-[env(safe-area-inset-bottom)] shadow-[0_-8px_24px_rgba(15,23,42,0.08)] backdrop-blur md:hidden"
        aria-label="Cockpit primary navigation"
        data-testid="cockpit-mobile-tab-bar"
    >
        <div class="mx-auto grid min-h-16 max-w-lg grid-cols-5 px-1">
            <Link
                v-for="item in cockpitMobilePrimaryNavigation"
                :key="item.key"
                :href="item.href"
                prefetch
                class="focus-visible:outline-primary flex min-h-14 min-w-0 flex-col items-center justify-center gap-1 rounded-lg px-1 py-2 text-center text-[0.65rem] font-semibold leading-none transition focus-visible:outline-2 focus-visible:outline-offset-[-2px]"
                :class="
                    props.activeKey === item.key
                        ? 'bg-primary/10 text-primary'
                        : 'text-muted-foreground hover:bg-muted hover:text-foreground'
                "
                :aria-current="
                    props.activeKey === item.key ? 'page' : undefined
                "
                :data-testid="`cockpit-mobile-tab-${item.key}`"
            >
                <component
                    :is="navigationIcons[item.key]"
                    class="size-5 shrink-0"
                    aria-hidden="true"
                />
                <span class="w-full truncate">{{ item.label }}</span>
            </Link>
        </div>
    </nav>
</template>
