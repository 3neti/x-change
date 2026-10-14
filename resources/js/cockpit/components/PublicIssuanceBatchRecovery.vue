<script setup lang="ts">
import { ref } from "vue";

type Endpoints = { request: string; verify: string; select: string };
type Batch = {
  reference: string;
  created_at: string | null;
  status: string;
  item_count: number;
  total_minor: number;
  currency: string;
  issued_at: string | null;
  expires_at: string | null;
};

const props = defineProps<{ endpoints: Endpoints }>();
const open = ref(false);
const mobile = ref("");
const code = ref("");
const challenge = ref<string | null>(null);
const batches = ref<Batch[]>([]);
const busy = ref(false);
const error = ref<string | null>(null);
const message = ref<string | null>(null);

function money(minor: number, currency: string): string {
  return new Intl.NumberFormat("en-PH", { style: "currency", currency }).format(minor / 100);
}

function date(value: string | null): string {
  return value ? new Date(value).toLocaleString() : "—";
}

function shortReference(reference: string): string {
  return `${reference.slice(0, 8)}…${reference.slice(-4)}`;
}

async function requestCode(): Promise<void> {
  busy.value = true;
  error.value = null;
  try {
    const response = await fetch(props.endpoints.request, {
      method: "POST",
      credentials: "same-origin",
      headers: { "Content-Type": "application/json", Accept: "application/json" },
      body: JSON.stringify({ mobile: mobile.value }),
    });
    const payload = await response.json();
    if (!response.ok) throw new Error(payload.message ?? "Recovery could not be started.");
    challenge.value = payload.challenge_reference ?? null;
    message.value = payload.message ?? "Enter the recovery code sent by SMS.";
  } catch (exception) {
    error.value = exception instanceof Error ? exception.message : "Recovery could not be started.";
  } finally {
    busy.value = false;
  }
}

async function verifyCode(): Promise<void> {
  if (!challenge.value) return;
  busy.value = true;
  error.value = null;
  try {
    const response = await fetch(props.endpoints.verify, {
      method: "POST",
      credentials: "same-origin",
      headers: { "Content-Type": "application/json", Accept: "application/json" },
      body: JSON.stringify({ challenge_reference: challenge.value, code: code.value }),
    });
    const payload = await response.json();
    if (!response.ok) throw new Error(payload.message ?? "The recovery code could not be verified.");
    batches.value = payload.batches ?? [];
    message.value = batches.value.length ? "Choose a workspace to continue." : "No matching workspaces were found.";
  } catch (exception) {
    error.value = exception instanceof Error ? exception.message : "The recovery code could not be verified.";
  } finally {
    busy.value = false;
  }
}

async function selectBatch(batch: Batch): Promise<void> {
  if (!challenge.value) return;
  busy.value = true;
  error.value = null;
  try {
    const response = await fetch(props.endpoints.select, {
      method: "POST",
      credentials: "same-origin",
      headers: { "Content-Type": "application/json", Accept: "application/json" },
      body: JSON.stringify({ challenge_reference: challenge.value, batch_reference: batch.reference }),
    });
    const payload = await response.json();
    if (!response.ok) throw new Error(payload.message ?? "The workspace could not be opened.");
    window.location.href = payload.workspace_url;
  } catch (exception) {
    error.value = exception instanceof Error ? exception.message : "The workspace could not be opened.";
  } finally {
    busy.value = false;
  }
}
</script>

<template>
  <section class="mt-5 rounded-2xl border border-slate-200 bg-white/90 p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900/90" data-testid="public-batch-recovery">
    <button type="button" class="flex w-full items-center justify-between gap-3 text-left" @click="open = !open">
      <span>
        <span class="block text-sm font-bold">Recover a previous batch</span>
        <span class="mt-1 block text-xs text-slate-500 dark:text-slate-400">Find your Pay Code workspaces using the mobile number recorded at creation.</span>
      </span>
      <span class="text-xs font-bold text-emerald-700 dark:text-emerald-300">{{ open ? 'Hide' : 'Open' }}</span>
    </button>
    <div v-if="open" class="mt-4 space-y-3">
      <form v-if="!challenge" class="flex flex-col gap-2 sm:flex-row" @submit.prevent="requestCode">
        <input v-model="mobile" required type="tel" inputmode="tel" autocomplete="tel" placeholder="09XXXXXXXXX" class="min-w-0 flex-1 rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950" />
        <button type="submit" :disabled="busy" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-bold text-white disabled:opacity-50">{{ busy ? 'Sending…' : 'Send recovery code' }}</button>
      </form>
      <p v-if="message && !challenge" class="text-xs text-slate-600 dark:text-slate-300">{{ message }}</p>
      <form v-if="challenge && batches.length === 0" class="space-y-2" @submit.prevent="verifyCode">
        <p class="text-xs text-slate-600 dark:text-slate-300">{{ message }}</p>
        <div class="flex flex-col gap-2 sm:flex-row">
          <input v-model="code" required type="text" inputmode="numeric" autocomplete="one-time-code" placeholder="Recovery code" class="min-w-0 flex-1 rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950" />
          <button type="submit" :disabled="busy" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-bold text-white disabled:opacity-50">{{ busy ? 'Checking…' : 'Verify code' }}</button>
        </div>
      </form>
      <div v-if="challenge && batches.length > 0" class="space-y-2">
        <p class="text-xs text-slate-600 dark:text-slate-300">{{ message }}</p>
        <button v-for="batch in batches" :key="batch.reference" type="button" :disabled="busy" class="flex w-full items-center justify-between gap-4 rounded-xl border border-slate-200 p-3 text-left transition hover:border-emerald-500 disabled:opacity-50 dark:border-slate-700" @click="selectBatch(batch)">
          <span><span class="block text-sm font-bold">{{ shortReference(batch.reference) }}</span><span class="mt-1 block text-xs text-slate-500">{{ date(batch.created_at) }} · {{ batch.item_count }} entries · {{ batch.status }}</span></span>
          <span class="shrink-0 text-sm font-bold">{{ money(batch.total_minor, batch.currency) }}</span>
        </button>
      </div>
      <p v-if="error" class="text-xs font-semibold text-rose-700 dark:text-rose-300">{{ error }}</p>
    </div>
  </section>
</template>
