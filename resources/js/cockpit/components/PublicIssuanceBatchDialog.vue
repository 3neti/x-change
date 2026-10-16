<script setup lang="ts">
import { computed, ref } from "vue";

const props = defineProps<{
  endpoints: {
    preview: string;
    create: string;
    fund: string;
    template: string;
  };
}>();

const open = ref(false);
const file = ref<File | null>(null);
const pastedRows = ref<string[][] | null>(null);
const pastedHeaders = ref<string[] | null>(null);
const isDragging = ref(false);
const mobile = ref("");
const preview = ref<{ headers: string[]; mapping: Record<string, string>; items: Array<{ source_row: number; status: string; amount_minor: number; payload: Record<string, string> }>; total_minor: number; pay_code_value_minor?: number; issuance_fee_minor?: number; total_due_minor?: number; currency?: string; errors: string[]; can_create: boolean } | null>(null);
const batch = ref<{ reference: string; access_token: string } | null>(null);
const busy = ref(false);
const error = ref<string | null>(null);
const copiedTemplate = ref(false);
const mappingFields = ["name", "mobile", "email", "amount", "message"] as const;

const validItems = computed(() => preview.value?.items.filter((item) => item.status === "valid") ?? []);
const invalidItems = computed(() => preview.value?.items.filter((item) => item.status !== "valid") ?? []);
const sourceReady = computed(() => file.value !== null || pastedRows.value !== null);
const sourceLabel = computed(() => file.value?.name ?? `${pastedRows.value?.length ?? 0} pasted rows`);
const mappingIssues = computed(() => {
  if (!preview.value) return [];
  return ["name", "mobile", "amount"].filter((field) => !preview.value?.mapping[field]);
});
const showMapping = ref(false);
const payCodeValueMinor = computed(() => preview.value?.pay_code_value_minor ?? preview.value?.total_minor ?? 0);
const issuanceFeeMinor = computed(() => preview.value?.issuance_fee_minor ?? 0);
const totalDueMinor = computed(() => preview.value?.total_due_minor ?? payCodeValueMinor.value + issuanceFeeMinor.value);
const currency = computed(() => preview.value?.currency ?? "PHP");

function formatMoney(minor: number): string {
  return new Intl.NumberFormat("en-PH", { style: "currency", currency: currency.value }).format(minor / 100);
}

function templateCsv(): string {
  return '"name","mobile","email","amount","message"\n"Example Recipient","09170000000","recipient@example.com","100.00","Emergency assistance"\n"Second Recipient","09171112222","second@example.com","50.00",""\n';
}

async function copyTemplate(): Promise<void> {
  try {
    await navigator.clipboard.writeText(templateCsv());
    copiedTemplate.value = true;
    window.setTimeout(() => { copiedTemplate.value = false; }, 2500);
  } catch {
    copiedTemplate.value = false;
  }
}

function resetSource(): void {
  file.value = null;
  pastedRows.value = null;
  pastedHeaders.value = null;
  preview.value = null;
  error.value = null;
  showMapping.value = false;
}

function chooseFile(event: Event): void {
  resetSource();
  file.value = (event.target as HTMLInputElement).files?.[0] ?? null;
}

function parseDelimitedLine(line: string, delimiter: string): string[] {
  const values: string[] = [];
  let value = "";
  let quoted = false;

  for (let index = 0; index < line.length; index += 1) {
    const character = line[index];
    const next = line[index + 1];
    if (character === '"' && quoted && next === '"') {
      value += '"';
      index += 1;
    } else if (character === '"') {
      quoted = !quoted;
    } else if (character === delimiter && !quoted) {
      values.push(value.trim());
      value = "";
    } else {
      value += character;
    }
  }

  values.push(value.trim());
  return values;
}

function parsePastedRows(text: string): void {
  const lines = text.trim().split(/\r?\n/).filter((line) => line.trim() !== "");
  if (lines.length < 1) return;
  const delimiter = lines[0].includes("\t") ? "\t" : ",";
  const values = lines.map((line) => parseDelimitedLine(line, delimiter));
  const knownHeaders = /name|mobile|phone|email|amount|value/i.test(lines[0]);
  pastedHeaders.value = knownHeaders ? values.shift() ?? null : null;
  pastedRows.value = values.length > 0 ? values : null;
  file.value = null;
  preview.value = null;
  error.value = null;
  showMapping.value = false;
}

function pasteRows(event: ClipboardEvent): void {
  parsePastedRows(event.clipboardData?.getData("text/plain") ?? "");
}

function dropInput(event: DragEvent): void {
  isDragging.value = false;
  const droppedFile = event.dataTransfer?.files?.[0];
  if (droppedFile) {
    resetSource();
    file.value = droppedFile;
    return;
  }
  parsePastedRows(event.dataTransfer?.getData("text/plain") ?? "");
}

async function inspect(): Promise<void> {
  if (!sourceReady.value) return;
  busy.value = true;
  error.value = null;
  const form = new FormData();
  if (file.value) form.append("file", file.value);
  if (pastedHeaders.value) form.append("headers", JSON.stringify(pastedHeaders.value));
  if (pastedRows.value) form.append("rows", JSON.stringify(pastedRows.value));
  if (preview.value?.mapping) form.append("mapping", JSON.stringify(preview.value.mapping));
  try {
    const response = await fetch(props.endpoints.preview, { method: "POST", body: form, headers: { Accept: "application/json" } });
    const data = await response.json();
    if (!response.ok) throw new Error(data.message ?? "The file could not be reviewed.");
    preview.value = data;
    showMapping.value = mappingIssues.value.length > 0;
  } catch (exception) {
    error.value = exception instanceof Error ? exception.message : "The file could not be reviewed.";
  } finally {
    busy.value = false;
  }
}

async function continueToPayment(): Promise<void> {
  if ((!file.value && !pastedRows.value) || !preview.value?.can_create || mobile.value.trim() === "") return;
  busy.value = true;
  error.value = null;
  const form = new FormData();
  if (file.value) form.append("file", file.value);
  if (pastedHeaders.value) form.append("headers", JSON.stringify(pastedHeaders.value));
  if (pastedRows.value) form.append("rows", JSON.stringify(pastedRows.value));
  form.append("mapping", JSON.stringify(preview.value.mapping));
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
          <div class="space-y-5 px-6 py-6">
            <div class="flex items-center gap-2 text-xs font-bold uppercase tracking-[0.14em] text-slate-400"><span class="rounded-full bg-emerald-600 px-2 py-1 text-white">1</span> Add list <span class="h-px flex-1 bg-slate-200 dark:bg-slate-700" /><span class="rounded-full bg-slate-200 px-2 py-1 text-slate-600 dark:bg-slate-700 dark:text-slate-200">2</span> Review <span class="h-px flex-1 bg-slate-200 dark:bg-slate-700" /><span class="rounded-full bg-slate-200 px-2 py-1 text-slate-600 dark:bg-slate-700 dark:text-slate-200">3</span> Pay</div>

            <div v-if="!sourceReady" data-testid="batch-source-dropzone" class="flex min-h-40 flex-col items-center justify-center rounded-2xl border-2 border-dashed px-5 py-6 text-center transition" :class="isDragging ? 'border-emerald-500 bg-emerald-50 dark:bg-emerald-950/30' : 'border-slate-300 bg-slate-50/70 dark:border-slate-700 dark:bg-slate-950/60'" tabindex="0" @dragenter.prevent="isDragging = true" @dragover.prevent @dragleave.prevent="isDragging = false" @drop.prevent="dropInput" @paste.stop.prevent="pasteRows">
              <div class="flex size-12 items-center justify-center rounded-2xl bg-white text-emerald-600 shadow-sm dark:bg-slate-900"><span class="text-xl">▦</span></div>
              <p class="mt-3 text-sm font-bold">{{ isDragging ? 'Drop to review' : 'Drop your recipient list here' }}</p>
              <p class="mt-1 text-xs text-slate-500">CSV · XLS · XLSX · or copied spreadsheet rows</p>
              <div class="mt-3 flex flex-wrap items-center justify-center gap-3">
                <a :href="endpoints.template" download class="text-xs font-bold text-emerald-700 underline decoration-emerald-300 underline-offset-4 transition hover:text-emerald-900 dark:text-emerald-300 dark:hover:text-emerald-200">Download CSV template</a>
                <button type="button" class="text-xs font-bold text-sky-700 underline decoration-sky-300 underline-offset-4 transition hover:text-sky-900 dark:text-sky-300 dark:hover:text-sky-200" @click="copyTemplate">{{ copiedTemplate ? 'Template copied' : 'Copy template CSV' }}</button>
              </div>
              <label class="mt-4 inline-flex cursor-pointer rounded-full bg-slate-900 px-4 py-2 text-sm font-bold text-white dark:bg-white dark:text-slate-900">Choose file<input class="sr-only" type="file" accept=".csv,.xls,.xlsx" @change="chooseFile" /></label>
            </div>

            <div v-else class="flex items-center gap-3 rounded-2xl border border-emerald-200 bg-emerald-50/70 p-4 dark:border-emerald-900 dark:bg-emerald-950/20">
              <div class="flex size-12 shrink-0 items-center justify-center rounded-xl bg-white text-emerald-700 shadow-sm dark:bg-slate-900">▦</div>
              <div class="min-w-0 flex-1"><p class="truncate text-sm font-bold">{{ sourceLabel }}</p><p class="text-xs text-slate-600 dark:text-slate-300">Ready to review</p></div>
              <button type="button" class="rounded-full border border-slate-300 bg-white px-3 py-1.5 text-xs font-bold dark:border-slate-700 dark:bg-slate-900" @click="resetSource">Replace</button>
            </div>

            <button v-if="!preview" data-testid="batch-review-button" type="button" class="w-full rounded-xl bg-slate-900 px-4 py-3 text-sm font-bold text-white disabled:opacity-50 dark:bg-white dark:text-slate-900" :disabled="!sourceReady || busy" @click="inspect">{{ busy ? 'Reading list…' : 'Review recipients' }}</button>
            <p v-if="error" class="rounded-xl bg-rose-50 p-3 text-sm text-rose-700 dark:bg-rose-950/30 dark:text-rose-200">{{ error }}</p>

            <div v-if="preview" class="space-y-4">
              <div class="grid gap-3 sm:grid-cols-3">
                <div class="rounded-xl border border-slate-200 bg-white p-3 dark:border-slate-700 dark:bg-slate-900"><p class="text-xs text-slate-500">Recipients</p><p class="mt-1 text-lg font-black">{{ preview.items.length }}</p></div>
                <div class="rounded-xl border border-slate-200 bg-white p-3 dark:border-slate-700 dark:bg-slate-900"><p class="text-xs text-slate-500">Ready</p><p class="mt-1 text-lg font-black text-emerald-600">{{ validItems.length }}</p></div>
                <div class="rounded-xl border border-slate-200 bg-white p-3 dark:border-slate-700 dark:bg-slate-900"><p class="text-xs text-slate-500">Pay Code value</p><p class="mt-1 text-lg font-black">{{ formatMoney(payCodeValueMinor) }}</p></div>
              </div>
              <div class="overflow-hidden rounded-2xl border border-slate-200 dark:border-slate-700">
                <div class="flex items-center justify-between border-b border-slate-200 px-4 py-3 dark:border-slate-700"><p class="text-sm font-bold">Recipient preview</p><p class="text-xs text-slate-500">Showing {{ Math.min(preview.items.length, 10) }} of {{ preview.items.length }}</p></div>
                <div class="overflow-x-auto"><table class="w-full text-left text-xs"><thead class="bg-slate-50 text-slate-500 dark:bg-slate-950"><tr><th class="px-4 py-2 font-semibold">Name</th><th class="px-4 py-2 font-semibold">Mobile</th><th class="px-4 py-2 text-right font-semibold">Amount</th><th class="px-4 py-2 font-semibold">Status</th></tr></thead><tbody><tr v-for="item in preview.items.slice(0, 10)" :key="item.source_row" class="border-t border-slate-100 dark:border-slate-800"><td class="px-4 py-2 font-semibold">{{ item.payload.name || '—' }}</td><td class="px-4 py-2">{{ item.payload.mobile || '—' }}</td><td class="px-4 py-2 text-right">₱{{ (item.amount_minor / 100).toFixed(2) }}</td><td class="px-4 py-2" :class="item.status === 'valid' ? 'text-emerald-600' : 'text-rose-600'">{{ item.status === 'valid' ? 'Ready' : 'Needs attention' }}</td></tr></tbody></table></div>
              </div>
              <p v-if="invalidItems.length" class="rounded-xl bg-rose-50 p-3 text-sm text-rose-700 dark:bg-rose-950/30 dark:text-rose-200">{{ invalidItems.length }} row(s) need attention. Fix the source file before continuing.</p>
              <details class="rounded-xl border border-slate-200 dark:border-slate-700" :open="showMapping || mappingIssues.length > 0"><summary class="cursor-pointer px-4 py-3 text-sm font-semibold">{{ mappingIssues.length ? 'Column mapping needs attention' : 'Column mapping detected automatically' }}</summary><div class="grid gap-3 border-t border-slate-200 p-4 sm:grid-cols-2 dark:border-slate-700"><label v-for="field in mappingFields" :key="field" class="text-xs font-semibold capitalize">{{ field }}<select v-model="preview.mapping[field]" class="mt-1 block w-full rounded-lg border border-slate-300 p-2 text-sm dark:border-slate-700 dark:bg-slate-950" @change="preview.can_create = false"><option value="">Unmapped</option><option v-for="header in preview.headers" :key="header" :value="header">{{ header }}</option></select></label><button type="button" class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold dark:border-slate-700" :disabled="busy" @click="inspect">Recheck mapping</button></div></details>
              <label class="block rounded-2xl border border-slate-200 p-4 text-sm font-semibold dark:border-slate-700">Your mobile number for batch recovery<p class="mt-1 text-xs font-normal text-slate-500">We’ll use it to recover this workspace if you leave the browser.</p><input v-model="mobile" inputmode="tel" class="mt-3 block w-full rounded-lg border border-slate-300 p-2 dark:border-slate-700 dark:bg-slate-950" placeholder="09…" /></label>
              <div class="rounded-2xl bg-slate-900 p-4 text-white dark:bg-slate-950"><div class="space-y-2 text-sm"><div class="flex items-center justify-between"><span class="text-slate-300">Pay Code value</span><span class="font-semibold">{{ formatMoney(payCodeValueMinor) }}</span></div><div class="flex items-center justify-between"><span class="text-slate-300">Issuance fee</span><span class="font-semibold">{{ formatMoney(issuanceFeeMinor) }}</span></div><div class="flex items-center justify-between border-t border-white/15 pt-3 text-base"><span class="font-bold">Total to pay</span><span class="text-xl font-black">{{ formatMoney(totalDueMinor) }}</span></div></div><p class="mt-2 text-xs text-slate-400">The total includes the Pay Code value and the batch issuance fee.</p><button type="button" class="mt-4 w-full rounded-xl bg-emerald-500 px-4 py-3 text-sm font-black text-white disabled:opacity-50" :disabled="!preview.can_create || mobile.trim() === '' || busy" @click="continueToPayment">Continue to payment</button></div>
            </div>
          </div>
        </section>
      </div>
    </Teleport>
  </div>
</template>
