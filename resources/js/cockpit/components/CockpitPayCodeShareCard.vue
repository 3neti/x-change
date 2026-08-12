<script setup lang="ts">
import {
    Check,
    Copy,
    Download,
    Facebook,
    Mail,
    MessageCircle,
    Share2,
    Smartphone,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';

// Presentation-only sharing surface. This component never receives voucher
// models or raw instruction payloads; it only accepts already-authorized,
// non-sensitive display strings. Every action here is browser-local: no
// delivery is sent and no journal assertion is produced.
const props = withDefaults(
    defineProps<{
        code?: string | null;
        claimUrl?: string | null;
        claimQr?: string | null;
        contextText?: string | null;
        variant?: 'prominent' | 'compact';
    }>(),
    {
        code: null,
        claimUrl: null,
        claimQr: null,
        contextText: null,
        variant: 'prominent',
    },
);

const copyState = ref<'idle' | 'copied' | 'failed'>('idle');

const normalizedCode = computed<string>(() => props.code?.trim() || 'Pay Code');
const normalizedClaimUrl = computed<string | null>(() => {
    const value = props.claimUrl?.trim();

    return value ? value : null;
});
const normalizedClaimQr = computed<string | null>(() => {
    const value = props.claimQr?.trim();

    return value ? value : null;
});
const normalizedContextText = computed<string | null>(() => {
    const value = props.contextText?.trim();

    return value ? value : null;
});
const isProminent = computed<boolean>(() => props.variant !== 'compact');

const shareTitle = computed<string>(() => `Pay Code ${normalizedCode.value}`);
const shareHeadline = computed<string>(
    () => `Claim Pay Code || ${normalizedCode.value} ||`,
);
// Recommended share copy is the headline followed by the canonical URL on
// its own line. This is used for destinations without a separate URL field
// (SMS, email, WhatsApp); native Share and Facebook carry the URL through
// their own dedicated fields instead of duplicating it in the text.
const shareMessage = computed<string>(() => {
    return normalizedClaimUrl.value
        ? `${shareHeadline.value}\n${normalizedClaimUrl.value}`
        : shareHeadline.value;
});
const encodedShareMessage = computed<string>(() =>
    encodeURIComponent(shareMessage.value),
);
const encodedShareTitle = computed<string>(() =>
    encodeURIComponent(shareTitle.value),
);
const encodedClaimUrl = computed<string>(() =>
    encodeURIComponent(normalizedClaimUrl.value ?? ''),
);

const canNativeShare = computed<boolean>(
    () =>
        typeof navigator !== 'undefined' &&
        typeof navigator.share === 'function',
);

const smsUrl = computed<string>(() => `sms:?body=${encodedShareMessage.value}`);
const emailUrl = computed<string>(
    () =>
        `mailto:?subject=${encodedShareTitle.value}&body=${encodedShareMessage.value}`,
);
const whatsappUrl = computed<string>(
    () => `https://wa.me/?text=${encodedShareMessage.value}`,
);
const facebookUrl = computed<string>(
    () =>
        `https://www.facebook.com/sharer/sharer.php?u=${encodedClaimUrl.value}`,
);
const downloadFilename = computed<string>(() => {
    const normalized = normalizedCode.value
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '');

    return `pay-code-${normalized || 'code'}.png`;
});

const copyStatusText = computed<string>(() => {
    if (copyState.value === 'copied') {
        return 'Claim link copied. No delivery was sent.';
    }

    if (copyState.value === 'failed') {
        return 'Copy is unavailable in this browser.';
    }

    return '';
});

async function shareNative(): Promise<void> {
    if (!canNativeShare.value || normalizedClaimUrl.value === null) {
        return;
    }

    try {
        await navigator.share({
            title: shareTitle.value,
            text: shareHeadline.value,
            url: normalizedClaimUrl.value,
        });
    } catch (error) {
        // A user-cancelled share must never surface as an error.
        if (error instanceof DOMException && error.name === 'AbortError') {
            return;
        }
    }
}

async function copyClaimLink(): Promise<void> {
    if (normalizedClaimUrl.value === null) {
        copyState.value = 'failed';

        return;
    }

    const clipboard = globalThis.navigator?.clipboard;

    if (!clipboard?.writeText) {
        copyState.value = 'failed';

        return;
    }

    try {
        // Only the canonical claim URL is copied, never the composed message.
        await clipboard.writeText(normalizedClaimUrl.value);
        copyState.value = 'copied';
    } catch {
        copyState.value = 'failed';
    }
}
</script>

<template>
    <section
        v-if="normalizedClaimUrl !== null"
        class="grid min-w-0 gap-4"
        :class="
            isProminent
                ? 'rounded-2xl border border-emerald-200 bg-white p-4 dark:border-emerald-900/70 dark:bg-slate-950 sm:p-5'
                : ''
        "
        data-testid="cockpit-pay-code-share-card"
        :data-variant="variant"
    >
        <div
            class="grid min-w-0 justify-items-center gap-4 text-center"
            data-testid="cockpit-pay-code-share-lead"
        >
            <div
                v-if="isProminent && normalizedClaimQr"
                class="flex shrink-0 justify-center"
            >
                <img
                    :src="normalizedClaimQr"
                    :alt="`QR code to claim Pay Code ${normalizedCode}`"
                    class="size-40 rounded-2xl border border-slate-200 bg-white p-2 shadow-sm dark:border-slate-800 sm:size-48"
                    data-testid="cockpit-pay-code-share-qr"
                />
            </div>

            <div class="grid min-w-0 justify-items-center gap-2 text-center">
                <p
                    class="text-[0.65rem] font-semibold tracking-[0.16em] text-emerald-700 uppercase dark:text-emerald-300"
                >
                    Share Pay Code
                </p>
                <p
                    class="min-w-0 break-all font-mono text-2xl font-bold tracking-[0.08em] text-slate-950 dark:text-white"
                    data-testid="cockpit-pay-code-share-code"
                >
                    || {{ normalizedCode }} ||
                </p>
                <p
                    v-if="normalizedContextText"
                    class="text-center text-sm text-slate-600 dark:text-slate-300"
                >
                    {{ normalizedContextText }}
                </p>
                <p
                    class="min-w-0 max-w-full break-all text-center text-sm leading-5"
                    data-testid="cockpit-pay-code-share-url"
                >
                    <a
                        :href="normalizedClaimUrl"
                        class="text-emerald-700 underline decoration-emerald-300 underline-offset-4 transition hover:text-emerald-900 dark:text-emerald-300 dark:decoration-emerald-700 dark:hover:text-emerald-100"
                        data-testid="cockpit-pay-code-share-url-link"
                    >{{ normalizedClaimUrl }}</a>
                </p>
            </div>
        </div>

        <button
            v-if="canNativeShare"
            type="button"
            class="inline-flex min-h-11 w-full min-w-0 items-center justify-center gap-2 rounded-xl bg-emerald-600 px-4 text-sm font-semibold text-white transition hover:bg-emerald-700"
            data-testid="cockpit-pay-code-share-native"
            @click="shareNative"
        >
            <Share2 class="size-4 shrink-0" aria-hidden="true" />
            Share
        </button>

        <div
            class="grid min-w-0 grid-cols-2 gap-2"
            data-testid="cockpit-pay-code-share-actions"
        >
            <button
                type="button"
                class="inline-flex min-h-11 min-w-0 items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-2 text-xs font-semibold text-slate-700 transition hover:border-emerald-300 hover:text-emerald-700 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-200 dark:hover:border-emerald-700 dark:hover:text-emerald-300"
                data-testid="cockpit-pay-code-share-copy"
                @click="copyClaimLink"
            >
                <Check
                    v-if="copyState === 'copied'"
                    class="size-4 shrink-0 text-emerald-600"
                    aria-hidden="true"
                />
                <Copy v-else class="size-4 shrink-0" aria-hidden="true" />
                <span class="text-center leading-4">{{
                    copyState === 'copied' ? 'Copied' : 'Copy Link'
                }}</span>
            </button>
            <a
                :href="smsUrl"
                class="inline-flex min-h-11 min-w-0 items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-2 text-xs font-semibold text-slate-700 transition hover:border-emerald-300 hover:text-emerald-700 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-200 dark:hover:border-emerald-700 dark:hover:text-emerald-300"
                data-testid="cockpit-pay-code-share-sms"
            >
                <Smartphone class="size-4 shrink-0" aria-hidden="true" />
                <span class="text-center leading-4">SMS</span>
            </a>
            <a
                :href="emailUrl"
                class="inline-flex min-h-11 min-w-0 items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-2 text-xs font-semibold text-slate-700 transition hover:border-emerald-300 hover:text-emerald-700 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-200 dark:hover:border-emerald-700 dark:hover:text-emerald-300"
                data-testid="cockpit-pay-code-share-email"
            >
                <Mail class="size-4 shrink-0" aria-hidden="true" />
                <span class="text-center leading-4">Email</span>
            </a>
            <a
                :href="whatsappUrl"
                target="_blank"
                rel="noopener noreferrer"
                class="inline-flex min-h-11 min-w-0 items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-2 text-xs font-semibold text-slate-700 transition hover:border-emerald-300 hover:text-emerald-700 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-200 dark:hover:border-emerald-700 dark:hover:text-emerald-300"
                data-testid="cockpit-pay-code-share-whatsapp"
            >
                <MessageCircle class="size-4 shrink-0" aria-hidden="true" />
                <span class="text-center leading-4">WhatsApp</span>
            </a>
            <a
                :href="facebookUrl"
                target="_blank"
                rel="noopener noreferrer"
                class="inline-flex min-h-11 min-w-0 items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-2 text-xs font-semibold text-slate-700 transition hover:border-emerald-300 hover:text-emerald-700 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-200 dark:hover:border-emerald-700 dark:hover:text-emerald-300"
                data-testid="cockpit-pay-code-share-facebook"
            >
                <Facebook class="size-4 shrink-0" aria-hidden="true" />
                <span class="text-center leading-4">Facebook</span>
            </a>
            <a
                v-if="normalizedClaimQr"
                :href="normalizedClaimQr"
                :download="downloadFilename"
                class="inline-flex min-h-11 min-w-0 items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-2 text-xs font-semibold text-slate-700 transition hover:border-emerald-300 hover:text-emerald-700 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-200 dark:hover:border-emerald-700 dark:hover:text-emerald-300"
                data-testid="cockpit-pay-code-share-download"
            >
                <Download class="size-4 shrink-0" aria-hidden="true" />
                <span class="text-center leading-4">Download QR</span>
            </a>
        </div>

        <p
            class="min-h-4 text-xs leading-4 text-slate-500 dark:text-slate-400"
            role="status"
            aria-live="polite"
            data-testid="cockpit-pay-code-share-status"
        >
            {{ copyStatusText }}
        </p>
    </section>
</template>
