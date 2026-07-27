<script setup lang="ts">
import {
    ArrowLeftRight,
    BadgeCheck,
    ScanLine,
    ShieldCheck,
    UserRound,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';

const props = withDefaults(
    defineProps<{
        amount: string | number;
        currency: string;
        recipient?: string;
        purpose?: string;
        claimOutcome: 'provider_disbursement' | 'account_funding';
        voucherType: 'redeemable' | 'payable' | 'settlement';
        expiry?: string;
        instructionLabels?: string[];
        issuedCode?: string | null;
    }>(),
    {
        recipient: '',
        purpose: '',
        expiry: 'No expiry',
        instructionLabels: () => [],
        issuedCode: null,
    },
);

const visibleSide = ref<'front' | 'back'>('front');

const formattedAmount = computed<string>(() => {
    const value = Number(props.amount);

    if (!Number.isFinite(value)) {
        return `${props.currency || 'PHP'} 0.00`;
    }

    return `${props.currency || 'PHP'} ${value.toLocaleString('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })}`;
});

const recipientLabel = computed<string>(() => {
    const recipient = props.recipient.trim();

    if (recipient === '' || recipient.toUpperCase() === 'CASH') {
        return 'Anyone with this Pay Code';
    }

    if (/^(\+|09|639|63)/.test(recipient)) {
        return `Mobile ending ${recipient.slice(-4)}`;
    }

    return recipient;
});

const capabilityLabel = computed<string>(() => {
    if (props.claimOutcome === 'account_funding') {
        return 'Add to Account';
    }

    if (props.voucherType === 'payable') {
        return 'Collect Payment';
    }

    if (props.voucherType === 'settlement') {
        return 'Settlement';
    }

    return 'Receive Funds';
});

const displayedCode = computed<string>(() => {
    return props.issuedCode?.trim() || 'PAY CODE PREVIEW';
});
</script>

<template>
    <section
        class="@container rounded-3xl border border-slate-200 bg-slate-100/80 p-3 shadow-inner dark:border-slate-800 dark:bg-slate-900/80"
        data-testid="cockpit-pay-code-canvas"
    >
        <div class="mb-3 flex items-center justify-between gap-3 px-1">
            <div>
                <p
                    class="text-[0.65rem] font-semibold tracking-[0.18em] text-slate-500 uppercase dark:text-slate-400"
                >
                    Live Pay Code
                </p>
                <p class="text-xs text-slate-600 dark:text-slate-300">
                    Updates as you design.
                </p>
            </div>
            <div class="flex items-center gap-2">
                <slot name="action" />
                <div
                    class="inline-flex rounded-full border border-slate-200 bg-white p-0.5 shadow-sm dark:border-slate-700 dark:bg-slate-950"
                    aria-label="Pay Code side"
                >
                    <button
                        type="button"
                        class="rounded-full px-3 py-1 text-[0.7rem] font-semibold transition"
                        :class="
                            visibleSide === 'front'
                                ? 'bg-slate-950 text-white dark:bg-white dark:text-slate-950'
                                : 'text-slate-500 dark:text-slate-400'
                        "
                        data-testid="cockpit-pay-code-canvas-front-button"
                        @click="visibleSide = 'front'"
                    >
                        Front
                    </button>
                    <button
                        type="button"
                        class="rounded-full px-3 py-1 text-[0.7rem] font-semibold transition"
                        :class="
                            visibleSide === 'back'
                                ? 'bg-slate-950 text-white dark:bg-white dark:text-slate-950'
                                : 'text-slate-500 dark:text-slate-400'
                        "
                        data-testid="cockpit-pay-code-canvas-back-button"
                        @click="visibleSide = 'back'"
                    >
                        Back
                    </button>
                </div>
            </div>
        </div>

        <article
            v-if="visibleSide === 'front'"
            class="relative aspect-[1.72/1] min-h-56 overflow-hidden rounded-[1.4rem] border border-amber-200 bg-[#fffaf0] p-5 text-slate-950 shadow-xl shadow-slate-900/10 @md:p-7 dark:border-amber-900/60 dark:bg-[#19170f] dark:text-amber-50"
            data-testid="cockpit-pay-code-canvas-front"
        >
            <div
                class="absolute inset-y-0 left-0 w-2 bg-emerald-600"
                aria-hidden="true"
            />
            <div
                class="absolute inset-y-0 left-3 w-0.5 bg-emerald-600/50"
                aria-hidden="true"
            />
            <div
                class="absolute -right-16 -bottom-20 size-64 rounded-full border-[2rem] border-emerald-700/5"
                aria-hidden="true"
            />

            <div class="relative flex h-full flex-col justify-between gap-5">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p
                            class="text-[0.65rem] font-black tracking-[0.22em] text-emerald-700 uppercase dark:text-emerald-300"
                        >
                            x-change
                        </p>
                        <p
                            class="mt-1 text-xs font-semibold text-slate-500 dark:text-amber-100/60"
                        >
                            Digital Pay Code
                        </p>
                    </div>
                    <span
                        class="rounded-full bg-emerald-700 px-3 py-1 text-[0.65rem] font-bold tracking-wide text-white uppercase"
                    >
                        {{ capabilityLabel }}
                    </span>
                </div>

                <div>
                    <p
                        class="text-[0.65rem] font-semibold tracking-[0.18em] text-slate-500 uppercase dark:text-amber-100/60"
                    >
                        Value
                    </p>
                    <p
                        class="mt-1 text-3xl font-black tracking-tight @md:text-4xl"
                        data-testid="cockpit-pay-code-canvas-amount"
                    >
                        {{ formattedAmount }}
                    </p>
                    <p
                        v-if="purpose"
                        class="mt-1 max-w-[80%] truncate text-xs text-slate-600 dark:text-amber-100/70"
                    >
                        {{ purpose }}
                    </p>
                </div>

                <div class="flex items-end justify-between gap-4">
                    <div class="min-w-0">
                        <p
                            class="flex items-center gap-1.5 text-[0.65rem] font-semibold text-slate-500 dark:text-amber-100/60"
                        >
                            <UserRound class="size-3.5" aria-hidden="true" />
                            Prepared for
                        </p>
                        <p
                            class="mt-1 truncate text-sm font-bold"
                            data-testid="cockpit-pay-code-canvas-recipient"
                        >
                            {{ recipientLabel }}
                        </p>
                    </div>
                    <div class="shrink-0 text-right">
                        <p
                            class="text-[0.6rem] font-semibold tracking-[0.18em] text-slate-500 uppercase dark:text-amber-100/60"
                        >
                            {{ issuedCode ? 'Issued code' : 'Preview' }}
                        </p>
                        <p
                            class="mt-1 font-mono text-sm font-black tracking-[0.14em]"
                        >
                            {{ displayedCode }}
                        </p>
                    </div>
                </div>
            </div>
        </article>

        <article
            v-else
            class="relative aspect-[1.72/1] min-h-56 overflow-hidden rounded-[1.4rem] bg-slate-950 p-5 text-white shadow-xl shadow-slate-900/20 @md:p-7"
            data-testid="cockpit-pay-code-canvas-back"
        >
            <div class="flex h-full items-center gap-5 @md:gap-8">
                <div
                    class="flex aspect-square w-24 shrink-0 items-center justify-center rounded-2xl border border-dashed border-white/25 bg-white/5 @md:w-32"
                >
                    <BadgeCheck
                        v-if="issuedCode"
                        class="size-12 text-emerald-300"
                        aria-label="Pay Code issued"
                    />
                    <ScanLine
                        v-else
                        class="size-12 text-white/35"
                        aria-label="Claim QR appears after issue"
                    />
                </div>
                <div class="min-w-0">
                    <p
                        class="text-[0.65rem] font-bold tracking-[0.2em] text-emerald-300 uppercase"
                    >
                        {{ issuedCode ? 'Ready to share' : 'Claim preview' }}
                    </p>
                    <h4 class="mt-2 text-xl font-bold">
                        {{
                            claimOutcome === 'account_funding'
                                ? 'Add this value to an Account'
                                : 'Claim this Pay Code'
                        }}
                    </h4>
                    <ol class="mt-3 grid gap-1.5 text-xs text-slate-300">
                        <li class="flex items-center gap-2">
                            <span class="font-bold text-emerald-300">1</span>
                            Open the secure claim page.
                        </li>
                        <li class="flex items-center gap-2">
                            <span class="font-bold text-emerald-300">2</span>
                            Complete the required checks.
                        </li>
                        <li class="flex items-center gap-2">
                            <span class="font-bold text-emerald-300">3</span>
                            Confirm the claim.
                        </li>
                    </ol>
                    <div class="mt-4 flex flex-wrap gap-1.5">
                        <span
                            class="inline-flex items-center gap-1 rounded-full bg-white/10 px-2 py-1 text-[0.65rem] font-semibold text-slate-200"
                        >
                            <ArrowLeftRight class="size-3" aria-hidden="true" />
                            {{ expiry }}
                        </span>
                        <span
                            v-for="label in instructionLabels.slice(0, 3)"
                            :key="label"
                            class="inline-flex items-center gap-1 rounded-full bg-white/10 px-2 py-1 text-[0.65rem] font-semibold text-slate-200"
                        >
                            <ShieldCheck class="size-3" aria-hidden="true" />
                            {{ label }}
                        </span>
                    </div>
                </div>
            </div>
        </article>
    </section>
</template>
