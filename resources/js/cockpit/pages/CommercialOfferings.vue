<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import {
    BadgeCheck,
    Banknote,
    Calculator,
    Check,
    Coins,
    GitBranch,
    Landmark,
    Percent,
    Send,
    ShieldCheck,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { store as storeOffering } from '@/routes/x-change/cockpit/commercial/offerings';
import { store as approveOffering } from '@/routes/x-change/cockpit/commercial/offerings/approvals';
import CockpitLayout from '../layouts/CockpitLayout.vue';

type CatalogItem = {
    reference: string;
    label: string;
    category: string;
    currency: string;
    unit_price_minor: number;
    deprecated?: boolean;
};

type WaterfallRule = {
    reference: string;
    sequence: number;
    line_type: 'deduction' | 'allocation' | 'residual';
    category: string;
    recipient_reference: string;
    fixed_amount_minor: number | null;
    basis_points: number | null;
    minimum_amount_minor: number | null;
    maximum_amount_minor: number | null;
    participant_role: string | null;
};

type CommercialOffering = {
    reference: string;
    version: number;
    effective_at: string;
    catalog: {
        reference: string;
        version: number;
        currency: string;
        items: CatalogItem[];
    };
    waterfall_policy: {
        reference: string;
        version: number;
        currency: string;
        rules: WaterfallRule[];
    };
    legal_trace: {
        jurisdiction: string;
        profile: string;
        decision: string;
    };
};

type PendingOffering = {
    id: number;
    reference: string;
    version: number;
    snapshot_hash: string;
    effective_at: string | null;
    submitted_at: string | null;
    maker: { type: string; id: string | number };
};

const props = defineProps<{
    cockpitHeaderReadModel?: Record<string, unknown>;
    commercialOffering: {
        profile: 'pay_code' | 'account_funding';
        active: CommercialOffering;
        source: 'package_default' | 'published';
        can_manage: boolean;
        can_approve: boolean;
        pending: PendingOffering[];
    };
}>();

const activeTab = ref<'price-list' | 'waterfall'>('price-list');
const approvalReference = ref('');
const approvingId = ref<number | null>(null);
const active = computed(() => props.commercialOffering.active);
const form = useForm({
    profile: props.commercialOffering.profile,
    effective_at: new Date().toISOString(),
    items: active.value.catalog.items.map((item) => ({
        reference: item.reference,
        unit_price: (item.unit_price_minor / 100).toFixed(2),
    })),
    rules: active.value.waterfall_policy.rules.map((rule) => ({
        reference: rule.reference,
        method:
            rule.line_type === 'residual'
                ? 'residual'
                : rule.basis_points !== null
                  ? 'basis_points'
                  : 'fixed',
        value:
            rule.basis_points ??
            (rule.fixed_amount_minor !== null
                ? (rule.fixed_amount_minor / 100).toFixed(2)
                : null),
        minimum_amount:
            rule.minimum_amount_minor !== null
                ? (rule.minimum_amount_minor / 100).toFixed(2)
                : null,
        maximum_amount:
            rule.maximum_amount_minor !== null
                ? (rule.maximum_amount_minor / 100).toFixed(2)
                : null,
        recipient_reference: rule.recipient_reference,
        participant_role: rule.participant_role,
    })),
});

const visibleItems = computed(() =>
    active.value.catalog.items.filter((item) => !item.deprecated),
);

function priceFor(reference: string) {
    return form.items.find((item) => item.reference === reference);
}

function ruleFor(reference: string) {
    return form.rules.find((rule) => rule.reference === reference);
}

function submit(): void {
    form.post(storeOffering(), { preserveScroll: true });
}

function approve(id: number): void {
    if (!approvalReference.value.trim()) {
        return;
    }

    approvingId.value = id;
    router.post(
        approveOffering(id),
        { authorization_reference: approvalReference.value },
        {
            preserveScroll: true,
            onFinish: () => {
                approvingId.value = null;
            },
            onSuccess: () => {
                approvalReference.value = '';
            },
        },
    );
}
</script>

<template>
    <Head title="Commercial Controls" />

    <CockpitLayout
        active-navigation="commercial"
        :cockpit-header-read-model="cockpitHeaderReadModel"
    >
        <main
            class="mx-auto max-w-7xl space-y-5"
            data-testid="commercial-controls-page"
        >
            <header
                class="overflow-hidden rounded-2xl bg-slate-950 text-white shadow-sm"
            >
                <div
                    class="grid gap-5 px-5 py-5 lg:grid-cols-[minmax(0,1fr)_auto] lg:items-center lg:px-6"
                >
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <p
                                class="text-xs font-semibold uppercase tracking-[0.18em] text-sky-300"
                            >
                                System-Owned Commercial Controls
                            </p>
                            <span
                                class="rounded-full border border-white/15 px-2.5 py-1 text-[0.68rem] font-semibold text-slate-200"
                            >
                                {{ active.catalog.currency }} · Version
                                {{ active.version }}
                            </span>
                        </div>
                        <h1
                            class="mt-2 text-2xl font-semibold tracking-tight sm:text-3xl"
                        >
                            Price List & Waterfall
                        </h1>
                        <p
                            class="mt-2 max-w-2xl text-sm leading-6 text-slate-300"
                        >
                            Price the instruction service, then define where an
                            accepted charge is attributed.
                        </p>
                    </div>
                    <div
                        class="flex items-center gap-3 rounded-xl border border-white/10 bg-white/5 px-4 py-3"
                    >
                        <ShieldCheck class="size-5 text-emerald-300" />
                        <div>
                            <p class="text-xs text-slate-400">
                                Publication Control
                            </p>
                            <p class="text-sm font-semibold">
                                Independent Maker–Checker
                            </p>
                        </div>
                    </div>
                </div>
            </header>

            <section class="grid gap-3 sm:grid-cols-3">
                <article
                    class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900"
                >
                    <p class="text-xs font-semibold text-slate-500">
                        Active Source
                    </p>
                    <p class="mt-1 text-lg font-semibold">
                        {{
                            commercialOffering.source === 'published'
                                ? 'Published Offering'
                                : 'Package Default'
                        }}
                    </p>
                </article>
                <article
                    class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900"
                >
                    <p class="text-xs font-semibold text-slate-500">
                        Priced Instructions
                    </p>
                    <p class="mt-1 text-lg font-semibold">
                        {{ visibleItems.length }}
                    </p>
                </article>
                <article
                    class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900"
                >
                    <p class="text-xs font-semibold text-slate-500">
                        Awaiting Approval
                    </p>
                    <p class="mt-1 text-lg font-semibold">
                        {{ commercialOffering.pending.length }}
                    </p>
                </article>
            </section>

            <section
                class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900"
            >
                <div
                    class="flex flex-col gap-3 border-b border-slate-200 px-5 py-4 sm:flex-row sm:items-center sm:justify-between dark:border-slate-800"
                >
                    <div>
                        <h2 class="text-base font-semibold">
                            Commercial Offering
                        </h2>
                        <p class="mt-0.5 text-sm text-slate-500">
                            One governed version binds prices, allocation
                            policy, attribution, and legal trace.
                        </p>
                    </div>
                    <div
                        class="inline-flex rounded-xl bg-slate-100 p-1 dark:bg-slate-800"
                    >
                        <button
                            type="button"
                            class="inline-flex h-9 items-center gap-2 rounded-lg px-3 text-sm font-semibold"
                            :class="
                                activeTab === 'price-list'
                                    ? 'bg-white text-slate-950 shadow-sm dark:bg-slate-950 dark:text-white'
                                    : 'text-slate-500'
                            "
                            @click="activeTab = 'price-list'"
                        >
                            <Coins class="size-4" /> Price List
                        </button>
                        <button
                            type="button"
                            class="inline-flex h-9 items-center gap-2 rounded-lg px-3 text-sm font-semibold"
                            :class="
                                activeTab === 'waterfall'
                                    ? 'bg-white text-slate-950 shadow-sm dark:bg-slate-950 dark:text-white'
                                    : 'text-slate-500'
                            "
                            @click="activeTab = 'waterfall'"
                        >
                            <GitBranch class="size-4" /> Waterfall
                        </button>
                    </div>
                </div>

                <div
                    v-if="activeTab === 'price-list'"
                    class="divide-y divide-slate-100 dark:divide-slate-800"
                >
                    <div
                        class="grid grid-cols-[minmax(0,1fr)_9rem] gap-4 bg-slate-50 px-5 py-2 text-xs font-semibold uppercase tracking-wide text-slate-500 dark:bg-slate-950/50"
                    >
                        <span>Instruction Service</span>
                        <span class="text-right">Charge</span>
                    </div>
                    <div
                        v-for="item in visibleItems"
                        :key="item.reference"
                        class="grid grid-cols-[minmax(0,1fr)_9rem] items-center gap-4 px-5 py-3"
                    >
                        <div class="min-w-0">
                            <p class="truncate text-sm font-semibold">
                                {{ item.label }}
                            </p>
                            <p class="truncate text-xs text-slate-500">
                                {{ item.reference }}
                            </p>
                        </div>
                        <label class="relative">
                            <span
                                class="absolute inset-y-0 left-3 grid place-items-center text-sm text-slate-500"
                                >₱</span
                            >
                            <input
                                v-if="priceFor(item.reference)"
                                v-model="priceFor(item.reference)!.unit_price"
                                type="number"
                                min="0"
                                step="0.01"
                                :disabled="!commercialOffering.can_manage"
                                class="h-10 w-full rounded-lg border border-slate-200 bg-white pl-7 pr-3 text-right text-sm font-semibold dark:border-slate-700 dark:bg-slate-950"
                            />
                        </label>
                    </div>
                </div>

                <div v-else class="space-y-3 p-5">
                    <article
                        v-for="rule in active.waterfall_policy.rules"
                        :key="rule.reference"
                        class="rounded-xl border border-slate-200 p-4 dark:border-slate-800"
                    >
                        <div
                            class="flex flex-wrap items-center justify-between gap-3"
                        >
                            <div class="flex items-center gap-3">
                                <span
                                    class="grid size-9 place-items-center rounded-lg bg-slate-100 dark:bg-slate-800"
                                >
                                    <Landmark
                                        v-if="rule.category === 'provider_cost'"
                                        class="size-4"
                                    />
                                    <Percent
                                        v-else-if="
                                            rule.category ===
                                            'partner_commission'
                                        "
                                        class="size-4"
                                    />
                                    <Banknote v-else class="size-4" />
                                </span>
                                <div>
                                    <p class="text-sm font-semibold">
                                        {{ rule.category.replaceAll('_', ' ') }}
                                    </p>
                                    <p class="text-xs text-slate-500">
                                        Step {{ rule.sequence }} ·
                                        {{ rule.reference }}
                                    </p>
                                </div>
                            </div>
                            <span
                                class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold capitalize dark:bg-slate-800"
                                >{{ rule.line_type }}</span
                            >
                        </div>
                        <div
                            v-if="ruleFor(rule.reference)"
                            class="mt-4 grid gap-3 md:grid-cols-3"
                        >
                            <label class="text-xs font-semibold text-slate-500">
                                Method
                                <select
                                    v-model="ruleFor(rule.reference)!.method"
                                    :disabled="
                                        rule.line_type === 'residual' ||
                                        !commercialOffering.can_manage
                                    "
                                    class="mt-1 h-10 w-full rounded-lg border border-slate-200 bg-white px-3 text-sm dark:border-slate-700 dark:bg-slate-950"
                                >
                                    <option value="fixed">Fixed PHP</option>
                                    <option value="basis_points">
                                        Basis Points
                                    </option>
                                    <option
                                        v-if="rule.line_type === 'residual'"
                                        value="residual"
                                    >
                                        Residual
                                    </option>
                                </select>
                            </label>
                            <label class="text-xs font-semibold text-slate-500">
                                Value
                                <input
                                    v-model="ruleFor(rule.reference)!.value"
                                    type="number"
                                    min="0"
                                    :disabled="
                                        rule.line_type === 'residual' ||
                                        !commercialOffering.can_manage
                                    "
                                    class="mt-1 h-10 w-full rounded-lg border border-slate-200 bg-white px-3 text-sm dark:border-slate-700 dark:bg-slate-950"
                                />
                            </label>
                            <label class="text-xs font-semibold text-slate-500">
                                Recipient
                                <input
                                    v-model="
                                        ruleFor(rule.reference)!
                                            .recipient_reference
                                    "
                                    type="text"
                                    :disabled="!commercialOffering.can_manage"
                                    class="mt-1 h-10 w-full rounded-lg border border-slate-200 bg-white px-3 text-sm dark:border-slate-700 dark:bg-slate-950"
                                />
                            </label>
                        </div>
                    </article>
                </div>

                <footer
                    v-if="commercialOffering.can_manage"
                    class="flex flex-col gap-3 border-t border-slate-200 bg-slate-50 px-5 py-4 sm:flex-row sm:items-center sm:justify-between dark:border-slate-800 dark:bg-slate-950/50"
                >
                    <div class="flex items-center gap-2 text-sm text-slate-500">
                        <Calculator class="size-4" />
                        Changes create a new version. Published history is never
                        edited.
                    </div>
                    <button
                        type="button"
                        :disabled="form.processing"
                        class="inline-flex h-10 items-center justify-center gap-2 rounded-lg bg-slate-950 px-4 text-sm font-semibold text-white disabled:opacity-50 dark:bg-white dark:text-slate-950"
                        @click="submit"
                    >
                        <Send class="size-4" />
                        {{
                            form.processing
                                ? 'Submitting'
                                : 'Submit New Version'
                        }}
                    </button>
                </footer>
            </section>

            <section
                v-if="commercialOffering.pending.length"
                class="rounded-2xl border border-amber-200 bg-amber-50/60 p-5 dark:border-amber-900 dark:bg-amber-950/20"
            >
                <div class="flex items-center gap-2">
                    <BadgeCheck
                        class="size-5 text-amber-700 dark:text-amber-300"
                    />
                    <h2 class="font-semibold">Independent Approval</h2>
                </div>
                <div class="mt-4 space-y-3">
                    <article
                        v-for="pending in commercialOffering.pending"
                        :key="pending.id"
                        class="flex flex-col gap-3 rounded-xl border border-amber-200 bg-white p-4 sm:flex-row sm:items-end dark:border-amber-900 dark:bg-slate-900"
                    >
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-semibold">
                                Version {{ pending.version }}
                            </p>
                            <p
                                class="mt-1 truncate font-mono text-xs text-slate-500"
                            >
                                {{ pending.snapshot_hash }}
                            </p>
                        </div>
                        <template v-if="commercialOffering.can_approve">
                            <label
                                class="min-w-0 flex-1 text-xs font-semibold text-slate-500"
                            >
                                Authorization Reference
                                <input
                                    v-model="approvalReference"
                                    type="text"
                                    class="mt-1 h-10 w-full rounded-lg border border-slate-200 bg-white px-3 text-sm dark:border-slate-700 dark:bg-slate-950"
                                    placeholder="Board or delegated control reference"
                                />
                            </label>
                            <button
                                type="button"
                                :disabled="
                                    approvingId !== null ||
                                    !approvalReference.trim()
                                "
                                class="inline-flex h-10 items-center justify-center gap-2 rounded-lg bg-emerald-700 px-4 text-sm font-semibold text-white disabled:opacity-50"
                                @click="approve(pending.id)"
                            >
                                <Check class="size-4" />
                                {{
                                    approvingId === pending.id
                                        ? 'Publishing'
                                        : 'Approve & Publish'
                                }}
                            </button>
                        </template>
                        <p
                            v-else
                            class="text-sm text-amber-800 dark:text-amber-200"
                        >
                            Waiting for a different authorized checker.
                        </p>
                    </article>
                </div>
            </section>
        </main>
    </CockpitLayout>
</template>
