<script setup lang="ts">
import {
    Check,
    Copy,
    Download,
    ExternalLink,
    Share2,
    X,
} from 'lucide-vue-next';
import { nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
import XChangeQrArtifact from '../../components/x-change/XChangeQrArtifact.vue';
import type { XChangeQrArtifactData } from '../../components/x-change/qrArtifacts';
import { cockpitClaimEntryLauncherEvent } from '../claimEntryLauncher';

const props = defineProps<{
    artifact?: XChangeQrArtifactData | null;
}>();

const isOpen = ref(false);
const copied = ref(false);
const closeButton = ref<HTMLButtonElement | null>(null);
let previouslyFocusedElement: HTMLElement | null = null;
let previousBodyOverflow = '';
let ownsHistoryEntry = false;
let copyStatusTimer: ReturnType<typeof setTimeout> | null = null;

function open(): void {
    if (isOpen.value || !props.artifact) {
        return;
    }

    previouslyFocusedElement = document.activeElement as HTMLElement | null;
    previousBodyOverflow = document.body.style.overflow;
    document.body.style.overflow = 'hidden';
    window.history.pushState(
        { ...window.history.state, xChangeClaimEntryLauncher: true },
        '',
        window.location.href,
    );
    ownsHistoryEntry = true;
    isOpen.value = true;
    void nextTick(() => closeButton.value?.focus());
}

function closeImmediately(): void {
    if (!isOpen.value) {
        return;
    }

    isOpen.value = false;
    ownsHistoryEntry = false;
    document.body.style.overflow = previousBodyOverflow;
    void nextTick(() => previouslyFocusedElement?.focus());
}

function close(): void {
    if (!isOpen.value) {
        return;
    }

    if (ownsHistoryEntry) {
        window.history.back();

        return;
    }

    closeImmediately();
}

function handlePopState(): void {
    if (isOpen.value) {
        closeImmediately();
    }
}

function handleKeydown(event: KeyboardEvent): void {
    if (event.key === 'Escape' && isOpen.value) {
        close();
    }
}

async function copyLink(): Promise<void> {
    if (!props.artifact) {
        return;
    }

    await navigator.clipboard.writeText(props.artifact.destination);
    copied.value = true;

    if (copyStatusTimer !== null) {
        clearTimeout(copyStatusTimer);
    }

    copyStatusTimer = setTimeout(() => {
        copied.value = false;
        copyStatusTimer = null;
    }, 2000);
}

async function share(): Promise<void> {
    if (!props.artifact) {
        return;
    }

    if (typeof navigator.share !== 'function') {
        await copyLink();

        return;
    }

    try {
        await navigator.share({
            title: props.artifact.title,
            text: 'Scan or open this page to enter and claim a Pay Code.',
            url: props.artifact.destination,
        });
    } catch (error) {
        if (!(error instanceof DOMException && error.name === 'AbortError')) {
            throw error;
        }
    }
}

onMounted(() => {
    window.addEventListener(cockpitClaimEntryLauncherEvent, open);
    window.addEventListener('popstate', handlePopState);
    window.addEventListener('keydown', handleKeydown);
});

onBeforeUnmount(() => {
    window.removeEventListener(cockpitClaimEntryLauncherEvent, open);
    window.removeEventListener('popstate', handlePopState);
    window.removeEventListener('keydown', handleKeydown);
    document.body.style.overflow = previousBodyOverflow;

    if (copyStatusTimer !== null) {
        clearTimeout(copyStatusTimer);
    }
});
</script>

<template>
    <Teleport to="body">
        <div
            v-if="isOpen && artifact"
            class="fixed inset-0 z-[100] flex items-end justify-center bg-slate-950/70 p-0 backdrop-blur-sm md:items-center md:p-6"
            data-testid="cockpit-claim-entry-launcher"
            @click.self="close"
        >
            <section
                role="dialog"
                aria-modal="true"
                aria-labelledby="cockpit-claim-entry-title"
                class="bg-background text-foreground flex max-h-[100dvh] w-full flex-col overflow-y-auto rounded-t-[2rem] border shadow-2xl md:max-w-xl md:rounded-[2rem]"
            >
                <header
                    class="flex items-start justify-between gap-4 px-5 pb-2 pt-5 sm:px-7 sm:pt-7"
                >
                    <div class="min-w-0">
                        <p
                            class="text-primary text-xs font-black uppercase tracking-[0.2em]"
                        >
                            Universal claim entry
                        </p>
                        <h2
                            id="cockpit-claim-entry-title"
                            class="mt-1 text-2xl font-black tracking-tight"
                        >
                            Show this QR to enter a Pay Code
                        </h2>
                        <p class="text-muted-foreground mt-2 text-sm leading-6">
                            This QR always opens the secure Pay Code entry page.
                            It contains no account, campaign, or Pay Code
                            details.
                        </p>
                    </div>
                    <button
                        ref="closeButton"
                        type="button"
                        class="hover:bg-muted focus-visible:ring-primary shrink-0 rounded-full p-2 transition focus-visible:outline-none focus-visible:ring-2"
                        aria-label="Close Claim QR"
                        data-testid="cockpit-claim-entry-close"
                        @click="close"
                    >
                        <X class="size-5" aria-hidden="true" />
                    </button>
                </header>

                <div
                    class="grid gap-5 px-5 pb-[calc(1.25rem+env(safe-area-inset-bottom))] sm:px-7 sm:pb-7"
                >
                    <XChangeQrArtifact
                        :kind="artifact.kind"
                        :src="artifact.image_data_uri"
                        :alt="`QR code for ${artifact.title}`"
                        :title="artifact.title"
                        :description="artifact.description"
                        :identifier="artifact.identifier"
                        test-id="cockpit-claim-entry-qr"
                        image-test-id="cockpit-claim-entry-qr-image"
                        expanded
                    />

                    <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                        <button
                            type="button"
                            class="border-border hover:bg-muted focus-visible:ring-primary inline-flex min-h-12 items-center justify-center gap-2 rounded-xl border px-3 text-sm font-bold transition focus-visible:outline-none focus-visible:ring-2"
                            data-testid="cockpit-claim-entry-share"
                            @click="share"
                        >
                            <Share2 class="size-4" aria-hidden="true" />
                            Share
                        </button>
                        <a
                            :href="artifact.image_data_uri"
                            download="pay-code-claim-entry.png"
                            class="border-border hover:bg-muted focus-visible:ring-primary inline-flex min-h-12 items-center justify-center gap-2 rounded-xl border px-3 text-sm font-bold transition focus-visible:outline-none focus-visible:ring-2"
                            data-testid="cockpit-claim-entry-download"
                        >
                            <Download class="size-4" aria-hidden="true" />
                            Download
                        </a>
                        <button
                            type="button"
                            class="border-border hover:bg-muted focus-visible:ring-primary inline-flex min-h-12 items-center justify-center gap-2 rounded-xl border px-3 text-sm font-bold transition focus-visible:outline-none focus-visible:ring-2"
                            data-testid="cockpit-claim-entry-copy"
                            @click="copyLink"
                        >
                            <Check
                                v-if="copied"
                                class="size-4"
                                aria-hidden="true"
                            />
                            <Copy v-else class="size-4" aria-hidden="true" />
                            {{ copied ? 'Copied' : 'Copy link' }}
                        </button>
                        <a
                            :href="artifact.destination"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="bg-primary text-primary-foreground hover:bg-primary/90 focus-visible:ring-primary inline-flex min-h-12 items-center justify-center gap-2 rounded-xl px-3 text-sm font-bold transition focus-visible:outline-none focus-visible:ring-2"
                            data-testid="cockpit-claim-entry-open"
                        >
                            <ExternalLink class="size-4" aria-hidden="true" />
                            Open
                        </a>
                    </div>

                    <p class="sr-only" aria-live="polite">
                        {{ copied ? 'Claim link copied.' : '' }}
                    </p>
                </div>
            </section>
        </div>
    </Teleport>
</template>
