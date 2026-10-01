<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref } from 'vue';
import AppContent from '@/components/AppContent.vue';
import AppShell from '@/components/AppShell.vue';
import AppSidebar from '@/components/AppSidebar.vue';
import AppSidebarHeader from '@/components/AppSidebarHeader.vue';
import type { BreadcrumbItem } from '@/types';

type Props = {
    breadcrumbs?: BreadcrumbItem[];
};

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});

const mobileHeaderVisible = ref(false);
const mobileBreakpoint = '(max-width: 767px)';
const revealDistance = 56;
const topGestureBoundary = 72;
const visibleDuration = 5_000;

let touchStartY: number | null = null;
let touchStartedAtTop = false;
let hideTimer: ReturnType<typeof setTimeout> | null = null;

function clearHideTimer(): void {
    if (hideTimer === null) {
        return;
    }

    clearTimeout(hideTimer);
    hideTimer = null;
}

function hideMobileHeader(): void {
    clearHideTimer();
    mobileHeaderVisible.value = false;
}

function revealMobileHeader(): void {
    clearHideTimer();
    mobileHeaderVisible.value = true;
    hideTimer = setTimeout(hideMobileHeader, visibleDuration);
}

function resetTouchGesture(): void {
    touchStartY = null;
    touchStartedAtTop = false;
}

function handleTouchStart(event: TouchEvent): void {
    const touch = event.touches.item(0);

    if (touch === null || !window.matchMedia(mobileBreakpoint).matches) {
        resetTouchGesture();

        return;
    }

    touchStartY = touch.clientY;
    touchStartedAtTop =
        window.scrollY <= 0 && touch.clientY <= topGestureBoundary;
}

function handleTouchMove(event: TouchEvent): void {
    if (touchStartY === null) {
        return;
    }

    const touch = event.touches.item(0);

    if (touch === null) {
        return;
    }

    const distance = touch.clientY - touchStartY;

    if (touchStartedAtTop && distance >= revealDistance) {
        revealMobileHeader();
        resetTouchGesture();

        return;
    }

    if (mobileHeaderVisible.value && distance <= -revealDistance) {
        hideMobileHeader();
        resetTouchGesture();
    }
}

onMounted(() => {
    window.addEventListener('touchstart', handleTouchStart, { passive: true });
    window.addEventListener('touchmove', handleTouchMove, { passive: true });
    window.addEventListener('touchend', resetTouchGesture, { passive: true });
    window.addEventListener('touchcancel', resetTouchGesture, {
        passive: true,
    });
});

onBeforeUnmount(() => {
    clearHideTimer();
    window.removeEventListener('touchstart', handleTouchStart);
    window.removeEventListener('touchmove', handleTouchMove);
    window.removeEventListener('touchend', resetTouchGesture);
    window.removeEventListener('touchcancel', resetTouchGesture);
});
</script>

<template>
    <AppShell variant="sidebar">
        <AppSidebar />
        <AppContent variant="sidebar" class="overflow-x-hidden">
            <div class="hidden md:block">
                <AppSidebarHeader :breadcrumbs="breadcrumbs" />
            </div>

            <Transition
                enter-active-class="transition duration-200 ease-out motion-reduce:transition-none"
                enter-from-class="-translate-y-full opacity-0"
                enter-to-class="translate-y-0 opacity-100"
                leave-active-class="transition duration-150 ease-in motion-reduce:transition-none"
                leave-from-class="translate-y-0 opacity-100"
                leave-to-class="-translate-y-full opacity-0"
            >
                <div
                    v-if="mobileHeaderVisible"
                    class="fixed inset-x-0 top-0 z-50 border-b border-sidebar-border/70 bg-background/95 shadow-sm backdrop-blur md:hidden"
                    data-testid="cockpit-mobile-revealed-header"
                    @pointerenter="clearHideTimer"
                    @pointerleave="revealMobileHeader"
                    @focusin="clearHideTimer"
                    @focusout="revealMobileHeader"
                >
                    <AppSidebarHeader :breadcrumbs="breadcrumbs" />
                </div>
            </Transition>

            <slot />
        </AppContent>
    </AppShell>
</template>
