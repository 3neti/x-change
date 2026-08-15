<script setup lang="ts">
import { Head, router } from "@inertiajs/vue3";
import {
  Check,
  HandCoins,
  Landmark,
  RefreshCw,
  ShieldCheck,
} from "lucide-vue-next";
import { computed, reactive, ref } from "vue";
import CockpitLayout from "../layouts/CockpitLayout.vue";

type Recipient = { id: string; name: string; identity: string };
type Grant = {
  reference: string;
  status: string;
  recipient: { name: string; identity: string | null };
  amount: string;
  purpose: string;
  test_allocation: boolean;
  maker: string;
  checker: string | null;
  actions: { approve: string; execute: string };
};
type InstitutionFundCandidate = {
  operation_reference: string;
  evidence_reference: string;
  amount_minor: number;
  amount: string;
  currency: string;
  connection_reference: string;
  available: boolean;
  observed_at: string | null;
};
type InstitutionFundClassification = {
  reference: string;
  status: string;
  amount: string;
  ownership_basis: string;
  evidence_reference: string;
  maker: string;
  checker: string | null;
  updated_at: string | null;
  actions: { approve: string; execute: string };
};
type ReconciliationRun = {
  reference: string;
  status: string;
  connection_reference: string;
  provider: string;
  currency: string;
  purpose: string;
  maker: string;
  checker: string | null;
  provider_balance: string | null;
  internal_balance: string | null;
  difference: string | null;
  evidence_reference: string | null;
  reason: string | null;
  observed_at: string | null;
  actions: { approve: string; execute: string };
};

const props = defineProps<{
  cockpitHeaderReadModel?: Record<string, unknown>;
  treasuryAccountGrants: {
    can_view: boolean;
    can_request: boolean;
    can_approve: boolean;
    can_execute: boolean;
    test_allocations_available: boolean;
    connections: Array<{
      reference: string;
      provider: string;
      currency: string;
    }>;
    recipients: Recipient[];
    grants: Grant[];
  };
  treasuryAccountGrantStoreUrl: string;
  treasuryInstitutionFunds: {
    can_view: boolean;
    can_request: boolean;
    can_approve: boolean;
    can_execute: boolean;
    balance: string;
    candidates: InstitutionFundCandidate[];
    classifications: InstitutionFundClassification[];
  };
  treasuryInstitutionFundStoreUrl: string;
  treasuryReconciliation: {
    can_view: boolean;
    can_request: boolean;
    can_approve: boolean;
    can_execute: boolean;
    connections: Array<{
      reference: string;
      provider: string;
      currency: string;
    }>;
    runs: ReconciliationRun[];
  };
  treasuryReconciliationStoreUrl: string;
}>();

const form = reactive({
  recipient_id: props.treasuryAccountGrants.recipients[0]?.id ?? "",
  connection_reference:
    props.treasuryAccountGrants.connections[0]?.reference ?? "",
  amount: "",
  purpose: "",
  test_allocation: false,
});
const idempotencyReference = ref(crypto.randomUUID());
const institutionForm = reactive({
  evidence_operation_reference:
    props.treasuryInstitutionFunds.candidates.find(
      (candidate) => candidate.available,
    )?.operation_reference ?? "",
  ownership_basis: "",
});
const institutionIdempotencyReference = ref(crypto.randomUUID());
const reconciliationForm = reactive({
  connection_reference:
    props.treasuryReconciliation.connections[0]?.reference ?? "",
  purpose: "",
});
const reconciliationIdempotencyReference = ref(crypto.randomUUID());

const amountMinor = computed(() => {
  const value = form.amount.trim();
  if (!/^\d+(\.\d{1,2})?$/.test(value)) return null;
  const [whole, fraction = ""] = value.split(".");
  return Number(whole) * 100 + Number(fraction.padEnd(2, "0"));
});

function submit(): void {
  if (amountMinor.value === null) return;
  const connection = props.treasuryAccountGrants.connections.find(
    (candidate) => candidate.reference === form.connection_reference,
  );
  if (!connection) return;
  router.post(
    props.treasuryAccountGrantStoreUrl,
    {
      recipient_id: form.recipient_id,
      amount_minor: amountMinor.value,
      currency: connection.currency,
      connection_reference: connection.reference,
      purpose: form.purpose,
      idempotency_reference: idempotencyReference.value,
      test_allocation: form.test_allocation,
    },
    {
      onSuccess: () => {
        idempotencyReference.value = crypto.randomUUID();
        form.amount = "";
        form.purpose = "";
      },
    },
  );
}

function submitInstitutionFunding(): void {
  if (
    !institutionForm.evidence_operation_reference ||
    !institutionForm.ownership_basis.trim()
  )
    return;
  router.post(
    props.treasuryInstitutionFundStoreUrl,
    {
      ...institutionForm,
      idempotency_reference: institutionIdempotencyReference.value,
    },
    {
      preserveScroll: true,
      onSuccess: () => {
        institutionIdempotencyReference.value = crypto.randomUUID();
        institutionForm.evidence_operation_reference = "";
        institutionForm.ownership_basis = "";
      },
    },
  );
}

function submitReconciliation(): void {
  if (
    !reconciliationForm.connection_reference ||
    !reconciliationForm.purpose.trim()
  )
    return;
  router.post(
    props.treasuryReconciliationStoreUrl,
    {
      ...reconciliationForm,
      idempotency_reference: reconciliationIdempotencyReference.value,
    },
    {
      preserveScroll: true,
      onSuccess: () => {
        reconciliationIdempotencyReference.value = crypto.randomUUID();
        reconciliationForm.purpose = "";
      },
    },
  );
}
</script>

<template>
  <Head title="Treasury Operations" />
  <CockpitLayout :cockpit-header-read-model="cockpitHeaderReadModel">
    <div class="mx-auto grid w-full max-w-7xl gap-5 px-4 py-6 sm:px-6">
      <section class="rounded-2xl border border-border bg-card p-5 shadow-sm">
        <div class="flex items-start gap-3">
          <HandCoins class="mt-0.5 size-5 text-emerald-600" />
          <div>
            <h1 class="text-xl font-semibold">Treasury Operations</h1>
            <p class="mt-1 text-sm text-muted-foreground">
              Classify owned liquidity and allocate it to an Account through
              independent maker-checker approval.
            </p>
          </div>
        </div>
        <form
          v-if="treasuryAccountGrants.can_request"
          class="mt-5 grid gap-3 rounded-xl border border-border p-4 md:grid-cols-2"
          @submit.prevent="submit"
        >
          <label class="grid gap-1 text-sm">
            <span class="font-medium">Recipient Account</span>
            <select
              v-model="form.recipient_id"
              class="h-10 rounded-lg border border-border bg-background px-3"
            >
              <option
                v-for="recipient in treasuryAccountGrants.recipients"
                :key="recipient.id"
                :value="recipient.id"
              >
                {{ recipient.name }} · {{ recipient.identity }}
              </option>
            </select>
          </label>
          <label
            v-if="treasuryAccountGrants.connections.length > 1"
            class="grid gap-1 text-sm"
          >
            <span class="font-medium">Treasury Connection</span>
            <select
              v-model="form.connection_reference"
              class="h-10 rounded-lg border border-border bg-background px-3"
            >
              <option
                v-for="connection in treasuryAccountGrants.connections"
                :key="connection.reference"
                :value="connection.reference"
              >
                {{ connection.provider }} · {{ connection.currency }}
              </option>
            </select>
          </label>
          <label class="grid gap-1 text-sm">
            <span class="font-medium">Amount</span>
            <div
              class="flex h-10 items-center rounded-lg border border-border bg-background px-3"
            >
              <span class="text-muted-foreground">₱</span>
              <input
                v-model="form.amount"
                inputmode="decimal"
                class="min-w-0 flex-1 bg-transparent px-2 outline-none"
              />
            </div>
          </label>
          <label class="grid gap-1 text-sm md:col-span-2">
            <span class="font-medium">Purpose</span>
            <input
              v-model="form.purpose"
              class="h-10 rounded-lg border border-border bg-background px-3"
            />
          </label>
          <label
            v-if="treasuryAccountGrants.test_allocations_available"
            class="flex items-center gap-2 text-sm"
          >
            <input v-model="form.test_allocation" type="checkbox" />
            Mark as a staging Test Allocation
          </label>
          <div class="flex justify-end md:col-span-2">
            <button
              type="submit"
              class="inline-flex h-10 items-center gap-2 rounded-xl bg-emerald-600 px-4 text-sm font-semibold text-white hover:bg-emerald-700"
            >
              <ShieldCheck class="size-4" /> Submit For Approval
            </button>
          </div>
        </form>
      </section>

      <section
        v-if="treasuryReconciliation.can_view"
        class="rounded-2xl border border-border bg-card p-5 shadow-sm"
      >
        <div class="flex min-w-0 items-start gap-3">
          <RefreshCw class="mt-0.5 size-5 shrink-0 text-sky-600" />
          <div class="min-w-0">
            <h2 class="font-semibold">Provider Reconciliation</h2>
            <p class="mt-1 text-sm text-muted-foreground">
              Request an authoritative provider balance check. No provider is
              contacted until an independent checker approves the run.
            </p>
          </div>
        </div>

        <form
          v-if="treasuryReconciliation.can_request"
          class="mt-5 grid min-w-0 gap-3 rounded-xl border border-border p-4 md:grid-cols-2"
          @submit.prevent="submitReconciliation"
        >
          <label class="grid min-w-0 gap-1 text-sm">
            <span class="font-medium">Treasury Connection</span>
            <select
              v-model="reconciliationForm.connection_reference"
              class="h-10 min-w-0 rounded-lg border border-border bg-background px-3"
            >
              <option
                v-for="connection in treasuryReconciliation.connections"
                :key="connection.reference"
                :value="connection.reference"
              >
                {{ connection.provider }} · {{ connection.currency }} ·
                {{ connection.reference }}
              </option>
            </select>
          </label>
          <label class="grid min-w-0 gap-1 text-sm">
            <span class="font-medium">Reason For Check</span>
            <input
              v-model="reconciliationForm.purpose"
              class="h-10 min-w-0 rounded-lg border border-border bg-background px-3"
              aria-label="Reason for provider reconciliation"
            />
          </label>
          <div class="flex justify-end md:col-span-2">
            <button
              type="submit"
              class="inline-flex h-10 items-center gap-2 rounded-xl bg-sky-600 px-4 text-sm font-semibold text-white hover:bg-sky-700"
            >
              <ShieldCheck class="size-4" /> Submit Check For Approval
            </button>
          </div>
        </form>

        <div class="mt-4 grid gap-3">
          <article
            v-for="run in treasuryReconciliation.runs"
            :key="run.reference"
            class="rounded-xl border border-border p-4"
          >
            <div class="flex flex-wrap items-start justify-between gap-3">
              <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2">
                  <p class="font-semibold">
                    {{ run.provider }} · {{ run.connection_reference }}
                  </p>
                  <span
                    class="rounded-full bg-muted px-2 py-0.5 text-xs font-medium capitalize"
                    >{{ run.status.replaceAll("_", " ") }}</span
                  >
                </div>
                <p class="mt-1 text-sm">{{ run.purpose }}</p>
                <p
                  v-if="run.evidence_reference"
                  class="mt-1 break-all text-xs text-muted-foreground"
                >
                  {{ run.evidence_reference }}
                </p>
                <p
                  v-if="run.reason"
                  class="mt-1 text-xs font-medium text-amber-700 dark:text-amber-300"
                >
                  {{ run.reason.replaceAll("-", " ") }}
                </p>
              </div>
              <dl
                v-if="run.provider_balance"
                class="grid grid-cols-2 gap-x-4 gap-y-1 text-right text-xs"
              >
                <dt class="text-muted-foreground">Provider</dt>
                <dd class="font-medium tabular-nums">
                  {{ run.provider_balance }}
                </dd>
                <dt class="text-muted-foreground">Attributed</dt>
                <dd class="font-medium tabular-nums">
                  {{ run.internal_balance }}
                </dd>
                <dt class="text-muted-foreground">Difference</dt>
                <dd class="font-semibold tabular-nums">{{ run.difference }}</dd>
              </dl>
            </div>
            <div
              class="mt-3 flex flex-wrap items-center justify-between gap-3 border-t border-border pt-3 text-xs text-muted-foreground"
            >
              <span
                >Maker · {{ run.maker
                }}<template v-if="run.checker">
                  · Checker · {{ run.checker }}</template
                ></span
              >
              <div class="flex gap-2">
                <button
                  v-if="
                    treasuryReconciliation.can_approve &&
                    run.status === 'awaiting_approval'
                  "
                  type="button"
                  class="inline-flex h-9 items-center gap-1 rounded-lg border border-border px-3 font-medium text-foreground"
                  @click="router.post(run.actions.approve)"
                >
                  <Check class="size-4" /> Approve
                </button>
                <button
                  v-if="
                    treasuryReconciliation.can_execute &&
                    run.status === 'approved'
                  "
                  type="button"
                  class="inline-flex h-9 items-center gap-1 rounded-lg bg-sky-600 px-3 font-medium text-white"
                  @click="router.post(run.actions.execute)"
                >
                  <RefreshCw class="size-4" /> Check Provider
                </button>
              </div>
            </div>
          </article>
        </div>
      </section>

      <section
        v-if="treasuryInstitutionFunds.can_view"
        class="rounded-2xl border border-border bg-card p-5 shadow-sm"
      >
        <div class="flex flex-wrap items-start justify-between gap-3">
          <div class="flex min-w-0 items-start gap-3">
            <Landmark class="mt-0.5 size-5 shrink-0 text-amber-600" />
            <div class="min-w-0">
              <h2 class="font-semibold">Institution-Owned Funds</h2>
              <p class="mt-1 text-sm text-muted-foreground">
                Classify only provider-confirmed cash that does not belong to a
                customer, reserve, cost, or payable.
              </p>
            </div>
          </div>
          <div class="text-right">
            <p
              class="text-xs font-medium uppercase tracking-wide text-muted-foreground"
            >
              Grantable
            </p>
            <p class="text-xl font-semibold tabular-nums">
              {{ treasuryInstitutionFunds.balance }}
            </p>
          </div>
        </div>

        <div
          v-if="treasuryInstitutionFunds.can_request"
          class="mt-5 grid gap-3 rounded-xl border border-border p-4"
        >
          <p class="text-sm font-medium">Authoritative Deposit Evidence</p>
          <form
            v-if="treasuryInstitutionFunds.candidates.length"
            class="grid gap-3 md:grid-cols-2"
            @submit.prevent="submitInstitutionFunding"
          >
            <label class="grid gap-1 text-sm">
              <span class="font-medium">Unclassified Deposit</span>
              <select
                v-model="institutionForm.evidence_operation_reference"
                class="h-10 min-w-0 rounded-lg border border-border bg-background px-3"
              >
                <option value="">Choose evidence</option>
                <option
                  v-for="candidate in treasuryInstitutionFunds.candidates"
                  :key="candidate.operation_reference"
                  :value="candidate.operation_reference"
                  :disabled="!candidate.available"
                >
                  {{ candidate.amount }} · {{ candidate.connection_reference
                  }}<template v-if="!candidate.available">
                    · unavailable</template
                  >
                </option>
              </select>
            </label>
            <label class="grid gap-1 text-sm">
              <span class="font-medium">Ownership Basis</span>
              <input
                v-model="institutionForm.ownership_basis"
                class="h-10 min-w-0 rounded-lg border border-border bg-background px-3"
                aria-label="Ownership basis"
              />
            </label>
            <div class="flex justify-end md:col-span-2">
              <button
                type="submit"
                class="inline-flex h-10 items-center gap-2 rounded-xl bg-amber-600 px-4 text-sm font-semibold text-white hover:bg-amber-700"
              >
                <ShieldCheck class="size-4" /> Submit Classification
              </button>
            </div>
          </form>
          <p
            v-else
            class="rounded-lg bg-muted/40 p-4 text-sm text-muted-foreground"
          >
            No unclassified provider evidence is available. Reconcile the
            provider after the owner-funded deposit settles.
          </p>
        </div>

        <div class="mt-4 grid gap-3">
          <article
            v-for="classification in treasuryInstitutionFunds.classifications"
            :key="classification.reference"
            class="rounded-xl border border-border p-4"
          >
            <div class="flex flex-wrap items-start justify-between gap-3">
              <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2">
                  <p class="font-semibold">Owner Funding</p>
                  <span
                    class="rounded-full bg-muted px-2 py-0.5 text-xs font-medium"
                    >{{ classification.status.replaceAll("_", " ") }}</span
                  >
                </div>
                <p class="mt-1 break-all text-xs text-muted-foreground">
                  {{ classification.evidence_reference }}
                </p>
                <p class="mt-2 text-sm">{{ classification.ownership_basis }}</p>
              </div>
              <p class="text-lg font-semibold tabular-nums">
                {{ classification.amount }}
              </p>
            </div>
            <div
              class="mt-3 flex flex-wrap items-center justify-between gap-3 border-t border-border pt-3 text-xs text-muted-foreground"
            >
              <span
                >Maker · {{ classification.maker
                }}<template v-if="classification.checker">
                  · Checker · {{ classification.checker }}</template
                ></span
              >
              <div class="flex gap-2">
                <button
                  v-if="
                    treasuryInstitutionFunds.can_approve &&
                    classification.status === 'awaiting_approval'
                  "
                  type="button"
                  class="inline-flex h-9 items-center gap-1 rounded-lg border border-border px-3 font-medium text-foreground"
                  @click="router.post(classification.actions.approve)"
                >
                  <Check class="size-4" /> Approve
                </button>
                <button
                  v-if="
                    treasuryInstitutionFunds.can_execute &&
                    classification.status === 'approved'
                  "
                  type="button"
                  class="inline-flex h-9 items-center gap-1 rounded-lg bg-amber-600 px-3 font-medium text-white"
                  @click="router.post(classification.actions.execute)"
                >
                  <Landmark class="size-4" /> Classify Funds
                </button>
              </div>
            </div>
          </article>
        </div>
      </section>

      <section
        v-if="treasuryAccountGrants.can_view"
        class="rounded-2xl border border-border bg-card p-5 shadow-sm"
      >
        <h2 class="font-semibold">Account Grants</h2>
        <div class="mt-4 grid gap-3">
          <article
            v-for="grant in treasuryAccountGrants.grants"
            :key="grant.reference"
            class="rounded-xl border border-border p-4"
          >
            <div class="flex flex-wrap items-start justify-between gap-3">
              <div>
                <div class="flex flex-wrap items-center gap-2">
                  <p class="font-semibold">{{ grant.recipient.name }}</p>
                  <span
                    class="rounded-full bg-muted px-2 py-0.5 text-xs font-medium"
                    >{{ grant.status.replaceAll("_", " ") }}</span
                  >
                  <span
                    v-if="grant.test_allocation"
                    class="rounded-full bg-violet-100 px-2 py-0.5 text-xs font-medium text-violet-800"
                    >Test Allocation</span
                  >
                </div>
                <p class="text-sm text-muted-foreground">
                  {{ grant.recipient.identity }} · {{ grant.reference }}
                </p>
                <p class="mt-2 text-sm">{{ grant.purpose }}</p>
              </div>
              <p class="text-lg font-semibold tabular-nums">
                {{ grant.amount }}
              </p>
            </div>
            <div
              class="mt-3 flex flex-wrap items-center justify-between gap-3 border-t border-border pt-3 text-xs text-muted-foreground"
            >
              <span
                >Maker · {{ grant.maker
                }}<template v-if="grant.checker">
                  · Checker · {{ grant.checker }}</template
                ></span
              >
              <div class="flex gap-2">
                <button
                  v-if="
                    treasuryAccountGrants.can_approve &&
                    grant.status === 'awaiting_approval'
                  "
                  type="button"
                  class="inline-flex h-9 items-center gap-1 rounded-lg border border-border px-3 font-medium text-foreground"
                  @click="router.post(grant.actions.approve)"
                >
                  <Check class="size-4" /> Approve
                </button>
                <button
                  v-if="
                    treasuryAccountGrants.can_execute &&
                    grant.status === 'approved'
                  "
                  type="button"
                  class="inline-flex h-9 items-center gap-1 rounded-lg bg-emerald-600 px-3 font-medium text-white"
                  @click="router.post(grant.actions.execute)"
                >
                  <HandCoins class="size-4" /> Allocate Funds
                </button>
              </div>
            </div>
          </article>
          <p
            v-if="!treasuryAccountGrants.grants.length"
            class="rounded-xl bg-muted/40 p-5 text-sm text-muted-foreground"
          >
            No Account Grants yet.
          </p>
        </div>
      </section>
    </div>
  </CockpitLayout>
</template>
