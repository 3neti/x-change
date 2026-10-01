<script setup lang="ts">
import { computed } from 'vue';
import PayCodeLogo from './PayCodeLogo.vue';
import type { XChangeQrArtifactKind } from './qrArtifacts';

const props = withDefaults(
    defineProps<{
        kind: XChangeQrArtifactKind;
        src: string;
        alt: string;
        title: string;
        description?: string;
        identifier?: string | null;
        testId?: string;
        imageTestId?: string;
        expanded?: boolean;
    }>(),
    {
        description: '',
        identifier: null,
        testId: 'x-change-qr-artifact',
        imageTestId: undefined,
        expanded: false,
    },
);

const isPayment = computed<boolean>(() => props.kind === 'qrph_payment');
const isPayCode = computed<boolean>(() => props.kind === 'pay_code');
</script>

<template>
    <figure
        class="grid min-w-0 gap-3 rounded-[1.35rem] bg-white p-3 text-slate-950"
        :class="
            isPayment
                ? 'border-4 border-double border-sky-700 shadow-[0_0_0_1px_rgba(3,105,161,0.2)]'
                : 'border border-slate-200 shadow-sm'
        "
        :data-kind="kind"
        :data-testid="testId"
    >
        <figcaption class="grid gap-1 text-center">
            <div class="flex items-center justify-center gap-2">
                <PayCodeLogo
                    v-if="!isPayment"
                    variant="mark"
                    size="micro"
                    aria-hidden="true"
                    data-testid="x-change-qr-artifact-pay-code-mark"
                />
                <span
                    v-else
                    class="rounded-full bg-sky-700 px-3 py-1 text-[0.65rem] font-black uppercase tracking-[0.2em] text-white"
                    data-testid="x-change-qr-artifact-payment-label"
                >
                    QR Ph · Scan to pay
                </span>
                <span class="text-sm font-bold">{{ title }}</span>
            </div>
            <p v-if="description" class="text-xs text-slate-600">
                {{ description }}
            </p>
        </figcaption>

        <div
            class="mx-auto flex aspect-square w-full items-center justify-center overflow-hidden rounded-xl bg-white"
            :class="expanded ? 'max-w-2xl p-2 sm:p-4' : 'max-w-72 p-2'"
        >
            <img
                :src="src"
                :alt="alt"
                class="aspect-square h-auto w-full object-contain"
                :class="expanded ? 'max-w-2xl' : ''"
                :width="expanded ? 1024 : 480"
                :height="expanded ? 1024 : 480"
                decoding="async"
                :data-testid="imageTestId ?? `${testId}-image`"
            />
        </div>

        <p
            v-if="identifier"
            class="text-center font-mono text-lg font-black tracking-[0.18em] text-slate-950 sm:text-xl"
            :data-testid="`${testId}-identifier`"
        >
            <template v-if="isPayCode">|| {{ identifier }} ||</template>
            <template v-else>{{ identifier }}</template>
        </p>
    </figure>
</template>
