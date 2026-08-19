<script setup lang="ts">
import { Head, router } from "@inertiajs/vue3";
import {
  Ban,
  Check,
  Clipboard,
  KeyRound,
  Plus,
  ShieldCheck,
  Unplug,
  X,
} from "lucide-vue-next";
import { computed, reactive, ref } from "vue";
import CockpitLayout from "../layouts/CockpitLayout.vue";

type Scope = { scope: string; description: string };
type Issuer = { id: string; name: string; identity: string };
type PartnerClient = {
  reference: string;
  client_id: string;
  name: string;
  environment: string;
  status: string;
  scopes: string[];
  issuer: { name: string; identity: string };
  mandate: {
    currencies: string[];
    settlement_rails: string[];
    unbound_pay_codes: boolean;
    maximum_amount: string;
    daily_principal_limit: string;
    voucher_profiles: string[];
    stored_value_spend: {
      enabled: boolean;
      currencies: string[];
      maximum_amount: string;
      daily_amount: string;
    };
  };
  created_at: string | null;
  actions: { check: string; suspend: string; revoke: string };
};
type Credential = {
  client_id: string;
  client_secret: string;
  environment: string;
  scopes: string[];
};
type ProductionMandate = {
  reference: string;
  name: string;
  status: string;
  snapshot_hash: string;
  scopes: string[];
  issuer: { name: string; identity: string };
  submitted_at: string | null;
  actions: { approve: string; activate: string };
};

const props = defineProps<{
  cockpitHeaderReadModel?: Record<string, unknown>;
  partnerApi: {
    api_enabled: boolean;
    production_governance: string;
    can_create_sandbox: boolean;
    can_suspend: boolean;
    can_revoke: boolean;
    can_request_production: boolean;
    can_approve_production: boolean;
    can_activate_production: boolean;
    scopes: Scope[];
    rails: string[];
    issuers: Issuer[];
    clients: PartnerClient[];
    production_mandates: ProductionMandate[];
  };
  partnerApiStoreUrl: string;
  partnerApiProductionStoreUrl: string;
  csrfToken: string;
}>();

const showCreate = ref(false);
const createMode = ref<"sandbox" | "production">("sandbox");
const credential = ref<Credential | null>(null);
const submitting = ref(false);
const errors = ref<Record<string, string[]>>({});
const copied = ref(false);
const connectionNotice = ref("");
const form = reactive({
  name: "",
  issuer_id: props.partnerApi.issuers[0]?.id ?? "",
  scopes: ["capabilities:read", "pay-codes:estimate", "pay-codes:read"],
  settlement_rails: ["automatic", "INSTAPAY"],
  maximum_amount: "1000.00",
  daily_limit: "5000.00",
  voucher_profiles: ["disbursement"],
  stored_value_enabled: false,
  stored_value_maximum_amount: "5.00",
  stored_value_daily_amount: "20.00",
  unbound_pay_codes: false,
  acknowledge_secret_once: false,
});

const canSubmit = computed(
  () =>
    form.name.trim() !== "" &&
    form.issuer_id !== "" &&
    form.scopes.length > 0 &&
    form.settlement_rails.length > 0 &&
    (!usesStoredValueScopes.value || form.stored_value_enabled) &&
    (createMode.value === "production" || form.acknowledge_secret_once),
);

const usesStoredValueScopes = computed(() =>
  form.scopes.some((scope) =>
    ["stored-value:read", "stored-value:spend"].includes(scope),
  ),
);

const canIssueStoredValue = computed(() =>
  form.scopes.includes("pay-codes:issue"),
);

function toMinor(value: string): number | null {
  const normalized = value.trim();
  if (!/^\d+(\.\d{1,2})?$/.test(normalized)) return null;
  const [whole, fraction = ""] = normalized.split(".");
  return Number(whole) * 100 + Number(fraction.padEnd(2, "0"));
}

async function createClient(): Promise<void> {
  const maximum = toMinor(form.maximum_amount);
  const daily = toMinor(form.daily_limit);
  const storedValueMaximum = toMinor(form.stored_value_maximum_amount);
  const storedValueDaily = toMinor(form.stored_value_daily_amount);
  errors.value = {};
  if (maximum === null || daily === null) {
    errors.value = {
      limits: [
        "Enter valid peso amounts with no more than two decimal places.",
      ],
    };
    return;
  }
  if (
    usesStoredValueScopes.value &&
    (storedValueMaximum === null ||
      storedValueDaily === null ||
      storedValueMaximum < 1 ||
      storedValueDaily < storedValueMaximum)
  ) {
    errors.value = {
      stored_value_spend: [
        "Enter a positive per-spend limit and a daily limit that covers it.",
      ],
    };
    return;
  }

  submitting.value = true;
  try {
    const response = await fetch(
      createMode.value === "sandbox"
        ? props.partnerApiStoreUrl
        : props.partnerApiProductionStoreUrl,
      {
        method: "POST",
        credentials: "same-origin",
        headers: {
          Accept: "application/json",
          "Content-Type": "application/json",
          "X-CSRF-TOKEN": props.csrfToken,
        },
        body: JSON.stringify({
          name: form.name,
          issuer_id: form.issuer_id,
          ...(createMode.value === "sandbox" ? { environment: "sandbox" } : {}),
          scopes: form.scopes,
          currencies: ["PHP"],
          settlement_rails: form.settlement_rails,
          maximum_amount_minor: maximum,
          daily_principal_limit_minor: daily,
          voucher_profiles: form.voucher_profiles,
          ...(usesStoredValueScopes.value
            ? {
                stored_value_spend: {
                  enabled: form.stored_value_enabled,
                  currencies: ["PHP"],
                  maximum_amount_minor: storedValueMaximum,
                  daily_amount_minor: storedValueDaily,
                },
              }
            : {}),
          unbound_pay_codes: form.unbound_pay_codes,
          ...(createMode.value === "sandbox"
            ? { acknowledge_secret_once: form.acknowledge_secret_once }
            : {}),
        }),
      },
    );
    const body = await response.json();
    if (!response.ok) {
      errors.value = body.errors ?? {
        submission: [body.message ?? "Client could not be created."],
      };
      return;
    }
    if (createMode.value === "sandbox") credential.value = body as Credential;
    showCreate.value = false;
    if (createMode.value === "production")
      router.reload({ only: ["partner_api"] });
  } finally {
    submitting.value = false;
  }
}

function openCreate(mode: "sandbox" | "production"): void {
  createMode.value = mode;
  showCreate.value = true;
}

async function transitionProductionMandate(
  mandate: ProductionMandate,
  transition: "approve" | "activate",
): Promise<void> {
  const response = await fetch(mandate.actions[transition], {
    method: "POST",
    credentials: "same-origin",
    headers: {
      Accept: "application/json",
      "Content-Type": "application/json",
      "X-CSRF-TOKEN": props.csrfToken,
    },
    body: JSON.stringify(
      transition === "approve"
        ? { confirm_snapshot: true }
        : { acknowledge_secret_once: true },
    ),
  });
  const body = await response.json();
  if (!response.ok) {
    errors.value = body.errors ?? {
      submission: [body.message ?? "The mandate could not be updated."],
    };
    return;
  }
  if (transition === "activate") credential.value = body as Credential;
  router.reload({ only: ["partner_api"] });
}

function toggle(list: string[], value: string): void {
  const index = list.indexOf(value);
  index >= 0 ? list.splice(index, 1) : list.push(value);
}

function suspend(client: PartnerClient): void {
  router.post(client.actions.suspend, {}, { preserveScroll: true });
}

async function checkConnection(client: PartnerClient): Promise<void> {
  const response = await fetch(client.actions.check, {
    method: "POST",
    credentials: "same-origin",
    headers: { Accept: "application/json", "X-CSRF-TOKEN": props.csrfToken },
  });
  const result = await response.json();
  connectionNotice.value = result.ready
    ? `${client.name} is ready for authenticated Partner API requests.`
    : `${client.name} needs configuration attention before use.`;
}

function revoke(client: PartnerClient): void {
  if (window.confirm(`Revoke ${client.name} permanently?`)) {
    router.post(client.actions.revoke, {}, { preserveScroll: true });
  }
}

async function copyCredentials(): Promise<void> {
  if (!credential.value) return;
  await navigator.clipboard.writeText(
    `CLIENT_ID=${credential.value.client_id}\nCLIENT_SECRET=${credential.value.client_secret}`,
  );
  copied.value = true;
}

function finishCredentialCeremony(): void {
  credential.value = null;
  copied.value = false;
  router.reload({ only: ["partner_api"] });
}
</script>

<template>
  <Head title="API Partners" />
  <CockpitLayout :cockpit-header-read-model="cockpitHeaderReadModel">
    <div class="mx-auto grid w-full max-w-7xl gap-5 px-4 py-6 sm:px-6">
      <section class="rounded-2xl border border-border bg-card p-5 shadow-sm">
        <div class="flex flex-wrap items-start justify-between gap-4">
          <div>
            <div class="flex items-center gap-2">
              <KeyRound class="size-5 text-emerald-600" />
              <h1 class="text-xl font-semibold">API Partners</h1>
            </div>
            <p class="mt-1 text-sm text-muted-foreground">
              Govern machine access to Pay Code issuance without sharing a human
              login.
            </p>
          </div>
          <button
            v-if="partnerApi.can_create_sandbox"
            type="button"
            class="inline-flex h-10 items-center gap-2 rounded-xl bg-emerald-600 px-4 text-sm font-semibold text-white hover:bg-emerald-700"
            @click="openCreate('sandbox')"
          >
            <Plus class="size-4" /> Provision Sandbox Client
          </button>
          <button
            v-if="partnerApi.can_request_production"
            type="button"
            class="inline-flex h-10 items-center gap-2 rounded-xl border border-violet-300 px-4 text-sm font-semibold text-violet-700 dark:text-violet-300"
            @click="openCreate('production')"
          >
            <Plus class="size-4" /> Request Production Client
          </button>
        </div>
        <div class="mt-4 grid gap-3 sm:grid-cols-2">
          <div class="rounded-xl bg-muted/45 p-4">
            <p
              class="text-xs font-semibold uppercase tracking-wide text-muted-foreground"
            >
              Partner API
            </p>
            <p class="mt-1 font-medium">
              {{
                partnerApi.api_enabled ? "Enabled" : "Disabled by deployment"
              }}
            </p>
          </div>
          <div
            class="rounded-xl border border-amber-300/60 bg-amber-50/70 p-4 dark:bg-amber-950/20"
          >
            <p
              class="text-xs font-semibold uppercase tracking-wide text-amber-700 dark:text-amber-300"
            >
              Production
            </p>
            <p class="mt-1 text-sm">
              Independent maker-checker approval is required. This workspace
              cannot bypass it.
            </p>
          </div>
        </div>
      </section>

      <section class="rounded-2xl border border-border bg-card p-5 shadow-sm">
        <div>
          <h2 class="font-semibold">Production Mandates</h2>
          <p class="text-sm text-muted-foreground">
            Immutable maker requests, independent approval, then a one-time
            credential ceremony.
          </p>
        </div>
        <div
          v-if="partnerApi.production_mandates.length"
          class="mt-4 grid gap-3"
        >
          <article
            v-for="mandate in partnerApi.production_mandates"
            :key="mandate.reference"
            class="rounded-xl border border-border p-4"
          >
            <div class="flex flex-wrap items-start justify-between gap-3">
              <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2">
                  <h3 class="font-semibold">{{ mandate.name }}</h3>
                  <span
                    class="rounded-full bg-violet-50 px-2 py-0.5 text-xs font-semibold capitalize text-violet-800 dark:bg-violet-950 dark:text-violet-200"
                    >{{ mandate.status.replaceAll("_", " ") }}</span
                  >
                </div>
                <p class="mt-1 text-sm text-muted-foreground">
                  {{ mandate.issuer.name }} · {{ mandate.issuer.identity }}
                </p>
                <p
                  class="mt-1 break-all font-mono text-[10px] text-muted-foreground"
                >
                  Snapshot {{ mandate.snapshot_hash }}
                </p>
              </div>
              <div class="flex gap-2">
                <button
                  v-if="
                    mandate.status === 'awaiting_approval' &&
                    partnerApi.can_approve_production
                  "
                  type="button"
                  class="h-9 rounded-lg border px-3 text-sm"
                  @click="transitionProductionMandate(mandate, 'approve')"
                >
                  Approve
                </button>
                <button
                  v-if="
                    mandate.status === 'approved' &&
                    partnerApi.can_activate_production
                  "
                  type="button"
                  class="h-9 rounded-lg bg-violet-600 px-3 text-sm font-semibold text-white"
                  @click="transitionProductionMandate(mandate, 'activate')"
                >
                  Issue Credentials
                </button>
              </div>
            </div>
            <div class="mt-3 flex flex-wrap gap-1.5">
              <span
                v-for="scope in mandate.scopes"
                :key="scope"
                class="rounded-md bg-muted px-2 py-1 text-xs"
                >{{ scope }}</span
              >
            </div>
          </article>
        </div>
        <div
          v-else
          class="mt-4 rounded-xl border border-dashed p-6 text-center text-sm text-muted-foreground"
        >
          No production mandate requests yet.
        </div>
      </section>

      <section class="rounded-2xl border border-border bg-card p-5 shadow-sm">
        <div class="flex items-center justify-between gap-3">
          <div>
            <h2 class="font-semibold">Clients</h2>
            <p class="text-sm text-muted-foreground">
              {{ partnerApi.clients.length }} governed machine identities
            </p>
          </div>
        </div>
        <div v-if="partnerApi.clients.length" class="mt-4 grid gap-3">
          <p
            v-if="connectionNotice"
            class="rounded-lg bg-sky-50 p-3 text-sm text-sky-800 dark:bg-sky-950/30 dark:text-sky-200"
            role="status"
          >
            {{ connectionNotice }}
          </p>
          <article
            v-for="client in partnerApi.clients"
            :key="client.reference"
            class="rounded-xl border border-border p-4"
          >
            <div class="flex flex-wrap items-start justify-between gap-3">
              <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2">
                  <h3 class="font-semibold">{{ client.name }}</h3>
                  <span
                    class="rounded-full bg-muted px-2 py-0.5 text-xs font-medium capitalize"
                    >{{ client.environment }}</span
                  >
                  <span
                    class="rounded-full px-2 py-0.5 text-xs font-semibold capitalize"
                    :class="
                      client.status === 'active'
                        ? 'bg-emerald-100 text-emerald-800'
                        : 'bg-slate-100 text-slate-700'
                    "
                    >{{ client.status }}</span
                  >
                </div>
                <p class="mt-1 text-sm text-muted-foreground">
                  {{ client.issuer.name }} · {{ client.issuer.identity }}
                </p>
                <p
                  class="mt-1 break-all font-mono text-xs text-muted-foreground"
                >
                  Client ID {{ client.client_id }}
                </p>
              </div>
              <div v-if="client.status === 'active'" class="flex gap-2">
                <button
                  type="button"
                  class="inline-flex h-9 items-center gap-1.5 rounded-lg border px-3 text-sm"
                  @click="checkConnection(client)"
                >
                  <ShieldCheck class="size-4" />Check
                </button>
                <button
                  v-if="partnerApi.can_suspend"
                  type="button"
                  class="inline-flex h-9 items-center gap-1.5 rounded-lg border px-3 text-sm"
                  @click="suspend(client)"
                >
                  <Unplug class="size-4" />Suspend
                </button>
                <button
                  v-if="partnerApi.can_revoke"
                  type="button"
                  class="inline-flex h-9 items-center gap-1.5 rounded-lg border border-rose-300 px-3 text-sm text-rose-700"
                  @click="revoke(client)"
                >
                  <Ban class="size-4" />Revoke
                </button>
              </div>
            </div>
            <div class="mt-3 flex flex-wrap gap-1.5">
              <span
                v-for="scope in client.scopes"
                :key="scope"
                class="rounded-md bg-muted px-2 py-1 text-xs"
                >{{ scope }}</span
              >
            </div>
            <div class="mt-3 grid gap-2 text-sm sm:grid-cols-3">
              <p>
                <span class="text-muted-foreground">Per issue</span><br />{{
                  client.mandate.maximum_amount
                }}
              </p>
              <p>
                <span class="text-muted-foreground">Daily principal</span
                ><br />{{ client.mandate.daily_principal_limit }}
              </p>
              <p>
                <span class="text-muted-foreground">Rails</span><br />{{
                  client.mandate.settlement_rails.join(", ")
                }}
              </p>
            </div>
            <div
              v-if="client.mandate.stored_value_spend.enabled"
              class="mt-3 rounded-lg border border-violet-200 bg-violet-50/60 p-3 text-sm dark:border-violet-800 dark:bg-violet-950/20"
            >
              <p class="font-medium text-violet-800 dark:text-violet-200">
                Reusable balance merchant mandate
              </p>
              <p class="mt-1 text-xs text-muted-foreground">
                Per spend
                {{ client.mandate.stored_value_spend.maximum_amount }} · Daily
                {{ client.mandate.stored_value_spend.daily_amount }} ·
                {{ client.mandate.stored_value_spend.currencies.join(", ") }}
              </p>
            </div>
          </article>
        </div>
        <div
          v-else
          class="mt-4 rounded-xl border border-dashed p-8 text-center text-sm text-muted-foreground"
        >
          No Partner API clients yet.
        </div>
      </section>
    </div>

    <div
      v-if="showCreate"
      class="fixed inset-0 z-50 grid place-items-center bg-black/60 p-4"
      @click.self="showCreate = false"
    >
      <form
        class="max-h-[90vh] w-full max-w-2xl overflow-y-auto rounded-2xl bg-card p-5 shadow-2xl"
        @submit.prevent="createClient"
      >
        <div class="flex items-start justify-between gap-3">
          <div>
            <h2 class="text-lg font-semibold">
              {{
                createMode === "sandbox"
                  ? "Provision Sandbox Client"
                  : "Request Production Client"
              }}
            </h2>
            <p class="text-sm text-muted-foreground">
              {{
                createMode === "sandbox"
                  ? "Bind one machine identity to one issuer Account and a bounded mandate."
                  : "Submit an immutable mandate. No credential exists until an independent checker approves it."
              }}
            </p>
          </div>
          <button type="button" aria-label="Close" @click="showCreate = false">
            <X class="size-5" />
          </button>
        </div>
        <div class="mt-5 grid gap-4 sm:grid-cols-2">
          <label class="grid gap-1 text-sm"
            >Client name<input
              v-model="form.name"
              class="h-10 rounded-lg border bg-background px-3"
              required
          /></label>
          <label class="grid gap-1 text-sm"
            >Issuer Account<select
              v-model="form.issuer_id"
              class="h-10 rounded-lg border bg-background px-3"
              required
            >
              <option
                v-for="issuer in partnerApi.issuers"
                :key="issuer.id"
                :value="issuer.id"
              >
                {{ issuer.name }} · {{ issuer.identity }}
              </option>
            </select></label
          >
          <label class="grid gap-1 text-sm"
            >Maximum per issue (₱)<input
              v-model="form.maximum_amount"
              inputmode="decimal"
              class="h-10 rounded-lg border bg-background px-3"
          /></label>
          <label class="grid gap-1 text-sm"
            >Daily principal limit (₱)<input
              v-model="form.daily_limit"
              inputmode="decimal"
              class="h-10 rounded-lg border bg-background px-3"
          /></label>
        </div>
        <fieldset class="mt-5">
          <legend class="text-sm font-semibold">Scopes</legend>
          <div class="mt-2 grid gap-2 sm:grid-cols-2">
            <label
              v-for="scope in partnerApi.scopes"
              :key="scope.scope"
              class="flex gap-2 rounded-lg border p-3 text-sm"
              ><input
                type="checkbox"
                :checked="form.scopes.includes(scope.scope)"
                @change="toggle(form.scopes, scope.scope)"
              /><span
                ><strong class="block font-medium">{{ scope.scope }}</strong
                ><span class="text-xs text-muted-foreground">{{
                  scope.description
                }}</span></span
              ></label
            >
          </div>
        </fieldset>
        <fieldset
          v-if="canIssueStoredValue"
          class="mt-5 rounded-xl border border-border p-4"
        >
          <legend class="px-1 text-sm font-semibold">Pay Code profiles</legend>
          <div class="grid gap-2 sm:grid-cols-2">
            <label class="flex gap-2 rounded-lg border p-3 text-sm">
              <input
                type="checkbox"
                :checked="form.voucher_profiles.includes('disbursement')"
                @change="toggle(form.voucher_profiles, 'disbursement')"
              />
              <span>Disbursement</span>
            </label>
            <label class="flex gap-2 rounded-lg border p-3 text-sm">
              <input
                type="checkbox"
                :checked="form.voucher_profiles.includes('stored_value')"
                @change="toggle(form.voucher_profiles, 'stored_value')"
              />
              <span>Reusable balance issuance</span>
            </label>
          </div>
        </fieldset>
        <fieldset
          v-if="usesStoredValueScopes"
          data-testid="stored-value-partner-mandate"
          class="mt-5 rounded-xl border border-violet-300/70 bg-violet-50/60 p-4 dark:border-violet-800 dark:bg-violet-950/20"
        >
          <legend
            class="px-1 text-sm font-semibold text-violet-800 dark:text-violet-200"
          >
            Reusable balance merchant mandate
          </legend>
          <label class="flex gap-2 text-sm">
            <input
              v-model="form.stored_value_enabled"
              data-testid="stored-value-mandate-enabled"
              type="checkbox"
            />
            <span>
              Enable merchant debits
              <span class="block text-xs text-muted-foreground">
                This client may debit a presented reusable balance only within
                the limits below.
              </span>
            </span>
          </label>
          <div class="mt-4 grid gap-4 sm:grid-cols-2">
            <label class="grid gap-1 text-sm">
              Maximum per spend (₱)
              <input
                v-model="form.stored_value_maximum_amount"
                data-testid="stored-value-maximum-amount"
                inputmode="decimal"
                class="h-10 rounded-lg border bg-background px-3"
              />
            </label>
            <label class="grid gap-1 text-sm">
              Daily spend limit (₱)
              <input
                v-model="form.stored_value_daily_amount"
                data-testid="stored-value-daily-amount"
                inputmode="decimal"
                class="h-10 rounded-lg border bg-background px-3"
              />
            </label>
          </div>
        </fieldset>
        <fieldset class="mt-5">
          <legend class="text-sm font-semibold">Settlement rails</legend>
          <div class="mt-2 flex flex-wrap gap-2">
            <label
              v-for="rail in partnerApi.rails"
              :key="rail"
              class="flex items-center gap-2 rounded-lg border px-3 py-2 text-sm"
              ><input
                type="checkbox"
                :checked="form.settlement_rails.includes(rail)"
                @change="toggle(form.settlement_rails, rail)"
              />{{ rail }}</label
            >
          </div>
        </fieldset>
        <label class="mt-4 flex gap-2 rounded-lg border p-3 text-sm"
          ><input v-model="form.unbound_pay_codes" type="checkbox" /><span
            >Allow unbound Pay Codes
            <span class="block text-xs text-muted-foreground"
              >Otherwise every issuance must identify a recipient, vendor, or
              secret.</span
            ></span
          ></label
        >
        <label
          v-if="createMode === 'sandbox'"
          class="mt-3 flex gap-2 rounded-lg border border-amber-300 bg-amber-50/70 p-3 text-sm dark:bg-amber-950/20"
          ><input v-model="form.acknowledge_secret_once" type="checkbox" /><span
            >I understand that the client secret is shown once and cannot be
            recovered.</span
          ></label
        >
        <div
          v-if="Object.keys(errors).length"
          class="mt-4 rounded-lg bg-rose-50 p-3 text-sm text-rose-700"
        >
          <p v-for="(messages, field) in errors" :key="field">
            {{ messages.join(" ") }}
          </p>
        </div>
        <div class="mt-5 flex justify-end gap-2">
          <button
            type="button"
            class="h-10 rounded-lg border px-4"
            @click="showCreate = false"
          >
            Cancel</button
          ><button
            type="submit"
            :disabled="!canSubmit || submitting"
            class="h-10 rounded-lg bg-emerald-600 px-4 font-semibold text-white disabled:opacity-50"
          >
            {{
              submitting
                ? "Working…"
                : createMode === "sandbox"
                  ? "Create Client"
                  : "Submit Mandate"
            }}
          </button>
        </div>
      </form>
    </div>

    <div
      v-if="credential"
      class="fixed inset-0 z-50 grid place-items-center bg-black/70 p-4"
    >
      <section
        class="w-full max-w-xl rounded-2xl bg-card p-5 shadow-2xl"
        aria-modal="true"
        role="dialog"
      >
        <div class="flex items-center gap-2 text-emerald-700">
          <ShieldCheck class="size-5" />
          <h2 class="text-lg font-semibold">Client Created</h2>
        </div>
        <p class="mt-2 text-sm text-muted-foreground">
          Copy these credentials now. The secret will disappear when you close
          this dialog.
        </p>
        <dl class="mt-4 grid gap-3">
          <div>
            <dt class="text-xs text-muted-foreground">Client ID</dt>
            <dd class="break-all rounded-lg bg-muted p-3 font-mono text-sm">
              {{ credential.client_id }}
            </dd>
          </div>
          <div>
            <dt class="text-xs text-muted-foreground">Client Secret</dt>
            <dd class="break-all rounded-lg bg-muted p-3 font-mono text-sm">
              {{ credential.client_secret }}
            </dd>
          </div>
        </dl>
        <div class="mt-5 flex justify-end gap-2">
          <button
            type="button"
            class="inline-flex h-10 items-center gap-2 rounded-lg border px-4"
            @click="copyCredentials"
          >
            <component :is="copied ? Check : Clipboard" class="size-4" />{{
              copied ? "Copied" : "Copy Both"
            }}</button
          ><button
            type="button"
            class="h-10 rounded-lg bg-emerald-600 px-4 font-semibold text-white"
            @click="finishCredentialCeremony"
          >
            I saved the credentials
          </button>
        </div>
      </section>
    </div>
  </CockpitLayout>
</template>
