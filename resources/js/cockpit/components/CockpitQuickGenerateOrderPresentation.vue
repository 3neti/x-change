<script setup lang="ts">
import PayCodeLogo from '../../components/x-change/PayCodeLogo.vue';

defineProps<{
    amount: string;
    recipient: string;
    payCodeType: string;
    estimatedCost: string;
    purpose: string;
}>();
</script>

<template>
    <section
        class="rounded-2xl border border-emerald-200 bg-white/95 p-4 text-slate-950 shadow-[0_24px_60px_-32px_rgba(15,23,42,0.45)]"
        aria-label="Issuance order"
        data-testid="cockpit-quick-generate-order-presentation"
    >
        <header class="flex items-start justify-between gap-4">
            <div>
                <p
                    class="text-[0.65rem] font-semibold uppercase tracking-[0.18em] text-emerald-700"
                >
                    Issuance
                </p>
                <h2 class="mt-1 text-xl font-semibold tracking-tight">Order</h2>
                <p class="mt-1 text-xs leading-5 text-slate-500">
                    Set the value, payee, and purpose.
                </p>
            </div>
            <PayCodeLogo
                variant="mark"
                class-name="!h-10 !max-h-10 !max-w-10"
            />
        </header>

        <div class="mt-4 grid gap-3 sm:grid-cols-2">
            <div
                class="rounded-xl border border-slate-200 bg-slate-50 px-3.5 py-3"
            >
                <p class="text-[0.68rem] font-medium text-slate-500">Amount</p>
                <p
                    class="mt-1 text-3xl font-semibold tabular-nums tracking-[-0.04em]"
                >
                    {{ amount }}
                </p>
                <div
                    class="mt-3 flex items-baseline justify-between gap-3 border-t border-slate-200 pt-2 text-xs"
                >
                    <span class="text-slate-500">Estimated Cost</span>
                    <span class="font-semibold tabular-nums">
                        {{ estimatedCost }}
                    </span>
                </div>
            </div>

            <div
                class="rounded-xl border border-slate-200 bg-white px-3.5 py-3"
            >
                <p class="text-[0.68rem] font-medium text-slate-500">Pay To</p>
                <p class="mt-2 text-sm font-semibold leading-5">
                    {{ recipient }}
                </p>
                <p class="mt-3 text-[0.68rem] leading-4 text-slate-500">
                    Recipient, mobile, or payout route.
                </p>
            </div>
        </div>

        <dl class="mt-3 grid gap-3 sm:grid-cols-2">
            <div class="rounded-xl border border-slate-200 px-3.5 py-3">
                <dt class="text-[0.68rem] font-medium text-slate-500">
                    Pay Code Type
                </dt>
                <dd class="mt-1.5">
                    <span
                        class="inline-flex rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-800"
                    >
                        {{ payCodeType }}
                    </span>
                </dd>
            </div>
            <div class="rounded-xl border border-slate-200 px-3.5 py-3">
                <dt class="text-[0.68rem] font-medium text-slate-500">
                    Purpose
                </dt>
                <dd class="mt-1.5 text-sm font-semibold">
                    {{ purpose }}
                </dd>
            </div>
        </dl>

        <div
            class="mt-4 flex items-center justify-between gap-4 border-t border-emerald-100 pt-3"
        >
            <p class="text-[0.68rem] leading-4 text-slate-500">
                Illustrative order · no transaction
            </p>
            <span
                class="inline-flex items-center gap-2 text-xs font-semibold text-emerald-700"
            >
                <span class="size-2 rounded-full bg-emerald-500" />
                Ready to issue
            </span>
        </div>
    </section>
</template>
