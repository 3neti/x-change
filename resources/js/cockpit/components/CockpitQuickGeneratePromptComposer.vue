<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from "vue";

const props = defineProps<{
  endpoint?: string;
}>();

const emit = defineEmits<{
  interpreted: [instructions: Record<string, unknown>];
  "show-cost": [];
}>();

const prompt = ref("");
const busy = ref(false);
const error = ref("");
const result = ref<Record<string, unknown> | null>(null);
const draftApplied = ref(false);
const promptInput = ref<HTMLInputElement | null>(null);
const hintIndex = ref(0);
const promptHints = [
  "Try: 1,000 pesos with signature and selfie",
  "Try: 500 pesos for Sofia",
  "Try: 100 pesos, claimable until tomorrow",
  "Try: 5,370 The Usual",
];
let interpretationTimer: ReturnType<typeof setTimeout> | null = null;
let hintTimer: ReturnType<typeof setInterval> | null = null;

const instructions = computed<Record<string, unknown> | null>(() => {
  const value = result.value?.instructions;

  return typeof value === "object" && value !== null
    ? (value as Record<string, unknown>)
    : null;
});

const summary = computed<string[]>(() => {
  const data = instructions.value;

  if (data === null) {
    return [];
  }

  const amount = data.cash && typeof data.cash === "object"
    ? (data.cash as Record<string, unknown>).amount
    : null;
  const fields = data.inputs && typeof data.inputs === "object"
    ? (data.inputs as Record<string, unknown>).fields
    : [];
  const icons = Array.isArray(fields)
    ? fields.map((field) => instructionIcon(String(field))).join(" ")
    : "";

  return [
    typeof amount === "number" ? `₱${amount.toFixed(2)}` : null,
    icons || null,
  ].filter((value): value is string => value !== null);
});

const clarification = computed<string>(() => {
  const clarifications = result.value?.clarifications;

  return Array.isArray(clarifications)
    ? clarifications.filter((value): value is string => typeof value === "string").join(" ")
    : "";
});

const isReady = computed<boolean>(() => result.value?.status === "ready" && instructions.value !== null);
const canSubmit = computed<boolean>(() => !busy.value && isReady.value && !draftApplied.value);
const buttonLabel = computed<string>(() => {
  if (busy.value) {
    return "Checking…";
  }

  if (isReady.value && summary.value.length > 0) {
    return summary.value.join(" · ");
  }

  if (result.value?.status === "needs_clarification" || clarification.value !== "") {
    return "Needs details";
  }

  if (error.value !== "") {
    return "Try again";
  }

  return "Describe Pay Code";
});

const emptyStateLabel = computed<string>(() => promptHints[hintIndex.value] ?? promptHints[0]);

function instructionIcon(field: string): string {
  return {
    signature: "✍️",
    selfie: "📸",
    location: "📍",
    otp: "🔐",
    kyc: "🪪",
    name: "👤",
    address: "📍",
  }[field.toLowerCase()] ?? "•";
}

function clearInterpretationTimer(): void {
  if (interpretationTimer !== null) {
    clearTimeout(interpretationTimer);
    interpretationTimer = null;
  }
}

function focusPrompt(): void {
  promptInput.value?.focus();
}

function showCostPreview(): void {
  emit("show-cost");
  focusPrompt();
}

async function interpret(apply = false): Promise<void> {
  clearInterpretationTimer();
  error.value = "";
  draftApplied.value = false;

  if (prompt.value.trim() === "") {
    result.value = null;
    return;
  }

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

    if (!response.ok) {
      throw new Error(String(payload.message ?? "Unable to interpret this prompt."));
    }

    result.value = payload;

    if (apply && isReady.value && instructions.value !== null) {
      emit("interpreted", instructions.value);
      draftApplied.value = true;
    }
  } catch (caught) {
    error.value = caught instanceof Error ? caught.message : "Unable to interpret this prompt.";
    result.value = null;
  } finally {
    busy.value = false;
  }
}

function submit(): void {
  if (canSubmit.value && instructions.value !== null) {
    emit("interpreted", instructions.value);
    draftApplied.value = true;

    return;
  }

  void interpret(true);
}

watch(prompt, () => {
  clearInterpretationTimer();
  result.value = null;
  error.value = "";
  draftApplied.value = false;

  if (prompt.value.trim() === "") {
    return;
  }

  interpretationTimer = setTimeout(() => {
    void interpret();
  }, 450);
});

onBeforeUnmount(clearInterpretationTimer);

onMounted(() => {
  hintTimer = setInterval(() => {
    hintIndex.value = (hintIndex.value + 1) % promptHints.length;
  }, 3500);
});

onBeforeUnmount(() => {
  if (hintTimer !== null) {
    clearInterval(hintTimer);
    hintTimer = null;
  }
});
</script>

<template>
  <section
    class="rounded-2xl border border-violet-200 bg-violet-50/80 p-2 shadow-sm dark:border-violet-900/70 dark:bg-violet-950/20"
    data-testid="cockpit-quick-generate-prompt-composer"
  >
    <div class="flex gap-2">
      <input
        v-model="prompt"
        ref="promptInput"
        class="min-w-0 flex-1 rounded-xl border border-violet-200 bg-white px-4 py-3 text-sm text-slate-950 shadow-sm outline-none focus:border-violet-500 focus:ring-2 focus:ring-violet-200 dark:border-violet-800 dark:bg-slate-950 dark:text-white"
        placeholder="500 pesos. Require signature and selfie."
        aria-label="Describe the Pay Code"
        data-testid="cockpit-quick-generate-prompt-input"
        @keydown.enter="submit"
      />
      <button
        type="button"
        class="min-w-36 rounded-xl bg-violet-600 px-4 py-3 text-xs font-semibold text-white shadow-sm transition hover:bg-violet-700 disabled:cursor-not-allowed disabled:opacity-45"
        :disabled="!canSubmit"
        data-testid="cockpit-quick-generate-prompt-go"
        @click="submit"
      >
        {{ buttonLabel }}
      </button>
    </div>
    <p
      v-if="error"
      class="mt-2 px-1 text-xs text-rose-700 dark:text-rose-300"
      data-testid="cockpit-quick-generate-prompt-error"
    >
      {{ error }}
    </p>
    <p
      v-else-if="clarification && !isReady"
      class="mt-2 px-1 text-xs text-slate-600 dark:text-slate-300"
      data-testid="cockpit-quick-generate-prompt-result"
    >
      {{ clarification }}
    </p>
    <button
      v-else-if="!isReady && !error"
      type="button"
      class="mt-2 w-full rounded-lg px-1 text-left text-xs text-slate-600 transition hover:text-violet-700 dark:text-slate-300 dark:hover:text-violet-300"
      data-testid="cockpit-quick-generate-prompt-result"
      @click="focusPrompt"
    >
      {{ emptyStateLabel }}
    </button>
    <p
      v-else-if="isReady"
      class="mt-2 px-1 text-xs font-medium text-emerald-700 dark:text-emerald-300"
      data-testid="cockpit-quick-generate-prompt-result"
    >
      <button
        type="button"
        class="text-left underline decoration-dotted underline-offset-2 transition hover:text-emerald-900 dark:hover:text-emerald-100"
        @click="showCostPreview"
      >
        Estimated Cost {{ summary[0] ?? "—" }}
      </button>
    </p>
  </section>
</template>
