<script setup lang="ts">
import { preview } from '@/routes/x-change/stamps';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
const props = defineProps<{
    designId: string;
    revision: number;
    provider?: string | null;
    stampPresentation?: Record<string, unknown> | null;
    slots: Record<string, string>;
    indicatorKeys?: string[];
    claimQr?: string | null;
    claimQrLabel?: string;
    code?: string | null;
    interactive?: boolean;
    marker?: 'none' | 'code' | 'qr' | 'both';
    position?: 'top_left' | 'top_right' | 'bottom_left' | 'bottom_right';
}>();
const markerBounds = ref<{
    x: number;
    y: number;
    width: number;
    height: number;
} | null>(null);
const markerStyle = computed(() =>
    markerBounds.value
        ? {
              left: `${markerBounds.value.x / 12}%`,
              top: `${markerBounds.value.y / 6.3}%`,
              width: `${markerBounds.value.width / 12}%`,
              height: `${markerBounds.value.height / 6.3}%`,
          }
        : {},
);
const emit = defineEmits<{
    claimQrClick: [];
    previewError: [failed: boolean];
}>();
const image = ref('');
const providerName = ref('');
const error = ref('');
watch(error, (value) => emit('previewError', value !== ''));
const retry = ref(0);
const loading = ref(false);
let timer: ReturnType<typeof setTimeout> | undefined;
let request: AbortController | undefined;
let sequence = 0;
watch(
    () => [
        props.designId,
        props.revision,
        props.provider,
        props.stampPresentation,
        props.marker,
        props.code,
        props.slots,
        props.indicatorKeys,
        props.position,
        retry.value,
    ],
    () => {
        clearTimeout(timer);
        request?.abort();
        const current = ++sequence;
        error.value = '';
        loading.value = true;
        timer = setTimeout(async () => {
            request = new AbortController();
            try {
                const cookie = document.cookie
                    .split('; ')
                    .find((value) => value.startsWith('XSRF-TOKEN='));
                const response = await fetch(preview.url(), {
                    method: 'POST',
                    credentials: 'same-origin',
                    signal: request.signal,
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        ...(cookie
                            ? {
                                  'X-XSRF-TOKEN': decodeURIComponent(
                                      cookie.slice(11),
                                  ),
                              }
                            : {}),
                    },
                    body: JSON.stringify({
                        design_id: props.designId,
                        revision: props.revision,
                        ...(props.provider ? { provider: props.provider } : {}),
                        position: props.position ?? 'bottom_right',
                        slots: props.slots,
                        indicator_keys: props.indicatorKeys ?? [],
                        presentation: props.stampPresentation ?? undefined,
                        marker: props.marker ?? 'qr',
                        code: props.code || undefined,
                    }),
                });
                const result = await response.json().catch(() => ({
                    message: 'Stamp preview unavailable. Please try again.',
                }));
                if (!response.ok) {
                    throw new Error(
                        result.message || 'Stamp preview unavailable.',
                    );
                }
                if (
                    typeof result.image !== 'string' ||
                    !result.image.startsWith('data:image/png;base64,')
                ) {
                    throw new Error('Stamp preview unavailable.');
                }
                if (current === sequence) {
                    image.value = result.image;
                    providerName.value =
                        props.slots.brand &&
                        typeof result.provider_identity?.name === 'string'
                            ? result.provider_identity.name
                            : '';
                    markerBounds.value = result.marker_bounds ?? null;
                }
            } catch (failure) {
                if (
                    current === sequence &&
                    !(
                        failure instanceof DOMException &&
                        failure.name === 'AbortError'
                    )
                ) {
                    error.value =
                        failure instanceof Error
                            ? failure.message
                            : 'Stamp preview unavailable.';
                }
            } finally {
                if (current === sequence) {
                    loading.value = false;
                }
            }
        }, 350);
    },
    { immediate: true, deep: true },
);
onBeforeUnmount(() => {
    clearTimeout(timer);
    request?.abort();
    sequence++;
});
</script>
<template>
    <div
        class="@container relative aspect-[1200/630] w-full overflow-hidden rounded-[1.4rem]"
        data-testid="system-stamp"
    >
        <img
            v-if="image"
            :src="image"
            :alt="`${providerName ? `${providerName} ` : ''}${slots.brand}: ${slots.amount}. ${slots.recipient}. ${slots.purpose}. ${slots.expiry || ''}. ${slots.instructions || ''}`"
            class="block h-full w-full"
        />
        <button
            v-if="
                image &&
                markerBounds &&
                claimQr &&
                interactive &&
                marker !== 'code' &&
                marker !== 'none' &&
                !loading &&
                !error
            "
            type="button"
            data-testid="system-stamp-claim-marker"
            :style="markerStyle"
            aria-label="Expand claim QR"
            class="absolute rounded-lg focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white"
            @click="emit('claimQrClick')"
        />
        <p
            v-if="loading"
            role="status"
            class="absolute bottom-2 left-2 rounded bg-slate-950/80 px-3 py-1 text-xs text-white"
        >
            Updating preview…
        </p>
        <div
            v-if="error"
            class="absolute inset-x-0 bottom-0 bg-white/95 p-3 text-sm text-slate-900"
        >
            <p role="alert">
                {{ image ? 'Previous preview shown. ' : '' }}{{ error }}
            </p>
            <button
                type="button"
                class="relative z-20 mt-2 rounded border px-3 py-1"
                @click="retry++"
            >
                Try Again
            </button>
        </div>
    </div>
</template>
