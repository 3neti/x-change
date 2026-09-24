<script setup lang="ts">
import { Download, Maximize2, Printer } from 'lucide-vue-next';
import { computed, ref } from 'vue';

const props = defineProps<{
    campaign: {
        title: string;
        merchant_display_name: string;
        payment_qr: {
            reference: string;
            amount_mode: 'fixed' | 'open';
            fixed_amount_minor: number | null;
            currency: string;
            qr_data_uri: string | null;
            generated_at: string | null;
        };
    };
}>();

const expanded = ref(false);
const amount = computed(() => {
    if (props.campaign.payment_qr.amount_mode !== 'fixed') return 'Open amount';

    return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: props.campaign.payment_qr.currency,
    }).format((props.campaign.payment_qr.fixed_amount_minor ?? 0) / 100);
});

function printStamp(): void {
    globalThis.print();
}
</script>

<template>
    <section
        class="campaign-payment-qr-print mt-4 overflow-hidden rounded-[1.75rem] border border-slate-200 bg-slate-950 p-5 text-white shadow-inner dark:border-slate-800"
        data-testid="campaign-payment-qr-stamp"
    >
        <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
                <p
                    class="text-[0.65rem] font-semibold uppercase tracking-[0.22em] text-cyan-200"
                >
                    Reusable payment QR Ph
                </p>
                <h3 class="mt-2 text-2xl font-semibold leading-tight">
                    {{ campaign.title }}
                </h3>
                <p class="mt-1 text-sm text-slate-300">
                    {{ campaign.merchant_display_name }} · {{ amount }}
                </p>
            </div>
            <span
                class="rounded-full bg-emerald-400/15 px-2 py-1 text-xs font-semibold text-emerald-200"
            >
                Ready
            </span>
        </div>

        <button
            type="button"
            class="mt-5 flex w-full items-center justify-center rounded-2xl bg-white p-4"
            :aria-label="`Enlarge QR Ph for ${campaign.title}`"
            :aria-expanded="expanded"
            data-testid="campaign-payment-qr-enlarge"
            @click="expanded = !expanded"
        >
            <img
                v-if="campaign.payment_qr.qr_data_uri"
                :src="campaign.payment_qr.qr_data_uri"
                :alt="`QR Ph for ${campaign.title}`"
                :class="expanded ? 'size-[min(78vw,30rem)]' : 'size-64'"
                class="object-contain transition-all"
                data-testid="campaign-payment-qr-image"
            />
            <Maximize2
                v-else
                class="size-16 text-slate-300"
                aria-hidden="true"
            />
        </button>

        <p class="mt-4 text-center text-xs leading-5 text-slate-300">
            Scan with GCash or Maya. Each confirmed payment is observed against
            this campaign.
        </p>

        <div class="mt-4 grid grid-cols-2 gap-2 print:hidden">
            <a
                v-if="campaign.payment_qr.qr_data_uri"
                :href="campaign.payment_qr.qr_data_uri"
                :download="`${campaign.title.toLowerCase().replaceAll(/[^a-z0-9]+/g, '-')}-qr-ph.png`"
                class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl border border-white/20 px-3 py-2 text-sm font-semibold"
                data-testid="campaign-payment-qr-download"
            >
                <Download class="size-4" aria-hidden="true" />Download
            </a>
            <button
                type="button"
                class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-white px-3 py-2 text-sm font-semibold text-slate-950"
                data-testid="campaign-payment-qr-print"
                @click="printStamp"
            >
                <Printer class="size-4" aria-hidden="true" />Print
            </button>
        </div>
    </section>
</template>

<style>
@media print {
    body * {
        visibility: hidden;
    }

    .campaign-payment-qr-print,
    .campaign-payment-qr-print * {
        visibility: visible;
    }

    .campaign-payment-qr-print {
        position: fixed;
        inset: 0;
        margin: auto;
        width: min(100%, 44rem);
    }
}
</style>
