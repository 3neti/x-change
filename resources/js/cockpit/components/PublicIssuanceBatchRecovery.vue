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

function close(): void {
  open.value = false;
}

function handleKeydown(event: KeyboardEvent): void {
  if (event.key === "Escape") {
    close();
  }
}

function apiError(payload: { message?: string; errors?: Record<string, string[]> }): string {
  return payload.errors?.code?.[0] ?? payload.message ?? "The recovery code could not be verified.";
}

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
    if (!response.ok) throw new Error(apiError(payload));
    batches.value = payload.batches ?? [];
    message.value = batches.value.length ? "Choose a workspace to continue." : "No matching workspaces were found.";
  } catch (exception) {
    error.value = exception instanceof Error ? exception.message : "The recovery code could not be verified.";
  } finally {
    busy.value = false;
  }
}

function requestNewCode(): void {
  challenge.value = null;
  code.value = "";
  batches.value = [];
  message.value = null;
  error.value = null;
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
  <div data-testid="public-batch-recovery">
    <button type="button" class="inline-flex items-center rounded-full px-4 py-3 text-sm font-semibold text-slate-600 transition hover:bg-white hover:text-slate-950 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-500 dark:text-slate-300 dark:hover:bg-slate-900 dark:hover:text-white" data-testid="public-batch-recovery-launcher" @click="open = true">
      Recover a previous batch
    </button>

    <Teleport to="body">
      <div v-if="open" class="fixed inset-0 z-[200] overflow-y-auto p-4 sm:p-6" role="dialog" aria-modal="true" aria-labelledby="public-batch-recovery-title" data-testid="public-batch-recovery-modal" @keydown.esc="handleKeydown">
        <button type="button" class="fixed inset-0 size-full cursor-default bg-slate-950/45 backdrop-blur-[2px]" aria-label="Close recovery dialog" data-testid="public-batch-recovery-backdrop" @click="close" />
        <div class="relative mx-auto mt-8 w-full max-w-xl rounded-2xl border border-slate-200 bg-white p-5 shadow-2xl dark:border-slate-700 dark:bg-slate-900 sm:mt-16 sm:p-6" tabindex="-1">
        <div class="flex items-start justify-between gap-4">
          <div>
            <h2 id="public-batch-recovery-title" class="text-lg font-bold tracking-tight">Recover a previous batch</h2>
            <p class="mt-1 text-sm leading-6 text-slate-500 dark:text-slate-400">Find your Pay Code workspaces using the mobile number recorded at creation.</p>
          </div>
          <button type="button" class="rounded-lg p-2 text-slate-500 transition hover:bg-slate-100 hover:text-slate-950 dark:hover:bg-slate-800 dark:hover:text-white" aria-label="Close recovery dialog" data-testid="public-batch-recovery-close" @click="close">×</button>
        </div>

        <div class="mt-5 space-y-3">
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
            <button v-for="batch in batches" :key="batch.reference" type="button" :disabled="busy" class="flex w-full items-center justify-between gap-4 rounded-xl border border-slate-200 p-4 text-left transition hover:border-emerald-500 disabled:opacity-50 dark:border-slate-700" data-testid="public-batch-recovery-batch" @click="selectBatch(batch)">
              <span class="min-w-0">
                <span class="block text-base font-bold text-slate-950 dark:text-white">{{ date(batch.created_at) }}</span>
                <span class="mt-1 block text-xs text-slate-500 dark:text-slate-400">{{ batch.item_count }} entries · {{ batch.status }} · {{ money(batch.total_minor, batch.currency) }}</span>
                <span class="mt-2 block truncate font-mono text-[0.68rem] text-slate-400 dark:text-slate-500">{{ shortReference(batch.reference) }}</span>
              </span>
              <span class="shrink-0 text-sm font-bold text-emerald-700 dark:text-emerald-300">Open</span>
            </button>
          </div>
          <p v-if="error" class="text-xs font-semibold text-rose-700 dark:text-rose-300">{{ error }}</p>
          <button v-if="challenge && batches.length === 0 && error" type="button" class="text-xs font-bold text-emerald-700 underline dark:text-emerald-300" @click="requestNewCode">Request a new code</button>
        </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>
