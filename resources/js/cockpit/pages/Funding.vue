<script setup lang="ts">
import { computed } from 'vue';
import CockpitLayout from '../layouts/CockpitLayout.vue';
import type { CockpitFundingPageProps } from '../types';

const props = defineProps<CockpitFundingPageProps>();

const summaryCards = computed(() => [
    {
        key: 'awaiting',
        label: 'Awaiting Funds',
        value: String(props.funding_read_model.summary.awaiting_funds),
        helper: 'Open Funding Intents waiting for authoritative settlement.',
        tone: 'text-sky-700 dark:text-sky-300',
    },
    {
        key: 'settled',
        label: 'Settled Funding',
        value: props.funding_read_model.summary.settled_funding,
        helper: 'Verified net funding posted to this Account.',
        tone: 'text-emerald-700 dark:text-emerald-300',
    },
    {
        key: 'suspense',
        label: 'Open Suspense',
        value: String(props.funding_read_model.summary.open_suspense),
        helper: 'Mismatched or ambiguous evidence requiring review.',
        tone: 'text-amber-700 dark:text-amber-300',
    },
    {
        key: 'recovery',
        label: 'Recovery Outstanding',
        value: props.funding_read_model.summary.recovery_outstanding,
        helper: 'Reversed funding still held against future usable balance.',
        tone: 'text-rose-700 dark:text-rose-300',
    },
]);

const safeguards = [
    'A Funding Intent must exist before money can be recognized.',
    'A webhook stores evidence and wakes verification; it never credits an Account.',
    'Settlement is re-queried from the provider before exact net funding is posted.',
    'Mismatches enter suspense; operators cannot type an arbitrary credit amount.',
    'Reconciliation and compensation require separate maker and checker identities.',
];

function displayLabel(value: string): string {
    return value
        .replaceAll('_', ' ')
        .replace(/\b\w/g, (letter) => letter.toUpperCase());
}

function displayTime(value?: string | null): string {
    if (!value) {
        return '—';
    }

    const date = new Date(value);

    return Number.isNaN(date.getTime())
        ? value
        : new Intl.DateTimeFormat('en-PH', {
              dateStyle: 'medium',
              timeStyle: 'short',
          }).format(date);
}
</script>

<template>
    <CockpitLayout
        active-navigation="funding"
        :cockpit-header-read-model="cockpit_header_read_model"
    >
        <div
            class="mx-auto max-w-7xl space-y-5"
            data-testid="cockpit-funding-page"
        >
            <section
                class="overflow-hidden rounded-2xl border border-slate-200 bg-slate-950 text-white shadow-sm dark:border-slate-800"
            >
                <div
                    class="grid gap-6 px-5 py-5 lg:grid-cols-[minmax(0,1fr)_22rem] lg:px-6"
                >
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <p
                                class="text-xs font-semibold tracking-[0.22em] text-sky-300 uppercase"
                            >
                                Funding control
                            </p>
                            <span
                                class="rounded-full border border-white/15 bg-white/10 px-2.5 py-1 text-[0.65rem] font-semibold tracking-wide text-slate-200 uppercase"
                            >
                                provider verified
                            </span>
                        </div>
                        <h1
                            class="mt-2 text-2xl font-semibold tracking-tight sm:text-3xl"
                        >
                            Account Funding
                        </h1>
                        <p
                            class="mt-2 max-w-3xl text-sm leading-6 text-slate-300"
                        >
                            Request funding instructions and monitor
                            authoritative settlement. There is no manual “add
                            funds” control: only verified bank or EMI evidence
                            can increase the Account balance.
                        </p>
                    </div>

                    <div
                        class="rounded-xl border border-emerald-400/20 bg-emerald-400/10 p-4"
                    >
                        <p
                            class="text-xs font-semibold tracking-[0.16em] text-emerald-300 uppercase"
                        >
                            Control posture
                        </p>
                        <p class="mt-1 text-sm font-semibold text-white">
                            Webhook evidence ≠ Account credit
                        </p>
                        <p class="mt-1 text-xs leading-5 text-emerald-100/80">
                            The provider is queried independently before
                            Inventory recognition and Account posting occur
                            atomically.
                        </p>
                    </div>
                </div>
            </section>

            <section
                class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4"
                aria-label="Funding summary"
            >
                <article
                    v-for="card in summaryCards"
                    :key="card.key"
                    class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900"
                >
                    <p
                        class="text-xs font-semibold tracking-[0.14em] text-slate-500 uppercase dark:text-slate-400"
                    >
                        {{ card.label }}
                    </p>
                    <p
                        :class="[
                            'mt-2 text-2xl font-semibold tracking-tight',
                            card.tone,
                        ]"
                    >
                        {{ card.value }}
                    </p>
                    <p
                        class="mt-1 text-xs leading-5 text-slate-500 dark:text-slate-400"
                    >
                        {{ card.helper }}
                    </p>
                </article>
            </section>

            <section
                class="grid gap-5 xl:grid-cols-[minmax(0,1.35fr)_minmax(20rem,0.65fr)]"
            >
                <article
                    class="rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900"
                >
                    <div
                        class="flex flex-wrap items-start justify-between gap-3 border-b border-slate-200 px-5 py-4 dark:border-slate-800"
                    >
                        <div>
                            <p
                                class="text-xs font-semibold tracking-[0.16em] text-slate-500 uppercase"
                            >
                                Funding rails
                            </p>
                            <h2 class="mt-1 text-lg font-semibold">
                                Available providers
                            </h2>
                        </div>
                        <span
                            class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-600 dark:bg-slate-800 dark:text-slate-300"
                        >
                            {{ funding_read_model.providers.length }} connected
                        </span>
                    </div>
                    <div
                        v-if="funding_read_model.providers.length"
                        class="grid gap-3 p-4 sm:grid-cols-2"
                    >
                        <div
                            v-for="provider in funding_read_model.providers"
                            :key="provider.code"
                            class="rounded-lg border border-slate-200 p-4 dark:border-slate-700"
                        >
                            <div
                                class="flex items-center justify-between gap-3"
                            >
                                <p class="font-semibold">
                                    {{ provider.label }}
                                </p>
                                <span
                                    class="rounded-full bg-emerald-50 px-2 py-1 text-[0.65rem] font-semibold tracking-wide text-emerald-700 uppercase dark:bg-emerald-950/60 dark:text-emerald-300"
                                >
                                    {{ provider.status }}
                                </span>
                            </div>
                            <p
                                class="mt-2 text-xs leading-5 text-slate-500 dark:text-slate-400"
                            >
                                Signed intake plus independent authoritative
                                status verification.
                            </p>
                        </div>
                    </div>
                    <p v-else class="p-5 text-sm text-slate-500">
                        No funding provider is enabled for this environment.
                    </p>
                </article>

                <article
                    class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900"
                >
                    <p
                        class="text-xs font-semibold tracking-[0.16em] text-slate-500 uppercase"
                    >
                        Safeguards
                    </p>
                    <h2 class="mt-1 text-lg font-semibold">
                        Non-negotiable controls
                    </h2>
                    <ol class="mt-4 space-y-3">
                        <li
                            v-for="(safeguard, index) in safeguards"
                            :key="safeguard"
                            class="flex gap-3 text-sm leading-5 text-slate-600 dark:text-slate-300"
                        >
                            <span
                                class="flex size-6 shrink-0 items-center justify-center rounded-full bg-slate-950 text-xs font-semibold text-white dark:bg-sky-300 dark:text-slate-950"
                            >
                                {{ index + 1 }}
                            </span>
                            <span>{{ safeguard }}</span>
                        </li>
                    </ol>
                </article>
            </section>

            <section
                class="rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900"
            >
                <div
                    class="flex flex-wrap items-start justify-between gap-3 border-b border-slate-200 px-5 py-4 dark:border-slate-800"
                >
                    <div>
                        <p
                            class="text-xs font-semibold tracking-[0.16em] text-slate-500 uppercase"
                        >
                            Funding activity
                        </p>
                        <h2 class="mt-1 text-lg font-semibold">
                            Recent Funding Intents
                        </h2>
                    </div>
                    <span class="text-xs text-slate-500"
                        >Sanitized operational summary</span
                    >
                </div>
                <div
                    v-if="funding_read_model.intents.length"
                    class="overflow-x-auto"
                >
                    <table class="w-full min-w-[44rem] text-left text-sm">
                        <thead
                            class="bg-slate-50 text-xs tracking-wide text-slate-500 uppercase dark:bg-slate-950/40 dark:text-slate-400"
                        >
                            <tr>
                                <th class="px-5 py-3 font-semibold">
                                    Reference
                                </th>
                                <th class="px-5 py-3 font-semibold">
                                    Provider
                                </th>
                                <th class="px-5 py-3 font-semibold">Amount</th>
                                <th class="px-5 py-3 font-semibold">Status</th>
                                <th class="px-5 py-3 font-semibold">Created</th>
                            </tr>
                        </thead>
                        <tbody
                            class="divide-y divide-slate-100 dark:divide-slate-800"
                        >
                            <tr
                                v-for="intent in funding_read_model.intents"
                                :key="intent.reference"
                            >
                                <td
                                    class="px-5 py-3 font-mono text-xs text-slate-700 dark:text-slate-300"
                                >
                                    {{ intent.reference }}
                                </td>
                                <td class="px-5 py-3 font-medium">
                                    {{ displayLabel(intent.provider) }}
                                </td>
                                <td class="px-5 py-3 font-semibold">
                                    {{ intent.amount }}
                                </td>
                                <td class="px-5 py-3">
                                    <span
                                        class="rounded-full bg-slate-100 px-2 py-1 text-xs font-medium text-slate-700 dark:bg-slate-800 dark:text-slate-200"
                                    >
                                        {{ displayLabel(intent.status) }}
                                    </span>
                                </td>
                                <td
                                    class="px-5 py-3 text-slate-500 dark:text-slate-400"
                                >
                                    {{ displayTime(intent.created_at) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div v-else class="px-5 py-8 text-center">
                    <p
                        class="text-sm font-medium text-slate-700 dark:text-slate-200"
                    >
                        No Funding Intents yet
                    </p>
                    <p class="mt-1 text-xs text-slate-500">
                        A request will appear here before any incoming funds can
                        be recognized.
                    </p>
                </div>
            </section>

            <section class="grid gap-5 xl:grid-cols-2">
                <article
                    class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900"
                >
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <p
                                class="text-xs font-semibold tracking-[0.16em] text-amber-700 uppercase dark:text-amber-300"
                            >
                                Exception control
                            </p>
                            <h2 class="mt-1 text-lg font-semibold">Suspense</h2>
                        </div>
                        <span class="text-sm font-semibold">{{
                            funding_read_model.suspense_cases.length
                        }}</span>
                    </div>
                    <div
                        v-if="funding_read_model.suspense_cases.length"
                        class="mt-4 space-y-3"
                    >
                        <div
                            v-for="item in funding_read_model.suspense_cases"
                            :key="item.reference"
                            class="rounded-lg border border-amber-200 bg-amber-50/60 p-3 dark:border-amber-900 dark:bg-amber-950/20"
                        >
                            <div
                                class="flex flex-wrap items-center justify-between gap-2"
                            >
                                <p class="font-mono text-xs">
                                    {{ item.reference }}
                                </p>
                                <span
                                    class="text-xs font-semibold text-amber-800 dark:text-amber-300"
                                    >{{ displayLabel(item.reason) }}</span
                                >
                            </div>
                            <p
                                class="mt-2 text-xs text-slate-600 dark:text-slate-400"
                            >
                                {{
                                    item.pending_approval
                                        ? 'Maker request pending independent approval.'
                                        : 'Awaiting a controlled reconciliation request.'
                                }}
                            </p>
                        </div>
                    </div>
                    <p v-else class="mt-4 text-sm text-slate-500">
                        No open funding exceptions.
                    </p>
                </article>

                <article
                    class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900"
                >
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <p
                                class="text-xs font-semibold tracking-[0.16em] text-rose-700 uppercase dark:text-rose-300"
                            >
                                Issuance protection
                            </p>
                            <h2 class="mt-1 text-lg font-semibold">
                                Recovery holds
                            </h2>
                        </div>
                        <span class="text-sm font-semibold">{{
                            funding_read_model.recovery_holds.length
                        }}</span>
                    </div>
                    <div
                        v-if="funding_read_model.recovery_holds.length"
                        class="mt-4 space-y-3"
                    >
                        <div
                            v-for="hold in funding_read_model.recovery_holds"
                            :key="hold.reference"
                            class="rounded-lg border border-rose-200 bg-rose-50/60 p-3 dark:border-rose-900 dark:bg-rose-950/20"
                        >
                            <div
                                class="flex flex-wrap items-center justify-between gap-2"
                            >
                                <p class="font-mono text-xs">
                                    {{ hold.reference }}
                                </p>
                                <p
                                    class="font-semibold text-rose-800 dark:text-rose-300"
                                >
                                    {{ hold.outstanding }}
                                </p>
                            </div>
                            <p
                                class="mt-2 text-xs text-slate-600 dark:text-slate-400"
                            >
                                Future verified funding repays this hold before
                                becoming usable.
                            </p>
                        </div>
                    </div>
                    <p v-else class="mt-4 text-sm text-slate-500">
                        No active funding recovery holds.
                    </p>
                </article>
            </section>

            <section
                class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900"
            >
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p
                            class="text-xs font-semibold tracking-[0.16em] text-slate-500 uppercase"
                        >
                            3neti/wallet grammar
                        </p>
                        <h2 class="mt-1 text-lg font-semibold">
                            Treasury Inventory
                        </h2>
                    </div>
                    <span
                        class="rounded-full bg-slate-100 px-2.5 py-1 text-xs text-slate-600 dark:bg-slate-800 dark:text-slate-300"
                        >read-only positions</span
                    >
                </div>
                <div
                    v-if="funding_read_model.treasury_positions.length"
                    class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3"
                >
                    <div
                        v-for="position in funding_read_model.treasury_positions"
                        :key="`${position.provider}-${position.currency}`"
                        class="rounded-lg border border-slate-200 p-4 dark:border-slate-700"
                    >
                        <p
                            class="text-xs font-semibold tracking-wide text-slate-500 uppercase"
                        >
                            {{ displayLabel(position.provider) }}
                        </p>
                        <p class="mt-1 text-xl font-semibold">
                            {{ position.recognized }}
                        </p>
                        <p class="mt-1 text-xs text-slate-500">
                            {{ displayLabel(position.status) }} · durable
                            Inventory facts
                        </p>
                    </div>
                </div>
                <p v-else class="mt-4 text-sm text-slate-500">
                    No Treasury Inventory has been recognized from verified
                    funding yet.
                </p>
            </section>
        </div>
    </CockpitLayout>
</template>
