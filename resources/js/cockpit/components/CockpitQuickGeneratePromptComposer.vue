<script setup lang="ts">
import { computed, ref } from "vue";

const props = withDefaults(
  defineProps<{
    endpoint?: string;
    initialView?: "prompt" | "standard";
  }>(),
  { initialView: "standard" },
);

const emit = defineEmits<{
  interpreted: [instructions: Record<string, unknown>];
  viewChange: [view: "prompt" | "standard"];
}>();

const prompt = ref("");
const view = ref<"prompt" | "standard">(props.initialView);
const busy = ref(false);
const error = ref("");
const result = ref<Record<string, unknown> | null>(null);

const summary = computed(() => {
  const instructions = result.value?.instructions;
  if (typeof instructions !== "object" || instructions === null) return [];
  const data = instructions as Record<string, any>;
  const amount = data.cash?.amount;
  const fields = Array.isArray(data.inputs?.fields) ? data.inputs.fields : [];
  return [
    typeof amount === "number" ? `₱${amount.toFixed(2)}` : null,
    fields.length > 0 ? `Requires ${fields.join(", ")}` : null,
    typeof data.ttl === "string" ? `Expires in ${data.ttl}` : null,
  ].filter(Boolean);
});

function switchView(next: "prompt" | "standard"): void {
  view.value = next;
  emit("viewChange", next);
  const url = new URL(window.location.href);
  url.searchParams.set("view", next);
  window.history.replaceState({}, "", url);
}

async function interpret(): Promise<void> {
  error.value = "";
  result.value = null;
  busy.value = true;
  try {
    const response = await fetch(props.endpoint ?? "", {
      method: "POST",
      headers: {
        Accept: "application/json",
        "Content-Type": "application/json",
        "X-CSRF-TOKEN": document
          .querySelector('meta[name="csrf-token"]')
          ?.getAttribute("content") ?? "",
      },
      body: JSON.stringify({ prompt: prompt.value }),
    });
    const payload = (await response.json()) as Record<string, unknown>;
    if (!response.ok) throw new Error(String(payload.message ?? "Unable to interpret this prompt."));
    result.value = payload;
    if (payload.status === "ready" && typeof payload.instructions === "object" && payload.instructions !== null) {
      emit("interpreted", payload.instructions as Record<string, unknown>);
    }
  } catch (caught) {
    error.value = caught instanceof Error ? caught.message : "Unable to interpret this prompt.";
  } finally {
    busy.value = false;
  }
}
</script>

<template>
  <section class="rounded-2xl border border-violet-200 bg-violet-50/80 p-4 shadow-sm dark:border-violet-900/70 dark:bg-violet-950/20" data-testid="cockpit-quick-generate-prompt-composer">
    <div class="flex flex-wrap items-center justify-between gap-3">
      <div>
        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-violet-700 dark:text-violet-300">Engineering Preview</p>
        <h3 class="mt-1 text-lg font-semibold text-slate-950 dark:text-slate-50">Describe the Pay Code</h3>
        <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">Try “500 pesos. Require signature and selfie.”</p>
      </div>
      <div class="flex rounded-full border border-violet-200 bg-white p-1 text-xs dark:border-violet-800 dark:bg-slate-950">
        <button type="button" class="rounded-full px-3 py-1.5 font-semibold" :class="view === 'prompt' ? 'bg-violet-600 text-white' : 'text-slate-600'" data-testid="cockpit-quick-generate-view-prompt" @click="switchView('prompt')">Prompt</button>
        <button type="button" class="rounded-full px-3 py-1.5 font-semibold" :class="view === 'standard' ? 'bg-violet-600 text-white' : 'text-slate-600'" data-testid="cockpit-quick-generate-view-standard" @click="switchView('standard')">Standard</button>
      </div>
    </div>
    <div class="mt-4 flex flex-col gap-3 sm:flex-row">
      <input v-model="prompt" class="min-w-0 flex-1 rounded-xl border border-violet-200 bg-white px-4 py-3 text-sm text-slate-950 shadow-sm outline-none focus:border-violet-500 focus:ring-2 focus:ring-violet-200 dark:border-violet-800 dark:bg-slate-950 dark:text-white" placeholder="500 pesos. Require signature and selfie." data-testid="cockpit-quick-generate-prompt-input" @keydown.enter="interpret" />
      <button type="button" class="rounded-xl bg-violet-600 px-5 py-3 text-sm font-semibold text-white shadow-sm disabled:opacity-50" :disabled="busy || prompt.trim() === ''" data-testid="cockpit-quick-generate-prompt-go" @click="interpret">{{ busy ? 'Interpreting…' : 'Go!' }}</button>
    </div>
    <p v-if="error" class="mt-3 text-sm text-rose-700 dark:text-rose-300" data-testid="cockpit-quick-generate-prompt-error">{{ error }}</p>
    <div v-if="result" class="mt-3 rounded-xl border border-violet-200 bg-white p-3 text-sm dark:border-violet-800 dark:bg-slate-950" data-testid="cockpit-quick-generate-prompt-result">
      <p class="font-semibold">{{ result.status === 'ready' ? 'Draft applied' : 'More detail needed' }}</p>
      <p v-if="summary.length" class="mt-1 text-slate-600 dark:text-slate-300">{{ summary.join(' · ') }}</p>
      <p v-if="Array.isArray(result.clarifications)" class="mt-1 text-slate-600 dark:text-slate-300">{{ result.clarifications.join(' ') }}</p>
    </div>
  </section>
</template>
