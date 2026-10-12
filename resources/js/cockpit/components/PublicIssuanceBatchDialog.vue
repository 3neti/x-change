<script setup lang="ts">
import { computed, ref } from "vue";

const props = defineProps<{
  endpoints: {
    preview: string;
    create: string;
    fund: string;
  };
}>();

const open = ref(false);
const file = ref<File | null>(null);
const mobile = ref("");
const preview = ref<{ items: Array<{ source_row: number; status: string; amount_minor: number; payload: Record<string, string> }>; total_minor: number; errors: string[]; can_create: boolean } | null>(null);
const batch = ref<{ reference: string; access_token: string } | null>(null);
const busy = ref(false);
const error = ref<string | null>(null);

const validItems = computed(() => preview.value?.items.filter((item) => item.status === "valid") ?? []);

function chooseFile(event: Event): void {
  file.value = (event.target as HTMLInputElement).files?.[0] ?? null;
  preview.value = null;
  error.value = null;
}

async function inspect(): Promise<void> {
  if (!file.value) return;
  busy.value = true;
  error.value = null;
  const form = new FormData();
  form.append("file", file.value);
  try {
    const response = await fetch(props.endpoints.preview, { method: "POST", body: form, headers: { Accept: "application/json" } });
    const data = await response.json();
    if (!response.ok) throw new Error(data.message ?? "The file could not be reviewed.");
    preview.value = data;
  } catch (exception) {
    error.value = exception instanceof Error ? exception.message : "The file could not be reviewed.";
  } finally {
    busy.value = false;
  }
}

async function continueToPayment(): Promise<void> {
  if (!file.value || !preview.value?.can_create || mobile.value.trim() === "") return;
  busy.value = true;
  error.value = null;
  const form = new FormData();
  form.append("file", file.value);
  form.append("mobile", mobile.value.trim());
  try {
    const createResponse = await fetch(props.endpoints.create, { method: "POST", body: form, headers: { Accept: "application/json" } });
    const created = await createResponse.json();
    if (!createResponse.ok) throw new Error(created.message ?? "The batch could not be created.");
    batch.value = created;
    const fundResponse = await fetch(props.endpoints.fund.replace("__REFERENCE__", created.reference), {
      method: "POST",
      body: JSON.stringify({ idempotency_key: `batch-${created.reference}` }),
      headers: {
        Accept: "application/json",
        "Content-Type": "application/json",
        "X-Issuance-Batch-Token": created.access_token,
      },
    });
    const funded = await fundResponse.json();
    if (!fundResponse.ok) throw new Error(funded.message ?? "The batch could not be prepared for payment.");
    sessionStorage.setItem(`x-change.batch-token:${created.reference}`, created.access_token);
    window.location.reload();
  } catch (exception) {
    error.value = exception instanceof Error ? exception.message : "The batch could not be prepared for payment.";
  } finally {
    busy.value = false;
  }
}
</script>

<template>
  <div>
    <button type="button" class="inline-flex items-center gap-2 rounded-full border border-slate-300 px-4 py-3 text-sm font-semibold text-slate-700 transition hover:border-emerald-500 hover:text-emerald-700 dark:border-slate-700 dark:text-slate-200" data-testid="public-auto-generate-batch-action" @click="open = true">
      Create many now
    </button>

    <Teleport to="body">
      <div v-if="open" class="fixed inset-0 z-[100] flex items-start justify-center overflow-y-auto bg-slate-950/70 p-4 pt-[max(1rem,8vh)] backdrop-blur-sm sm:p-6 sm:pt-[10vh]" role="dialog" aria-modal="true" @click.self="open = false">
        <section class="relative w-full max-w-2xl overflow-hidden rounded-3xl border border-white/20 bg-white shadow-[0_32px_100px_-24px_rgba(15,23,42,0.65)] dark:border-slate-700 dark:bg-slate-900">
          <div class="border-b border-slate-200 bg-slate-50/80 px-6 py-5 dark:border-slate-800 dark:bg-slate-950/50">
            <div class="flex items-start justify-between gap-4">
              <div><p class="text-xs font-bold uppercase tracking-[0.16em] text-emerald-700 dark:text-emerald-300">Batch issuance</p><h2 class="mt-1 text-xl font-bold">Create many Pay Codes</h2><p class="mt-1 text-sm text-slate-500 dark:text-slate-300">Upload a payroll or ayuda list, review the total, then pay once.</p></div>
              <button type="button" class="rounded-full px-3 py-1.5 text-sm font-semibold text-slate-500 transition hover:bg-white hover:text-slate-900 dark:hover:bg-slate-800 dark:hover:text-white" @click="open = false">Close</button>
            </div>
          </div>
          <div class="space-y-4 px-6 py-6">
            <label class="block text-sm font-semibold">
              CSV, XLS, or XLSX file
              <input class="mt-2 block w-full rounded-lg border border-slate-300 p-2 text-sm dark:border-slate-700 dark:bg-slate-950" type="file" accept=".csv,.xls,.xlsx" @change="chooseFile" />
            </label>
            <button type="button" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white disabled:opacity-50 dark:bg-white dark:text-slate-900" :disabled="!file || busy" @click="inspect">{{ busy ? 'Reviewing…' : 'Review file' }}</button>
            <p v-if="error" class="rounded-lg bg-rose-50 p-3 text-sm text-rose-700">{{ error }}</p>
            <div v-if="preview" class="rounded-xl border border-slate-200 p-4 dark:border-slate-700">
              <p class="font-semibold">{{ validItems.length }} valid rows · ₱{{ (preview.total_minor / 100).toFixed(2) }}</p>
              <p v-if="preview.errors.length" class="mt-2 text-sm text-rose-700">{{ preview.errors.join(' ') }}</p>
              <label class="mt-4 block text-sm font-semibold">
                Your mobile number for batch updates
                <input v-model="mobile" inputmode="tel" class="mt-2 block w-full rounded-lg border border-slate-300 p-2 dark:border-slate-700 dark:bg-slate-950" placeholder="09…" />
              </label>
              <button type="button" class="mt-4 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-bold text-white disabled:opacity-50" :disabled="!preview.can_create || mobile.trim() === '' || busy" @click="continueToPayment">Continue to payment</button>
            </div>
          </div>
        </section>
      </div>
    </Teleport>
  </div>
</template>
