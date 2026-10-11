<script setup lang="ts">
import { Head, router, useForm } from "@inertiajs/vue3";
import { computed, ref } from "vue";
import { lock, retry, show, unlock, viewer } from "@/routes/x-change/checkout";
import { open, record, reconcile } from "@/routes/x-change/checkout/refund";

defineOptions({ layout: [] });

type CheckoutRow = {
  reference: string;
  order_reference: string | null;
  status: string;
  method: string;
  contact: string | null;
  contact_source: string | null;
  expected_minor: number | null;
  settled_minor: number | null;
  currency: string | null;
  refund_status: string | null;
  refund_eligible: boolean;
  attention_reason: string | null;
  last_activity_at: string | null;
  timeline: Array<{ type: string; at: string | null }>;
};

const props = defineProps<{
  role: "locked" | "owner" | "viewer";
  viewer_token?: string | null;
  monitor: {
    counts: Record<string, number>;
    filter: string;
    rows: CheckoutRow[];
    pagination: { current_page: number; last_page: number; total: number };
  } | null;
}>();

const passwordForm = useForm({ password: "" });
const actionPassword = ref("");
const reason = ref("");
const externalReference = ref("");
const treasuryReference = ref("");
const expanded = ref<string | null>(null);

const filters = ["all", "awaiting_payment", "settled", "issued", "attention", "refund_open"];
const title = computed(() => props.role === "viewer" ? "Checkout payments · viewer" : "Checkout payments");

function money(minor: number | null, currency: string | null): string {
  return minor === null ? "—" : new Intl.NumberFormat("en-PH", {
    style: "currency", currency: currency || "PHP",
  }).format(minor / 100);
}

function filterUrl(status: string, page = 1): string {
  const options = { query: { status, page } };
  return props.role === "viewer" && props.viewer_token
    ? viewer.url(props.viewer_token, options)
    : show.url(options);
}

function submitPassword(): void {
  passwordForm.post(unlock().url, { onSuccess: () => passwordForm.reset() });
}

function openRefund(row: CheckoutRow): void {
  if (reason.value.trim().length < 10) {
    return;
  }
  router.post(open(row.reference).url, { reason: reason.value }, { onSuccess: () => { reason.value = ""; } });
}

function recordRefund(row: CheckoutRow): void {
  router.post(record(row.reference).url, {
    password: actionPassword.value, external_reference: externalReference.value,
  }, { onSuccess: () => { actionPassword.value = ""; externalReference.value = ""; } });
}

function reconcileRefund(row: CheckoutRow): void {
  router.post(reconcile(row.reference).url, {
    password: actionPassword.value, treasury_reference: treasuryReference.value,
  }, { onSuccess: () => { actionPassword.value = ""; treasuryReference.value = ""; } });
}
</script>

<template>
  <div class="min-h-screen bg-slate-50 text-slate-950">
    <Head :title="title">
      <meta name="referrer" content="no-referrer" />
    </Head>
    <main class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
      <header class="mb-8 flex flex-wrap items-start justify-between gap-4">
        <div>
          <p class="text-xs font-bold uppercase tracking-widest text-sky-700">x-change</p>
          <h1 class="mt-2 text-3xl font-bold">{{ title }}</h1>
          <p class="mt-2 text-sm text-slate-600">Public Pay Code funding, issuance, and manual refund tracking.</p>
        </div>
        <button v-if="role === 'owner'" type="button" class="rounded-lg border border-slate-300 px-4 py-2 text-sm" @click="router.post(lock().url)">Lock console</button>
        <span v-else-if="role === 'viewer'" class="rounded-full bg-sky-100 px-3 py-1 text-sm font-semibold text-sky-900">Read only</span>
      </header>

      <section v-if="role === 'locked'" class="max-w-md rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <h2 class="text-lg font-semibold">Owner access</h2>
        <p class="mt-1 text-sm text-slate-600">Enter the commissioned Checkout console password.</p>
        <form class="mt-5 space-y-3" @submit.prevent="submitPassword">
          <label for="owner-password" class="block text-sm font-medium">Password</label>
          <input id="owner-password" v-model="passwordForm.password" type="password" autocomplete="current-password" class="w-full rounded-lg border border-slate-300 px-3 py-2" />
          <p v-if="passwordForm.errors.password" class="text-sm text-red-700">{{ passwordForm.errors.password }}</p>
          <button type="submit" :disabled="passwordForm.processing" class="rounded-lg bg-slate-950 px-4 py-2 text-sm font-semibold text-white disabled:opacity-50">Unlock</button>
        </form>
      </section>

      <template v-else-if="monitor">
        <div class="grid gap-3 sm:grid-cols-3 lg:grid-cols-6">
          <a v-for="filter in filters" :key="filter" :href="filterUrl(filter)" class="rounded-xl border bg-white p-4 shadow-sm" :class="monitor.filter === filter ? 'border-sky-500' : 'border-slate-200'">
            <span class="block text-xs font-semibold capitalize text-slate-500">{{ filter.replaceAll('_', ' ') }}</span>
            <strong class="mt-2 block text-2xl">{{ monitor.counts[filter] ?? 0 }}</strong>
          </a>
        </div>
        <div class="mt-6 overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm">
          <table class="min-w-full text-left text-sm">
            <thead class="border-b bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
              <tr><th class="p-4">Checkout</th><th class="p-4">Status</th><th class="p-4">Selected method</th><th class="p-4">Contact</th><th class="p-4">Expected</th><th class="p-4">Settled</th><th class="p-4">Last activity</th><th class="p-4">Details</th></tr>
            </thead>
            <tbody>
              <template v-for="row in monitor.rows" :key="row.reference">
                <tr class="border-b border-slate-100 align-top">
                  <td class="p-4 font-mono text-xs">{{ row.reference }}<span class="mt-1 block text-slate-500">{{ row.order_reference }}</span></td>
                  <td class="p-4 font-semibold">{{ row.status.replaceAll('_', ' ') }}<span v-if="row.refund_status" class="mt-1 block text-amber-700">Refund: {{ row.refund_status.replaceAll('_', ' ') }}</span><span v-if="row.refund_eligible && !row.refund_status" class="mt-1 block text-xs font-normal text-amber-700">Paid without a Pay Code · refund review needed</span><span v-if="row.status === 'issuance_attention' && row.attention_reason" class="mt-1 block text-xs font-normal text-slate-500">{{ row.attention_reason.replaceAll('_', ' ') }}</span></td>
                  <td class="p-4">{{ row.method.replaceAll('_', ' ') }}</td>
                  <td class="p-4">{{ row.contact ?? '—' }}<span class="mt-1 block text-xs text-slate-500">{{ row.contact_source }}</span></td>
                  <td class="p-4">{{ money(row.expected_minor, row.currency) }}</td>
                  <td class="p-4">{{ money(row.settled_minor, row.currency) }}</td>
                  <td class="p-4">{{ row.last_activity_at ? new Date(row.last_activity_at).toLocaleString() : '—' }}</td>
                  <td class="p-4"><button type="button" class="font-semibold text-sky-700" @click="expanded = expanded === row.reference ? null : row.reference">{{ expanded === row.reference ? 'Hide' : 'Inspect' }}</button></td>
                </tr>
                <tr v-if="expanded === row.reference"><td colspan="8" class="bg-slate-50 p-5">
                  <h2 class="font-semibold">Timeline</h2>
                  <ol class="mt-2 space-y-1 text-sm text-slate-600"><li v-for="event in row.timeline" :key="`${event.type}-${event.at}`">{{ event.at ? new Date(event.at).toLocaleString() : '—' }} · {{ event.type.replaceAll('_', ' ') }}</li></ol>
                  <div v-if="role === 'owner' && (row.refund_eligible || row.refund_status)" class="mt-5 grid gap-4 lg:grid-cols-2">
                    <div v-if="!row.refund_status" class="rounded-xl border bg-white p-4">
                      <template v-if="row.status === 'issuance_attention'"><h3 class="font-semibold">Issuance retry</h3>
                      <button type="button" class="mt-3 rounded-lg bg-sky-700 px-3 py-2 text-sm font-semibold text-white" @click="router.post(retry(row.reference).url)">Request guarded retry</button></template>
                      <h3 class="mt-5 font-semibold">Open manual refund case</h3>
                      <textarea v-model="reason" rows="2" placeholder="Reason for manual refund" class="mt-2 w-full rounded-lg border p-2" />
                      <button type="button" class="mt-2 rounded-lg border px-3 py-2 text-sm font-semibold" @click="openRefund(row)">Open case</button>
                    </div>
                    <div v-if="row.refund_status === 'open'" class="rounded-xl border bg-white p-4">
                      <h3 class="font-semibold">Record external return</h3>
                      <input v-model="externalReference" class="mt-2 w-full rounded-lg border p-2" placeholder="Bank or payment return reference" />
                      <input v-model="actionPassword" type="password" autocomplete="current-password" class="mt-2 w-full rounded-lg border p-2" placeholder="Reconfirm owner password" />
                      <button type="button" class="mt-2 rounded-lg border px-3 py-2 text-sm font-semibold" @click="recordRefund(row)">Record return</button>
                    </div>
                    <div v-if="row.refund_status === 'external_return_recorded'" class="rounded-xl border bg-white p-4">
                      <h3 class="font-semibold">Record Treasury reconciliation</h3>
                      <input v-model="treasuryReference" class="mt-2 w-full rounded-lg border p-2" placeholder="Treasury reconciliation reference" />
                      <input v-model="actionPassword" type="password" autocomplete="current-password" class="mt-2 w-full rounded-lg border p-2" placeholder="Reconfirm owner password" />
                      <button type="button" class="mt-2 rounded-lg border px-3 py-2 text-sm font-semibold" @click="reconcileRefund(row)">Mark reconciled</button>
                    </div>
                  </div>
                </td></tr>
              </template>
              <tr v-if="monitor.rows.length === 0"><td colspan="8" class="p-8 text-center text-slate-500">No checkouts match this filter.</td></tr>
            </tbody>
          </table>
        </div>
        <nav class="mt-4 flex items-center justify-between text-sm"><span>{{ monitor.pagination.total }} checkouts</span><div class="flex gap-3"><a v-if="monitor.pagination.current_page > 1" :href="filterUrl(monitor.filter, monitor.pagination.current_page - 1)" class="text-sky-700">Previous</a><a v-if="monitor.pagination.current_page < monitor.pagination.last_page" :href="filterUrl(monitor.filter, monitor.pagination.current_page + 1)" class="text-sky-700">Next</a></div></nav>
      </template>
    </main>
  </div>
</template>
