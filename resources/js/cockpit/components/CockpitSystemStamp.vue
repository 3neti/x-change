<script setup lang="ts">
import { preview } from '@/routes/x-change/stamps';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
const props = defineProps<{
    designId: string;
    revision: number;
    provider?: string | null;
    slots: Record<string, string>;
    claimQr?: string | null;
    claimQrLabel?: string;
    code?: string | null;
    interactive?: boolean;
    marker?: 'none' | 'code' | 'qr' | 'both';
    position?: 'top_left' | 'top_right' | 'bottom_left' | 'bottom_right';
}>();
const markerPalette = ref({ paper: '#FFFFFF', trim: '#FFFFFF' });
const markerStyle = computed(() => ({
    backgroundColor: markerPalette.value.paper,
    border: `2px solid ${markerPalette.value.trim}`,
    width: props.marker === 'code' ? '20.833%' : '15.833%',
    [props.position?.endsWith('_left') ? 'left' : 'right']: '4.5%',
    [props.position?.startsWith('top_') ? 'top' : 'bottom']: '8.571%',
}));
const emit = defineEmits<{ claimQrClick: [] }>();
const image = ref('');
const providerName = ref('');
const error = ref('');
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
        props.slots,
        props.position,
        retry.value,
    ],
    () => {
        clearTimeout(timer);
        request?.abort();
        const current = ++sequence;
        image.value = '';
        providerName.value = '';
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
                    providerName.value = props.slots.brand && typeof result.provider_identity?.name === 'string'
                        ? result.provider_identity.name
                        : '';
                    const palette = result.marker_palette;
                    markerPalette.value =
                        palette &&
                        /^#[0-9a-f]{6}$/i.test(palette.paper) &&
                        /^#[0-9a-f]{6}$/i.test(palette.trim)
                            ? palette
                            : { paper: '#FFFFFF', trim: '#FFFFFF' };
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
            :alt="`${slots.brand}${providerName ? ` · ${providerName}` : ''}: ${slots.amount}. ${slots.recipient}. ${slots.purpose}. ${slots.expiry || ''}. ${slots.instructions || ''}`"
            class="block h-full w-full"
        />
        <div
            v-if="image && marker !== 'none'"
            data-testid="system-stamp-claim-marker"
            :style="markerStyle"
            class="absolute isolate flex flex-col items-center gap-1 rounded-lg p-[clamp(2px,0.833cqw,10px)] text-center text-[clamp(6px,2.5cqw,12px)] text-slate-900"
        >
            <template v-if="marker !== 'code'">
                <button
                    v-if="claimQr && interactive"
                    type="button"
                    aria-label="Expand claim QR"
                    class="w-full"
                    @click="emit('claimQrClick')"
                >
                    <img
                        :src="claimQr"
                        :alt="claimQrLabel || 'Claim Pay Code QR'"
                        class="aspect-square w-full mix-blend-multiply"
                    />
                </button>
                <img
                    v-else-if="claimQr"
                    :src="claimQr"
                    :alt="claimQrLabel || 'Claim Pay Code QR'"
                    class="aspect-square w-full mix-blend-multiply"
                />
                <span
                    v-else
                    class="grid aspect-square w-full place-items-center"
                    >QR appears after issuance</span
                >
            </template>
            <span
                v-if="marker === 'code' || marker === 'both'"
                class="break-all font-mono"
                >{{ code || 'Code appears after issuance' }}</span
            >
        </div>
        <p v-if="loading" role="status" class="p-6 text-sm">Rendering Stamp…</p>
        <div v-if="error" class="p-6 text-sm">
            <p role="alert">{{ error }}</p>
            <button
                type="button"
                class="mt-3 rounded border px-3 py-2"
                @click="retry++"
            >
                Try Again
            </button>
        </div>
    </div>
</template>
