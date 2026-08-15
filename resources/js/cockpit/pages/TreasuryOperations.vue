<script setup lang="ts">
import { Head, router } from "@inertiajs/vue3";
import { BanknoteArrowDown, Check, ShieldCheck } from "lucide-vue-next";
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

const props = defineProps<{
  cockpitHeaderReadModel?: Record<string, unknown>;
  treasuryAccountGrants: {
    can_request: boolean;
    can_approve: boolean;
    can_execute: boolean;
    test_allocations_available: boolean;
    connections: Array<{ reference: string; provider: string; currency: string }>;
    recipients: Recipient[];
    grants: Grant[];
  };
  treasuryAccountGrantStoreUrl: string;
}>();

const form = reactive({
  recipient_id: props.treasuryAccountGrants.recipients[0]?.id ?? "",
  connection_reference: props.treasuryAccountGrants.connections[0]?.reference ?? "",
  amount: "",
  purpose: "",
  test_allocation: false,
});
const idempotencyReference = ref(crypto.randomUUID());

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
  router.post(props.treasuryAccountGrantStoreUrl, {
    recipient_id: form.recipient_id,
    amount_minor: amountMinor.value,
    currency: connection.currency,
    connection_reference: connection.reference,
    purpose: form.purpose,
    idempotency_reference: idempotencyReference.value,
    test_allocation: form.test_allocation,
  }, {
    onSuccess: () => {
      idempotencyReference.value = crypto.randomUUID();
      form.amount = "";
      form.purpose = "";
    },
  });
}
</script>

<template>
  <Head title="Treasury Operations" />
  <CockpitLayout :cockpit-header-read-model="cockpitHeaderReadModel">
    <div class="mx-auto grid w-full max-w-7xl gap-5 px-4 py-6 sm:px-6">
      <section class="rounded-2xl border border-border bg-card p-5 shadow-sm">
        <div class="flex items-start gap-3">
          <BanknoteArrowDown class="mt-0.5 size-5 text-emerald-600" />
          <div>
            <h1 class="text-xl font-semibold">Treasury Operations</h1>
            <p class="mt-1 text-sm text-muted-foreground">
              Allocate reconciled institution-owned funds to an Account through maker-checker approval.
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
            <select v-model="form.recipient_id" class="h-10 rounded-lg border border-border bg-background px-3">
              <option v-for="recipient in treasuryAccountGrants.recipients" :key="recipient.id" :value="recipient.id">
                {{ recipient.name }} · {{ recipient.identity }}
              </option>
            </select>
          </label>
          <label v-if="treasuryAccountGrants.connections.length > 1" class="grid gap-1 text-sm">
            <span class="font-medium">Treasury Connection</span>
            <select v-model="form.connection_reference" class="h-10 rounded-lg border border-border bg-background px-3">
              <option v-for="connection in treasuryAccountGrants.connections" :key="connection.reference" :value="connection.reference">
                {{ connection.provider }} · {{ connection.currency }}
              </option>
            </select>
          </label>
          <label class="grid gap-1 text-sm">
            <span class="font-medium">Amount</span>
            <div class="flex h-10 items-center rounded-lg border border-border bg-background px-3">
              <span class="text-muted-foreground">₱</span>
              <input v-model="form.amount" inputmode="decimal" class="min-w-0 flex-1 bg-transparent px-2 outline-none" />
            </div>
          </label>
          <label class="grid gap-1 text-sm md:col-span-2">
            <span class="font-medium">Purpose</span>
            <input v-model="form.purpose" class="h-10 rounded-lg border border-border bg-background px-3" />
          </label>
          <label v-if="treasuryAccountGrants.test_allocations_available" class="flex items-center gap-2 text-sm">
            <input v-model="form.test_allocation" type="checkbox" />
            Mark as a staging Test Allocation
          </label>
          <div class="flex justify-end md:col-span-2">
            <button type="submit" class="inline-flex h-10 items-center gap-2 rounded-xl bg-emerald-600 px-4 text-sm font-semibold text-white hover:bg-emerald-700">
              <ShieldCheck class="size-4" /> Submit For Approval
            </button>
          </div>
        </form>
      </section>

      <section class="rounded-2xl border border-border bg-card p-5 shadow-sm">
        <h2 class="font-semibold">Account Grants</h2>
        <div class="mt-4 grid gap-3">
          <article v-for="grant in treasuryAccountGrants.grants" :key="grant.reference" class="rounded-xl border border-border p-4">
            <div class="flex flex-wrap items-start justify-between gap-3">
              <div>
                <div class="flex flex-wrap items-center gap-2">
                  <p class="font-semibold">{{ grant.recipient.name }}</p>
                  <span class="rounded-full bg-muted px-2 py-0.5 text-xs font-medium">{{ grant.status.replaceAll('_', ' ') }}</span>
                  <span v-if="grant.test_allocation" class="rounded-full bg-violet-100 px-2 py-0.5 text-xs font-medium text-violet-800">Test Allocation</span>
                </div>
                <p class="text-sm text-muted-foreground">{{ grant.recipient.identity }} · {{ grant.reference }}</p>
                <p class="mt-2 text-sm">{{ grant.purpose }}</p>
              </div>
              <p class="text-lg font-semibold tabular-nums">{{ grant.amount }}</p>
            </div>
            <div class="mt-3 flex flex-wrap items-center justify-between gap-3 border-t border-border pt-3 text-xs text-muted-foreground">
              <span>Maker · {{ grant.maker }}<template v-if="grant.checker"> · Checker · {{ grant.checker }}</template></span>
              <div class="flex gap-2">
                <button v-if="treasuryAccountGrants.can_approve && grant.status === 'awaiting_approval'" type="button" class="inline-flex h-9 items-center gap-1 rounded-lg border border-border px-3 font-medium text-foreground" @click="router.post(grant.actions.approve)">
                  <Check class="size-4" /> Approve
                </button>
                <button v-if="treasuryAccountGrants.can_execute && grant.status === 'approved'" type="button" class="inline-flex h-9 items-center gap-1 rounded-lg bg-emerald-600 px-3 font-medium text-white" @click="router.post(grant.actions.execute)">
                  <BanknoteArrowDown class="size-4" /> Allocate Funds
                </button>
              </div>
            </div>
          </article>
          <p v-if="!treasuryAccountGrants.grants.length" class="rounded-xl bg-muted/40 p-5 text-sm text-muted-foreground">No Account Grants yet.</p>
        </div>
      </section>
    </div>
  </CockpitLayout>
</template>
