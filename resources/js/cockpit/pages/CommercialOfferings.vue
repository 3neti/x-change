<script setup lang="ts">
import { Head, router, useForm } from "@inertiajs/vue3";
import {
  BadgeCheck,
  BarChart3,
  Banknote,
  Calculator,
  Check,
  Coins,
  GitBranch,
  HandCoins,
  Landmark,
  Percent,
  Scale,
  Send,
  ShieldCheck,
} from "lucide-vue-next";
import { computed, ref } from "vue";
import { store as storeOffering } from "@/routes/x-change/cockpit/commercial/offerings";
import { store as approveOffering } from "@/routes/x-change/cockpit/commercial/offerings/approvals";
import { store as activateOffering } from "@/routes/x-change/cockpit/commercial/offerings/activations";
import CockpitLayout from "../layouts/CockpitLayout.vue";

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
  line_type: "deduction" | "allocation" | "residual";
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

type PublishedOffering = {
  id: number;
  reference: string;
  version: number;
  snapshot_hash: string;
  effective_at: string | null;
  approved_at: string | null;
};

type CommercialControls = {
  schema: string;
  sales: {
    count: number;
    posted_count: number;
    reversed_count: number;
    total_charged_minor: number;
    currency: string;
  };
  allocation_totals: Array<{
    category: string;
    currency: string;
    amount_minor: number;
    allocation_count: number;
  }>;
  position_balances: Array<{
    purpose: string;
    category: string;
    currency: string;
    current_minor: number;
    lifetime_allocated_minor: number;
    settled_minor: number;
    remaining_minor: number;
    difference_minor: number;
    reconciled: boolean;
  }>;
  provider_costs: {
    settled_count: number;
    settled_minor: number;
    variance_minor: number;
    outstanding_minor: number;
    recent_batches: Array<{
      reference: string;
      provider: string;
      connection_reference: string;
      currency: string;
      expected_amount_minor: number;
      observed_amount_minor: number;
      variance_amount_minor: number;
      status: string;
      observed_at: string | null;
    }>;
  };
  commissions: {
    earned_minor: number;
    requested_minor: number;
    settled_minor: number;
    open_count: number;
    available_minor: number;
    recent_batches: Array<{
      reference: string;
      partner_reference: string;
      provider: string;
      connection_reference: string;
      destination_summary: string;
      amount_minor: number;
      currency: string;
      status: string;
      requested_at: string | null;
      settled_at: string | null;
    }>;
  };
  recent_sales: Array<{
    reference: string;
    buyer_reference: string;
    amount_minor: number;
    currency: string;
    status: string;
    accepted_at: string | null;
    allocations: Array<{
      category: string;
      recipient_reference: string;
      amount_minor: number;
      status: string;
    }>;
  }>;
  policy: {
    attribution: {
      reference: string;
      version: number;
      eligible_roles: string[];
      unattributed_commission_behavior: string;
    };
    legal_trace: {
      jurisdiction: string;
      legal_entity_reference: string;
      profile: string;
      profile_version: string;
      decision: string;
      decision_references: string[];
      invariant_references: string[];
      trace_references: string[];
    };
    commercial_terms_are_not_client_funds: boolean;
    commission_requires_attributed_participant: boolean;
    provider_cost_requires_authoritative_evidence: boolean;
  };
};

const props = defineProps<{
  cockpitHeaderReadModel?: Record<string, unknown>;
  commercialOffering: {
    profile: "pay_code" | "account_funding";
    active: CommercialOffering;
    source: "installation_baseline" | "maker_checker_revision" | "unavailable";
    can_manage: boolean;
    can_approve: boolean;
    can_reconcile_provider_costs?: boolean;
    can_request_commission_payouts?: boolean;
    can_approve_commission_payouts?: boolean;
    can_execute_commission_payouts?: boolean;
    pending: PendingOffering[];
    published: PublishedOffering[];
    governance: {
      state: string;
      message: string;
      changes_locked: boolean;
      roles: {
        maker_count: number;
        checker_count: number;
        separation_ready: boolean;
      };
    };
    controls: CommercialControls;
  };
}>();

const activeTab = ref<
  "price-list" | "waterfall" | "activity" | "operations" | "policy"
>("price-list");
const approvalReference = ref("");
const approvingId = ref<number | null>(null);
const activationReference = ref("");
const activatingId = ref<number | null>(null);
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
      rule.line_type === "residual"
        ? "residual"
        : rule.basis_points !== null
          ? "basis_points"
          : "fixed",
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

function money(
  minor: number,
  currency = active.value.catalog.currency,
): string {
  return new Intl.NumberFormat("en-PH", {
    style: "currency",
    currency,
  }).format(minor / 100);
}

function label(value: string): string {
  return value
    .replaceAll("_", " ")
    .replace(/\b\w/g, (character) => character.toUpperCase());
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
        approvalReference.value = "";
      },
    },
  );
}

function activate(id: number): void {
  if (!activationReference.value.trim()) {
    return;
  }

  activatingId.value = id;
  router.post(
    activateOffering(id),
    { activation_reference: activationReference.value },
    {
      preserveScroll: true,
      onFinish: () => {
        activatingId.value = null;
      },
      onSuccess: () => {
        activationReference.value = "";
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
            <h1 class="mt-2 text-2xl font-semibold tracking-tight sm:text-3xl">
              Price List & Waterfall
            </h1>
            <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-300">
              Price the instruction service, then define where an accepted
              charge is attributed.
            </p>
          </div>
          <div
            class="flex items-center gap-3 rounded-xl border border-white/10 bg-white/5 px-4 py-3"
          >
            <ShieldCheck class="size-5 text-emerald-300" />
            <div>
              <p class="text-xs text-slate-400">Publication Control</p>
              <p class="text-sm font-semibold">
                {{
                  commercialOffering.governance.roles.separation_ready
                    ? "Independent Maker–Checker"
                    : "Initial Pricing · Changes Locked"
                }}
              </p>
            </div>
          </div>
        </div>
      </header>

      <section class="grid gap-3 sm:grid-cols-3">
        <article
          class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900"
        >
          <p class="text-xs font-semibold text-slate-500">Active Source</p>
          <p class="mt-1 text-lg font-semibold">
            {{
              commercialOffering.source === "maker_checker_revision"
                ? "Governed Offering"
                : commercialOffering.source === "installation_baseline"
                  ? "Installation Baseline"
                  : "Unavailable"
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
          <p class="text-xs font-semibold text-slate-500">Awaiting Approval</p>
          <p class="mt-1 text-lg font-semibold">
            {{
              commercialOffering.pending.length +
              commercialOffering.published.length
            }}
          </p>
        </article>
      </section>

      <section
        class="rounded-2xl border border-sky-200 bg-sky-50/70 p-4 dark:border-sky-900 dark:bg-sky-950/20"
      >
        <div class="flex items-start gap-3">
          <ShieldCheck class="mt-0.5 size-5 shrink-0 text-sky-700" />
          <div>
            <p class="text-sm font-semibold">
              {{ label(commercialOffering.governance.state) }}
            </p>
            <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">
              {{ commercialOffering.governance.message }}
            </p>
          </div>
        </div>
      </section>

      <section
        class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900"
      >
        <div
          class="flex flex-col gap-3 border-b border-slate-200 px-5 py-4 sm:flex-row sm:items-center sm:justify-between dark:border-slate-800"
        >
          <div>
            <h2 class="text-base font-semibold">Commercial Offering</h2>
            <p class="mt-0.5 text-sm text-slate-500">
              One governed version binds prices, allocation policy, attribution,
              and legal trace.
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
            <button
              type="button"
              class="inline-flex h-9 items-center gap-2 rounded-lg px-3 text-sm font-semibold"
              :class="
                activeTab === 'activity'
                  ? 'bg-white text-slate-950 shadow-sm dark:bg-slate-950 dark:text-white'
                  : 'text-slate-500'
              "
              @click="activeTab = 'activity'"
            >
              <BarChart3 class="size-4" /> Activity
            </button>
            <button
              type="button"
              class="inline-flex h-9 items-center gap-2 rounded-lg px-3 text-sm font-semibold"
              :class="
                activeTab === 'operations'
                  ? 'bg-white text-slate-950 shadow-sm dark:bg-slate-950 dark:text-white'
                  : 'text-slate-500'
              "
              @click="activeTab = 'operations'"
            >
              <HandCoins class="size-4" /> Operations
            </button>
            <button
              type="button"
              class="inline-flex h-9 items-center gap-2 rounded-lg px-3 text-sm font-semibold"
              :class="
                activeTab === 'policy'
                  ? 'bg-white text-slate-950 shadow-sm dark:bg-slate-950 dark:text-white'
                  : 'text-slate-500'
              "
              @click="activeTab = 'policy'"
            >
              <Scale class="size-4" /> Policy
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

        <div v-else-if="activeTab === 'waterfall'" class="space-y-3 p-5">
          <article
            v-for="rule in active.waterfall_policy.rules"
            :key="rule.reference"
            class="rounded-xl border border-slate-200 p-4 dark:border-slate-800"
          >
            <div class="flex flex-wrap items-center justify-between gap-3">
              <div class="flex items-center gap-3">
                <span
                  class="grid size-9 place-items-center rounded-lg bg-slate-100 dark:bg-slate-800"
                >
                  <Landmark
                    v-if="rule.category === 'provider_cost'"
                    class="size-4"
                  />
                  <Percent
                    v-else-if="rule.category === 'partner_commission'"
                    class="size-4"
                  />
                  <Banknote v-else class="size-4" />
                </span>
                <div>
                  <p class="text-sm font-semibold">
                    {{ rule.category.replaceAll("_", " ") }}
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
              class="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-5"
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
                  <option value="basis_points">Basis Points</option>
                  <option v-if="rule.line_type === 'residual'" value="residual">
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
                  v-model="ruleFor(rule.reference)!.recipient_reference"
                  type="text"
                  :disabled="!commercialOffering.can_manage"
                  class="mt-1 h-10 w-full rounded-lg border border-slate-200 bg-white px-3 text-sm dark:border-slate-700 dark:bg-slate-950"
                />
              </label>
              <label class="text-xs font-semibold text-slate-500">
                Minimum PHP
                <input
                  v-model="ruleFor(rule.reference)!.minimum_amount"
                  type="number"
                  min="0"
                  step="0.01"
                  :disabled="
                    rule.line_type === 'residual' ||
                    !commercialOffering.can_manage
                  "
                  class="mt-1 h-10 w-full rounded-lg border border-slate-200 bg-white px-3 text-sm dark:border-slate-700 dark:bg-slate-950"
                />
              </label>
              <label class="text-xs font-semibold text-slate-500">
                Maximum PHP
                <input
                  v-model="ruleFor(rule.reference)!.maximum_amount"
                  type="number"
                  min="0"
                  step="0.01"
                  :disabled="
                    rule.line_type === 'residual' ||
                    !commercialOffering.can_manage
                  "
                  class="mt-1 h-10 w-full rounded-lg border border-slate-200 bg-white px-3 text-sm dark:border-slate-700 dark:bg-slate-950"
                />
              </label>
              <label
                v-if="rule.category === 'partner_commission'"
                class="text-xs font-semibold text-slate-500 xl:col-span-5"
              >
                Attributed Participant Role
                <select
                  v-model="ruleFor(rule.reference)!.participant_role"
                  :disabled="!commercialOffering.can_manage"
                  class="mt-1 h-10 w-full rounded-lg border border-slate-200 bg-white px-3 text-sm dark:border-slate-700 dark:bg-slate-950"
                >
                  <option :value="null">No participant required</option>
                  <option
                    v-for="role in commercialOffering.controls.policy
                      .attribution.eligible_roles"
                    :key="role"
                    :value="role"
                  >
                    {{ label(role) }}
                  </option>
                </select>
              </label>
            </div>
          </article>
        </div>

        <div v-else-if="activeTab === 'activity'" class="space-y-5 p-5">
          <section class="space-y-3" data-testid="commercial-position-balances">
            <div>
              <h3 class="text-sm font-semibold">Commercial Positions</h3>
              <p class="mt-0.5 text-xs text-slate-500">
                Current system balances, separated from lifetime allocation
                activity.
              </p>
            </div>
            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
              <article
                v-for="position in commercialOffering.controls
                  .position_balances"
                :key="`${position.purpose}-${position.currency}`"
                class="rounded-xl border border-slate-200 p-4 dark:border-slate-800"
              >
                <div class="flex items-start justify-between gap-3">
                  <div>
                    <p class="text-xs font-semibold text-slate-500">
                      {{ label(position.purpose) }}
                    </p>
                    <p class="mt-1 text-lg font-semibold">
                      {{ money(position.current_minor, position.currency) }}
                    </p>
                  </div>
                  <span
                    class="inline-flex items-center gap-1 rounded-full px-2 py-1 text-[0.68rem] font-semibold"
                    :class="
                      position.reconciled
                        ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300'
                        : 'bg-amber-50 text-amber-700 dark:bg-amber-950/40 dark:text-amber-300'
                    "
                  >
                    <Check v-if="position.reconciled" class="size-3" />
                    {{ position.reconciled ? "Reconciled" : "Review" }}
                  </span>
                </div>
                <dl class="mt-3 space-y-1.5 text-xs">
                  <div class="flex items-center justify-between gap-3">
                    <dt class="text-slate-500">Lifetime Allocated</dt>
                    <dd class="font-medium">
                      {{
                        money(
                          position.lifetime_allocated_minor,
                          position.currency,
                        )
                      }}
                    </dd>
                  </div>
                  <div class="flex items-center justify-between gap-3">
                    <dt class="text-slate-500">Settled Or Paid</dt>
                    <dd class="font-medium">
                      {{ money(position.settled_minor, position.currency) }}
                    </dd>
                  </div>
                  <div
                    class="flex items-center justify-between gap-3 border-t border-slate-100 pt-1.5 dark:border-slate-800"
                  >
                    <dt class="font-medium">Remaining</dt>
                    <dd class="font-semibold">
                      {{ money(position.remaining_minor, position.currency) }}
                    </dd>
                  </div>
                </dl>
              </article>
            </div>
          </section>

          <section class="space-y-3">
            <div>
              <h3 class="text-sm font-semibold">Allocation History</h3>
              <p class="mt-0.5 text-xs text-slate-500">
                Lifetime posted allocations. Reversed sales are excluded.
              </p>
            </div>
            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
              <article
                v-for="allocation in commercialOffering.controls
                  .allocation_totals"
                :key="allocation.category"
                class="rounded-xl border border-slate-200 p-4 dark:border-slate-800"
              >
                <p class="text-xs font-semibold text-slate-500">
                  {{ label(allocation.category) }}
                </p>
                <p class="mt-1 text-lg font-semibold">
                  {{ money(allocation.amount_minor, allocation.currency) }}
                </p>
                <p class="mt-1 text-xs text-slate-500">
                  {{ allocation.allocation_count }} posted allocations
                </p>
              </article>
            </div>
          </section>

          <div class="grid gap-3 lg:grid-cols-3">
            <article class="rounded-xl bg-slate-950 p-4 text-white">
              <p class="text-xs font-semibold text-slate-400">
                Accepted Commercial Sales
              </p>
              <p class="mt-1 text-xl font-semibold">
                {{
                  money(commercialOffering.controls.sales.total_charged_minor)
                }}
              </p>
              <p class="mt-1 text-xs text-slate-400">
                {{ commercialOffering.controls.sales.posted_count }}
                posted ·
                {{ commercialOffering.controls.sales.reversed_count }}
                reversed
              </p>
            </article>
            <article
              class="rounded-xl border border-slate-200 p-4 dark:border-slate-800"
            >
              <p class="text-xs font-semibold text-slate-500">
                Provider Costs Settled
              </p>
              <p class="mt-1 text-xl font-semibold">
                {{
                  money(
                    commercialOffering.controls.provider_costs.settled_minor,
                  )
                }}
              </p>
              <p class="mt-1 text-xs text-slate-500">
                Variance
                {{
                  money(
                    commercialOffering.controls.provider_costs.variance_minor,
                  )
                }}
              </p>
            </article>
            <article
              class="rounded-xl border border-slate-200 p-4 dark:border-slate-800"
            >
              <p class="text-xs font-semibold text-slate-500">
                Partner Commissions
              </p>
              <p class="mt-1 text-xl font-semibold">
                {{
                  money(commercialOffering.controls.commissions.earned_minor)
                }}
              </p>
              <p class="mt-1 text-xs text-slate-500">
                {{
                  money(commercialOffering.controls.commissions.settled_minor)
                }}
                settled ·
                {{ commercialOffering.controls.commissions.open_count }}
                open
              </p>
            </article>
          </div>

          <div
            class="overflow-hidden rounded-xl border border-slate-200 dark:border-slate-800"
          >
            <div
              class="border-b border-slate-200 px-4 py-3 dark:border-slate-800"
            >
              <h3 class="text-sm font-semibold">Recent Commercial Sales</h3>
            </div>
            <div
              v-if="commercialOffering.controls.recent_sales.length"
              class="divide-y divide-slate-100 dark:divide-slate-800"
            >
              <article
                v-for="sale in commercialOffering.controls.recent_sales"
                :key="sale.reference"
                class="grid gap-3 px-4 py-3 md:grid-cols-[minmax(0,1fr)_auto] md:items-center"
              >
                <div class="min-w-0">
                  <p class="truncate text-sm font-semibold">
                    {{ sale.reference }}
                  </p>
                  <div class="mt-1 flex flex-wrap gap-1.5">
                    <span
                      v-for="allocation in sale.allocations"
                      :key="`${sale.reference}-${allocation.category}`"
                      class="rounded-full bg-slate-100 px-2 py-0.5 text-[0.68rem] font-medium dark:bg-slate-800"
                    >
                      {{ label(allocation.category) }} ·
                      {{ money(allocation.amount_minor, sale.currency) }}
                    </span>
                  </div>
                </div>
                <div class="text-left md:text-right">
                  <p class="text-sm font-semibold">
                    {{ money(sale.amount_minor, sale.currency) }}
                  </p>
                  <p class="text-xs capitalize text-slate-500">
                    {{ sale.status }}
                  </p>
                </div>
              </article>
            </div>
            <p v-else class="px-4 py-8 text-center text-sm text-slate-500">
              No accepted Commercial Sales yet.
            </p>
          </div>
        </div>

        <div v-else-if="activeTab === 'operations'" class="space-y-5 p-5">
          <div class="grid gap-3 md:grid-cols-2">
            <article
              class="rounded-xl border border-slate-200 p-4 dark:border-slate-800"
            >
              <p class="text-xs font-semibold text-slate-500">
                Provider Cost Payable
              </p>
              <p class="mt-1 text-xl font-semibold">
                {{ money(commercialOffering.controls.provider_costs.outstanding_minor) }}
              </p>
              <p class="mt-1 text-xs text-slate-500">
                Settles only against exact provider statement or invoice evidence.
              </p>
            </article>
            <article
              class="rounded-xl border border-slate-200 p-4 dark:border-slate-800"
            >
              <p class="text-xs font-semibold text-slate-500">
                Commission Available
              </p>
              <p class="mt-1 text-xl font-semibold">
                {{ money(commercialOffering.controls.commissions.available_minor) }}
              </p>
              <p class="mt-1 text-xs text-slate-500">
                Earned allocations not yet included in a controlled payout.
              </p>
            </article>
          </div>

          <section class="space-y-3">
            <div class="flex items-center justify-between gap-3">
              <div>
                <h3 class="text-sm font-semibold">Provider Cost Evidence</h3>
                <p class="mt-0.5 text-xs text-slate-500">
                  Exact batches settle; differences remain under review.
                </p>
              </div>
              <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold dark:bg-slate-800">
                {{ commercialOffering.can_reconcile_provider_costs ? "Operator Enabled" : "Read Only" }}
              </span>
            </div>
            <div class="overflow-hidden rounded-xl border border-slate-200 dark:border-slate-800">
              <div
                v-for="batch in commercialOffering.controls.provider_costs.recent_batches"
                :key="batch.reference"
                class="grid gap-2 border-b border-slate-100 px-4 py-3 last:border-b-0 sm:grid-cols-[minmax(0,1fr)_auto_auto] sm:items-center dark:border-slate-800"
              >
                <div class="min-w-0">
                  <p class="truncate text-sm font-semibold">{{ batch.reference }}</p>
                  <p class="text-xs text-slate-500">{{ label(batch.provider) }} · {{ batch.connection_reference }}</p>
                </div>
                <p class="text-sm font-semibold">{{ money(batch.observed_amount_minor, batch.currency) }}</p>
                <span class="w-fit rounded-full bg-slate-100 px-2 py-1 text-xs font-semibold dark:bg-slate-800">{{ label(batch.status) }}</span>
              </div>
              <p v-if="!commercialOffering.controls.provider_costs.recent_batches.length" class="px-4 py-8 text-center text-sm text-slate-500">
                No provider cost evidence recorded.
              </p>
            </div>
          </section>

          <section class="space-y-3">
            <div class="flex items-center justify-between gap-3">
              <div>
                <h3 class="text-sm font-semibold">Commission Payouts</h3>
                <p class="mt-0.5 text-xs text-slate-500">
                  Aggregated by partner and period with independent approval.
                </p>
              </div>
              <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold dark:bg-slate-800">
                {{ commercialOffering.can_execute_commission_payouts ? "Execution Enabled" : "Controlled" }}
              </span>
            </div>
            <div class="overflow-hidden rounded-xl border border-slate-200 dark:border-slate-800">
              <div
                v-for="batch in commercialOffering.controls.commissions.recent_batches"
                :key="batch.reference"
                class="grid gap-2 border-b border-slate-100 px-4 py-3 last:border-b-0 sm:grid-cols-[minmax(0,1fr)_auto_auto] sm:items-center dark:border-slate-800"
              >
                <div class="min-w-0">
                  <p class="truncate text-sm font-semibold">{{ batch.partner_reference }}</p>
                  <p class="text-xs text-slate-500">{{ batch.destination_summary }} · {{ batch.reference }}</p>
                </div>
                <p class="text-sm font-semibold">{{ money(batch.amount_minor, batch.currency) }}</p>
                <span class="w-fit rounded-full bg-slate-100 px-2 py-1 text-xs font-semibold dark:bg-slate-800">{{ label(batch.status) }}</span>
              </div>
              <p v-if="!commercialOffering.controls.commissions.recent_batches.length" class="px-4 py-8 text-center text-sm text-slate-500">
                No commission payout requested.
              </p>
            </div>
          </section>
        </div>

        <div v-else class="grid gap-4 p-5 lg:grid-cols-2">
          <article
            class="rounded-xl border border-slate-200 p-4 dark:border-slate-800"
          >
            <div class="flex items-center gap-2">
              <Scale class="size-4 text-sky-600" />
              <h3 class="text-sm font-semibold">Legal Trace</h3>
            </div>
            <dl class="mt-4 space-y-3 text-sm">
              <div class="flex justify-between gap-4">
                <dt class="text-slate-500">Jurisdiction</dt>
                <dd class="font-semibold">
                  {{
                    commercialOffering.controls.policy.legal_trace.jurisdiction
                  }}
                </dd>
              </div>
              <div class="flex justify-between gap-4">
                <dt class="text-slate-500">Profile</dt>
                <dd class="text-right font-semibold">
                  {{ commercialOffering.controls.policy.legal_trace.profile }}
                </dd>
              </div>
              <div class="flex justify-between gap-4">
                <dt class="text-slate-500">Decision</dt>
                <dd class="text-right font-semibold">
                  {{
                    label(
                      commercialOffering.controls.policy.legal_trace.decision,
                    )
                  }}
                </dd>
              </div>
            </dl>
          </article>
          <article
            class="rounded-xl border border-slate-200 p-4 dark:border-slate-800"
          >
            <div class="flex items-center gap-2">
              <ShieldCheck class="size-4 text-emerald-600" />
              <h3 class="text-sm font-semibold">Control Invariants</h3>
            </div>
            <ul class="mt-4 space-y-3 text-sm">
              <li class="flex gap-2">
                <Check class="mt-0.5 size-4 shrink-0 text-emerald-600" />
                Commercial allocations are not Client Funds.
              </li>
              <li class="flex gap-2">
                <Check class="mt-0.5 size-4 shrink-0 text-emerald-600" />
                Commission requires an attributed participant.
              </li>
              <li class="flex gap-2">
                <Check class="mt-0.5 size-4 shrink-0 text-emerald-600" />
                Provider cost settlement requires authoritative evidence.
              </li>
            </ul>
          </article>
        </div>

        <footer
          v-if="
            commercialOffering.can_manage &&
            ['price-list', 'waterfall'].includes(activeTab)
          "
          class="flex flex-col gap-3 border-t border-slate-200 bg-slate-50 px-5 py-4 sm:flex-row sm:items-center sm:justify-between dark:border-slate-800 dark:bg-slate-950/50"
        >
          <div class="flex items-center gap-2 text-sm text-slate-500">
            <Calculator class="size-4" />
            Changes create a new version. Published history is never edited.
          </div>
          <button
            type="button"
            :disabled="form.processing"
            class="inline-flex h-10 items-center justify-center gap-2 rounded-lg bg-slate-950 px-4 text-sm font-semibold text-white disabled:opacity-50 dark:bg-white dark:text-slate-950"
            @click="submit"
          >
            <Send class="size-4" />
            {{ form.processing ? "Submitting" : "Submit New Version" }}
          </button>
        </footer>
      </section>

      <section
        v-if="commercialOffering.pending.length"
        class="rounded-2xl border border-amber-200 bg-amber-50/60 p-5 dark:border-amber-900 dark:bg-amber-950/20"
      >
        <div class="flex items-center gap-2">
          <BadgeCheck class="size-5 text-amber-700 dark:text-amber-300" />
          <h2 class="font-semibold">Independent Approval</h2>
        </div>
        <div class="mt-4 space-y-3">
          <article
            v-for="pending in commercialOffering.pending"
            :key="pending.id"
            class="flex flex-col gap-3 rounded-xl border border-amber-200 bg-white p-4 sm:flex-row sm:items-end dark:border-amber-900 dark:bg-slate-900"
          >
            <div class="min-w-0 flex-1">
              <p class="text-sm font-semibold">Version {{ pending.version }}</p>
              <p class="mt-1 truncate font-mono text-xs text-slate-500">
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
                :disabled="approvingId !== null || !approvalReference.trim()"
                class="inline-flex h-10 items-center justify-center gap-2 rounded-lg bg-emerald-700 px-4 text-sm font-semibold text-white disabled:opacity-50"
                @click="approve(pending.id)"
              >
                <Check class="size-4" />
                {{
                  approvingId === pending.id
                    ? "Publishing"
                    : "Approve & Publish"
                }}
              </button>
            </template>
            <p v-else class="text-sm text-amber-800 dark:text-amber-200">
              Waiting for a different authorized checker.
            </p>
          </article>
        </div>
      </section>

      <section
        v-if="commercialOffering.published.length"
        class="rounded-2xl border border-sky-200 bg-sky-50/60 p-5 dark:border-sky-900 dark:bg-sky-950/20"
      >
        <div class="flex items-center gap-2">
          <ShieldCheck class="size-5 text-sky-700 dark:text-sky-300" />
          <h2 class="font-semibold">Ready For Activation</h2>
        </div>
        <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">
          Publication records approval. Activation is the separate control that
          changes pricing for new Pay Codes.
        </p>
        <div class="mt-4 space-y-3">
          <article
            v-for="published in commercialOffering.published"
            :key="published.id"
            class="flex flex-col gap-3 rounded-xl border border-sky-200 bg-white p-4 sm:flex-row sm:items-end dark:border-sky-900 dark:bg-slate-900"
          >
            <div class="min-w-0 flex-1">
              <p class="text-sm font-semibold">
                Version {{ published.version }}
              </p>
              <p class="mt-1 truncate font-mono text-xs text-slate-500">
                {{ published.snapshot_hash }}
              </p>
            </div>
            <template v-if="commercialOffering.can_approve">
              <label
                class="min-w-0 flex-1 text-xs font-semibold text-slate-500"
              >
                Activation Reference
                <input
                  v-model="activationReference"
                  type="text"
                  class="mt-1 h-10 w-full rounded-lg border border-slate-200 bg-white px-3 text-sm dark:border-slate-700 dark:bg-slate-950"
                  placeholder="Deployment or delegated control reference"
                />
              </label>
              <button
                type="button"
                :disabled="activatingId !== null || !activationReference.trim()"
                class="inline-flex h-10 items-center justify-center gap-2 rounded-lg bg-sky-700 px-4 text-sm font-semibold text-white disabled:opacity-50"
                @click="activate(published.id)"
              >
                <Check class="size-4" />
                {{
                  activatingId === published.id
                    ? "Activating"
                    : "Activate Version"
                }}
              </button>
            </template>
          </article>
        </div>
      </section>
    </main>
  </CockpitLayout>
</template>
