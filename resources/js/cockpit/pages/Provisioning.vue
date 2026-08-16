<script setup lang="ts">
import { Head, router } from "@inertiajs/vue3";
import {
  Ban,
  Check,
  Clipboard,
  KeyRound,
  Plus,
  ShieldCheck,
  UserRoundPlus,
  Undo2,
  X,
} from "lucide-vue-next";
import { computed, reactive, ref } from "vue";
import CockpitLayout from "../layouts/CockpitLayout.vue";

type Profile = {
  value: string;
  label: string;
  description: string;
  capabilities?: string[];
  activation_gate?: string;
};
type Seat = {
  reference: string;
  key: string;
  label: string;
  profile: string;
  profile_label: string;
  required: boolean;
  status: string;
  request_reference: string | null;
};
type ReplacementOption = {
  offer_reference: string;
  request_reference: string;
  purpose: string;
};
type ProvisioningRequest = {
  reference: string;
  profile: string;
  profile_label: string;
  status: string;
  commissioning: boolean;
  purpose: string;
  required_evidence: string[];
  capabilities?: string[];
  activation_gate?: string;
  recipient_designation?: null | {
    counterparty_reference: string;
    commercial_role: string;
    agreement_reference: string;
    settlement_designation_reference: string;
    supersedes_designation_reference: string;
    settlement_disposition: string;
    settlement_account_binding: string;
    component_scope: string[];
  };
  snapshot_hash: string | null;
  revision: number | null;
  submitted_at: string | null;
  approved_at: string | null;
  offer: null | {
    reference: string;
    status: string;
    expires_at: string | null;
    accepted_at: string | null;
    activated_at: string | null;
    candidate_bound: boolean;
    activation_reference: string | null;
    revoked_at: string | null;
    actions?: { activate: string; revoke: string; supersede: string };
    replacement_options?: ReplacementOption[];
  };
  events: { type: string; occurred_at: string | null }[];
  actions: { approve: string; reject: string; withdraw: string; issue: string };
  created_at: string | null;
};
type OneTimeOffer = {
  request_reference: string;
  offer_reference: string;
  claim_url: string;
  delivery_url: string;
  expires_at: string | null;
};

const props = defineProps<{
  cockpitHeaderReadModel?: Record<string, unknown>;
  provisioning: {
    capabilities: Record<string, boolean>;
    stats: {
      vacant_seats: number;
      awaiting_approval: number;
      outstanding_offers: number;
      activated: number;
    };
    profiles: Profile[];
    seats: Seat[];
    requests: ProvisioningRequest[];
  };
  provisioningStoreUrl: string;
  csrfToken: string;
}>();

const showCreate = ref(false);
const submitting = ref(false);
const errors = ref<Record<string, string[]>>({});
const oneTimeOffer = ref<OneTimeOffer | null>(null);
const copied = ref(false);
const deliveryChannel = ref<"email" | "sms">("sms");
const deliveryRecipient = ref("");
const deliveryNotice = ref("");
const terminalAction = ref<null | {
  kind: "reject" | "withdraw";
  url: string;
  label: string;
}>(null);
const terminalReason = ref("");
const authorityAction = ref<null | {
  kind: "activate" | "revoke" | "supersede";
  url: string;
  label: string;
  replacements: ReplacementOption[];
}>(null);
const authorityReason = ref("");
const replacementOfferReference = ref("");
const form = reactive({
  seat_reference: "",
  profile: "",
  purpose: "",
  capabilities: [] as string[],
});

const vacantSeats = computed(() =>
  props.provisioning.seats.filter((seat) => seat.status === "vacant"),
);

const selectedProfileValue = computed(() => {
  if (form.seat_reference) {
    return props.provisioning.seats.find((seat) => seat.reference === form.seat_reference)?.profile ?? "";
  }

  return form.profile;
});

const selectedProfile = computed(() =>
  props.provisioning.profiles.find((profile) => profile.value === selectedProfileValue.value),
);

function resetCapabilities(): void {
  form.capabilities = [];
}

function toggleCapability(capability: string): void {
  form.capabilities = form.capabilities.includes(capability)
    ? form.capabilities.filter((value) => value !== capability)
    : [...form.capabilities, capability];
}

function createRequest(): void {
  errors.value = {};
  router.post(
    props.provisioningStoreUrl,
    {
      seat_reference: form.seat_reference || null,
      profile: form.seat_reference ? null : form.profile,
      purpose: form.purpose,
      capabilities: form.capabilities,
    },
    {
      preserveScroll: true,
      onStart: () => (submitting.value = true),
      onFinish: () => (submitting.value = false),
      onError: (received) => {
        errors.value = Object.fromEntries(
          Object.entries(received).map(([key, value]) => [key, [String(value)]]),
        );
      },
      onSuccess: () => {
        showCreate.value = false;
        form.seat_reference = "";
        form.profile = "";
        form.purpose = "";
        form.capabilities = [];
      },
    },
  );
}

function approveRequest(request: ProvisioningRequest): void {
  router.post(
    request.actions.approve,
    { confirm_snapshot: true },
    { preserveScroll: true },
  );
}

function openTerminalAction(
  request: ProvisioningRequest,
  kind: "reject" | "withdraw",
): void {
  terminalReason.value = "";
  terminalAction.value = {
    kind,
    url: request.actions[kind],
    label: kind === "reject" ? "Reject Request" : "Withdraw Request",
  };
}

function submitTerminalAction(): void {
  if (!terminalAction.value || terminalReason.value.trim() === "") return;
  router.post(
    terminalAction.value.url,
    { reason: terminalReason.value.trim() },
    {
      preserveScroll: true,
      onSuccess: () => {
        terminalAction.value = null;
        terminalReason.value = "";
      },
    },
  );
}

function openAuthorityAction(request: ProvisioningRequest, kind: "activate" | "revoke" | "supersede"): void {
  if (!request.offer) return;
  if (!request.offer.actions) return;
  authorityReason.value = "";
  replacementOfferReference.value = "";
  authorityAction.value = {
    kind,
    url: request.offer.actions[kind],
    label: kind === "activate" ? "Activate Exact Authority" : (kind === "revoke" ? "Revoke Authority" : "Supersede Authority"),
    replacements: request.offer.replacement_options ?? [],
  };
}

function submitAuthorityAction(): void {
  if (!authorityAction.value) return;
  const payload = authorityAction.value.kind === "activate"
    ? { confirm_identity_and_capabilities: true }
    : authorityAction.value.kind === "supersede"
      ? { reason: authorityReason.value.trim(), replacement_offer_reference: replacementOfferReference.value }
      : { reason: authorityReason.value.trim() };
  if (authorityAction.value.kind !== "activate" && !authorityReason.value.trim()) return;
  if (authorityAction.value.kind === "supersede" && !replacementOfferReference.value) return;
  router.post(authorityAction.value.url, payload, {
    preserveScroll: true,
    onSuccess: () => {
      authorityAction.value = null;
      authorityReason.value = "";
      replacementOfferReference.value = "";
    },
  });
}

async function issueOffer(request: ProvisioningRequest): Promise<void> {
  errors.value = {};
  const response = await fetch(request.actions.issue, {
    method: "POST",
    credentials: "same-origin",
    headers: {
      Accept: "application/json",
      "Content-Type": "application/json",
      "X-CSRF-TOKEN": props.csrfToken,
    },
    body: JSON.stringify({ acknowledge_one_time_link: true }),
  });
  const body = await response.json();
  if (!response.ok) {
    errors.value = body.errors ?? {
      submission: [body.message ?? "Invitation could not be issued."],
    };
    return;
  }
  oneTimeOffer.value = body as OneTimeOffer;
}

async function copyOffer(): Promise<void> {
  if (!oneTimeOffer.value) return;
  await navigator.clipboard.writeText(oneTimeOffer.value.claim_url);
  copied.value = true;
}

async function deliverOffer(): Promise<void> {
  if (!oneTimeOffer.value || !deliveryRecipient.value.trim()) return;
  const token = new URL(oneTimeOffer.value.claim_url).pathname.split("/").filter(Boolean).pop();
  if (!token) return;
  const response = await fetch(oneTimeOffer.value.delivery_url, {
    method: "POST",
    credentials: "same-origin",
    headers: {
      Accept: "application/json",
      "Content-Type": "application/json",
      "X-CSRF-TOKEN": props.csrfToken,
    },
    body: JSON.stringify({
      channel: deliveryChannel.value,
      recipient: deliveryRecipient.value.trim(),
      claim_token: token,
    }),
  });
  const body = await response.json();
  deliveryNotice.value = response.ok
    ? `Invitation queued for ${deliveryChannel.value.toUpperCase()} delivery.`
    : body.message ?? "Invitation delivery could not be queued.";
}

function finishOfferCeremony(): void {
  oneTimeOffer.value = null;
  copied.value = false;
  deliveryRecipient.value = "";
  deliveryNotice.value = "";
  router.reload({ only: ["provisioning"] });
}

function formatDate(value: string | null): string {
  if (!value) return "—";
  return new Intl.DateTimeFormat("en-PH", {
    dateStyle: "medium",
    timeStyle: "short",
  }).format(new Date(value));
}
</script>

<template>
  <Head title="Provisioning" />
  <CockpitLayout :cockpit-header-read-model="cockpitHeaderReadModel">
    <div class="mx-auto grid w-full max-w-7xl gap-5 px-4 py-6 sm:px-6">
      <section class="rounded-2xl border border-border bg-card p-5 shadow-sm">
        <div class="flex flex-wrap items-start justify-between gap-4">
          <div class="min-w-0">
            <div class="flex items-center gap-2">
              <ShieldCheck class="size-5 text-emerald-600" />
              <h1 class="text-xl font-semibold">Provisioning</h1>
            </div>
            <p class="mt-1 max-w-3xl text-sm text-muted-foreground">
              Prepare authority, obtain independent approval, and invite the
              verified person who will accept it. Provisioning never moves money.
            </p>
          </div>
          <button
            v-if="provisioning.capabilities.request"
            type="button"
            class="inline-flex h-10 shrink-0 items-center gap-2 rounded-xl bg-emerald-600 px-4 text-sm font-semibold text-white hover:bg-emerald-700"
            @click="showCreate = true"
          >
            <Plus class="size-4" /> Prepare Request
          </button>
        </div>

        <div class="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
          <div
            v-for="(value, label) in provisioning.stats"
            :key="label"
            class="rounded-xl bg-muted/45 p-4"
          >
            <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
              {{ String(label).replaceAll("_", " ") }}
            </p>
            <p class="mt-1 text-2xl font-semibold tabular-nums">{{ value }}</p>
          </div>
        </div>
      </section>

      <section class="grid gap-4 xl:grid-cols-[0.85fr_1.15fr]">
        <div class="rounded-2xl border border-border bg-card p-5 shadow-sm">
          <div class="flex items-center gap-2">
            <UserRoundPlus class="size-5 text-violet-600" />
            <h2 class="font-semibold">Commissioning Seats</h2>
          </div>
          <p class="mt-1 text-sm text-muted-foreground">
            A vacant seat records a required responsibility before its human identity is known.
          </p>
          <div class="mt-4 grid gap-2">
            <article
              v-for="seat in provisioning.seats"
              :key="seat.reference"
              class="flex min-w-0 items-center justify-between gap-3 rounded-xl border border-border p-3"
            >
              <div class="min-w-0">
                <p class="truncate text-sm font-medium">{{ seat.label }}</p>
                <p class="text-xs text-muted-foreground">
                  {{ seat.required ? "Required" : "Optional" }} · {{ seat.profile_label }}
                </p>
              </div>
              <span class="rounded-full bg-muted px-2.5 py-1 text-xs font-medium capitalize">
                {{ seat.status.replaceAll("_", " ") }}
              </span>
            </article>
          </div>
        </div>

        <div class="rounded-2xl border border-border bg-card p-5 shadow-sm">
          <h2 class="font-semibold">Authority Requests</h2>
          <p class="mt-1 text-sm text-muted-foreground">
            Every row is an immutable maker-checker envelope with append-only evidence.
          </p>
          <div class="mt-4 grid gap-3">
            <article
              v-for="request in provisioning.requests"
              :key="request.reference"
              class="rounded-xl border border-border p-4"
            >
              <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="min-w-0">
                  <div class="flex flex-wrap items-center gap-2">
                    <p class="font-medium">{{ request.profile_label }}</p>
                    <span class="rounded-full bg-muted px-2 py-0.5 text-xs font-medium capitalize">
                      {{ request.status.replaceAll("_", " ") }}
                    </span>
                  </div>
                  <p class="mt-1 text-sm text-muted-foreground">{{ request.purpose }}</p>
                  <p class="mt-2 break-all font-mono text-[11px] text-muted-foreground">
                    {{ request.reference }} · v{{ request.revision }} · {{ request.snapshot_hash }}
                  </p>
                </div>
                <div class="flex flex-wrap gap-2">
                  <button
                    v-if="request.status === 'awaiting_approval' && provisioning.capabilities.approve"
                    type="button"
                    class="inline-flex h-9 items-center gap-2 rounded-xl border border-border px-3 text-sm font-medium hover:bg-muted"
                    @click="approveRequest(request)"
                  >
                    <Check class="size-4" /> Approve
                  </button>
                  <button
                    v-if="request.status === 'activated' && provisioning.capabilities.revoke && request.offer?.replacement_options?.length"
                    type="button"
                    class="inline-flex h-9 items-center gap-2 rounded-xl border border-violet-200 px-3 text-sm font-medium text-violet-700 hover:bg-violet-50 dark:border-violet-900 dark:text-violet-300 dark:hover:bg-violet-950"
                    @click="openAuthorityAction(request, 'supersede')"
                  >
                    <Undo2 class="size-4" /> Replace
                  </button>
                  <button
                    v-if="request.status === 'activation_pending' && provisioning.capabilities.activate"
                    type="button"
                    class="inline-flex h-9 items-center gap-2 rounded-xl bg-violet-600 px-3 text-sm font-semibold text-white hover:bg-violet-700"
                    @click="openAuthorityAction(request, 'activate')"
                  >
                    <ShieldCheck class="size-4" /> Activate
                  </button>
                  <button
                    v-if="request.status === 'activated' && provisioning.capabilities.revoke"
                    type="button"
                    class="inline-flex h-9 items-center gap-2 rounded-xl border border-rose-200 px-3 text-sm font-medium text-rose-700 hover:bg-rose-50 dark:border-rose-900 dark:text-rose-300 dark:hover:bg-rose-950"
                    @click="openAuthorityAction(request, 'revoke')"
                  >
                    <Ban class="size-4" /> Revoke
                  </button>
                  <button
                    v-if="request.status === 'awaiting_approval' && provisioning.capabilities.approve"
                    type="button"
                    class="inline-flex h-9 items-center gap-2 rounded-xl border border-rose-200 px-3 text-sm font-medium text-rose-700 hover:bg-rose-50 dark:border-rose-900 dark:text-rose-300 dark:hover:bg-rose-950"
                    @click="openTerminalAction(request, 'reject')"
                  >
                    <Ban class="size-4" /> Reject
                  </button>
                  <button
                    v-if="['draft', 'awaiting_approval', 'approved'].includes(request.status) && provisioning.capabilities.request"
                    type="button"
                    class="inline-flex h-9 items-center gap-2 rounded-xl border border-border px-3 text-sm font-medium hover:bg-muted"
                    @click="openTerminalAction(request, 'withdraw')"
                  >
                    <Undo2 class="size-4" /> Withdraw
                  </button>
                  <button
                    v-if="request.status === 'approved' && provisioning.capabilities.issue"
                    type="button"
                    class="inline-flex h-9 items-center gap-2 rounded-xl bg-emerald-600 px-3 text-sm font-semibold text-white hover:bg-emerald-700"
                    @click="issueOffer(request)"
                  >
                    <KeyRound class="size-4" /> Issue Invitation
                  </button>
                </div>
              </div>
              <div class="mt-3 flex flex-wrap gap-1.5">
                <span
                  v-for="field in request.required_evidence"
                  :key="field"
                  class="rounded-full bg-slate-100 px-2 py-1 text-xs font-medium capitalize text-slate-700 dark:bg-slate-800 dark:text-slate-200"
                >
                  {{ field.replaceAll("_", " ") }}
                </span>
              </div>
              <div v-if="request.capabilities?.length" class="mt-3 rounded-lg border border-border/70 p-3">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-muted-foreground">Authority Snapshot</p>
                <div class="mt-2 flex flex-wrap gap-1.5">
                  <span
                    v-for="capability in request.capabilities ?? []"
                    :key="capability"
                    class="rounded-full bg-violet-50 px-2 py-1 font-mono text-[10px] text-violet-800 dark:bg-violet-950 dark:text-violet-200"
                  >
                    {{ capability }}
                  </span>
                </div>
              </div>
              <div v-else-if="request.recipient_designation" class="mt-3 grid gap-3 rounded-lg border border-violet-200 bg-violet-50/60 p-3 text-xs dark:border-violet-900 dark:bg-violet-950/40">
                <div class="grid gap-1 sm:grid-cols-2">
                  <div class="min-w-0">
                    <p class="font-semibold text-violet-950 dark:text-violet-100">Recipient authority</p>
                    <p class="break-all font-mono text-[11px] text-violet-800 dark:text-violet-200">{{ request.recipient_designation.counterparty_reference }}</p>
                  </div>
                  <div class="min-w-0">
                    <p class="font-semibold text-violet-950 dark:text-violet-100">Settlement</p>
                    <p class="capitalize text-violet-800 dark:text-violet-200">{{ request.recipient_designation.settlement_disposition.replaceAll("_", " ") }} · accepted candidate Account</p>
                  </div>
                </div>
                <div class="grid gap-1">
                  <p class="font-semibold text-violet-950 dark:text-violet-100">Governed replacement</p>
                  <p class="break-all font-mono text-[11px] text-violet-800 dark:text-violet-200">
                    {{ request.recipient_designation.supersedes_designation_reference }} → {{ request.recipient_designation.settlement_designation_reference }}
                  </p>
                </div>
                <div class="flex flex-wrap gap-1.5">
                  <span
                    v-for="component in request.recipient_designation.component_scope"
                    :key="component"
                    class="rounded-full bg-white px-2 py-1 font-mono text-[10px] text-violet-800 dark:bg-violet-950 dark:text-violet-200"
                  >
                    {{ component }}
                  </span>
                </div>
              </div>
              <p v-else-if="request.activation_gate && request.activation_gate !== 'operator_authority'" class="mt-3 rounded-lg border border-amber-200 bg-amber-50 p-3 text-xs text-amber-900 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-100">
                Continues through {{ request.activation_gate.replaceAll("_", " ") }}. No operator authority is inferred from the profile label.
              </p>
              <div v-if="request.offer" class="mt-3 rounded-lg bg-muted/45 p-3 text-xs">
                Offer {{ request.offer.status.replaceAll("_", " ") }} · expires
                {{ formatDate(request.offer.expires_at) }}
              </div>
            </article>
            <div v-if="provisioning.requests.length === 0" class="rounded-xl border border-dashed border-border p-8 text-center text-sm text-muted-foreground">
              No provisioning requests yet.
            </div>
          </div>
        </div>
      </section>
    </div>

    <div
      v-if="showCreate"
      class="fixed inset-0 z-50 grid place-items-center bg-slate-950/65 p-4"
      role="dialog"
      aria-modal="true"
      aria-label="Prepare provisioning request"
      @click.self="showCreate = false"
    >
      <form class="grid w-full max-w-xl gap-4 rounded-2xl bg-card p-5 shadow-2xl" @submit.prevent="createRequest">
        <div class="flex items-start justify-between gap-3">
          <div>
            <h2 class="text-lg font-semibold">Prepare Authority</h2>
            <p class="text-sm text-muted-foreground">The snapshot becomes immutable when submitted.</p>
          </div>
          <button type="button" class="rounded-lg p-2 hover:bg-muted" aria-label="Close" @click="showCreate = false">
            <X class="size-4" />
          </button>
        </div>
        <label class="grid gap-1.5 text-sm font-medium">
          Commissioning Seat
          <select v-model="form.seat_reference" class="h-10 rounded-xl border border-input bg-background px-3" @change="resetCapabilities">
            <option value="">No seat · choose a profile</option>
            <option v-for="seat in vacantSeats" :key="seat.reference" :value="seat.reference">
              {{ seat.label }}
            </option>
          </select>
        </label>
        <label v-if="!form.seat_reference" class="grid gap-1.5 text-sm font-medium">
          Authority Profile
          <select v-model="form.profile" required class="h-10 rounded-xl border border-input bg-background px-3" @change="resetCapabilities">
            <option value="" disabled>Choose a profile</option>
            <option v-for="profile in provisioning.profiles" :key="profile.value" :value="profile.value">
              {{ profile.label }}
            </option>
          </select>
        </label>
        <fieldset v-if="selectedProfile?.capabilities?.length" class="grid gap-2 rounded-xl border border-border p-3">
          <legend class="px-1 text-sm font-medium">Exact Authority</legend>
          <p class="text-xs text-muted-foreground">Select only what this recipient should receive. The checker approves this immutable list.</p>
          <label
            v-for="capability in selectedProfile.capabilities ?? []"
            :key="capability"
            class="flex items-start gap-2 rounded-lg p-2 text-xs hover:bg-muted/60"
          >
            <input
              type="checkbox"
              class="mt-0.5 size-4 rounded border-input"
              :checked="form.capabilities.includes(capability)"
              @change="toggleCapability(capability)"
            />
            <span class="break-all font-mono">{{ capability }}</span>
          </label>
        </fieldset>
        <p v-else-if="selectedProfile" class="rounded-xl border border-amber-200 bg-amber-50 p-3 text-xs text-amber-900 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-100">
          This profile continues through {{ (selectedProfile.activation_gate ?? "operator_authority").replaceAll("_", " ") }}. No operator capability is granted by this invitation alone.
        </p>
        <label class="grid gap-1.5 text-sm font-medium">
          Purpose
          <textarea v-model="form.purpose" required maxlength="255" rows="3" class="rounded-xl border border-input bg-background px-3 py-2" />
        </label>
        <p v-for="messages in errors" :key="messages[0]" class="text-sm text-rose-600">{{ messages[0] }}</p>
        <button type="submit" :disabled="submitting" class="h-11 rounded-xl bg-emerald-600 px-4 text-sm font-semibold text-white disabled:opacity-50">
          {{ submitting ? "Submitting…" : "Submit For Approval" }}
        </button>
      </form>
    </div>

    <div
      v-if="oneTimeOffer"
      class="fixed inset-0 z-50 grid place-items-center bg-slate-950/70 p-4"
      role="dialog"
      aria-modal="true"
      aria-label="One-time provisioning invitation"
    >
      <div class="grid w-full max-w-xl gap-4 rounded-2xl bg-card p-5 shadow-2xl">
        <div class="flex items-center gap-2">
          <KeyRound class="size-5 text-amber-600" />
          <h2 class="text-lg font-semibold">Copy This Invitation Now</h2>
        </div>
        <p class="text-sm text-muted-foreground">
          This high-entropy link is shown once. The short display code is never sufficient proof of privileged authority.
        </p>
        <p class="break-all rounded-xl bg-muted p-3 font-mono text-xs">{{ oneTimeOffer.claim_url }}</p>
        <button type="button" class="inline-flex h-11 items-center justify-center gap-2 rounded-xl bg-emerald-600 px-4 text-sm font-semibold text-white" @click="copyOffer">
          <Clipboard class="size-4" /> {{ copied ? "Copied" : "Copy Invitation Link" }}
        </button>
        <div class="grid gap-2 rounded-xl border border-border p-3 sm:grid-cols-[7rem_1fr_auto]">
          <select v-model="deliveryChannel" class="h-10 rounded-lg border border-input bg-background px-2 text-sm" aria-label="Delivery channel">
            <option value="sms">SMS</option><option value="email">Email</option>
          </select>
          <input v-model="deliveryRecipient" class="h-10 min-w-0 rounded-lg border border-input bg-background px-3 text-sm" :placeholder="deliveryChannel === 'sms' ? '09XXXXXXXXX' : 'name@example.com'" aria-label="Invitation recipient" />
          <button type="button" class="h-10 rounded-lg border border-emerald-300 px-3 text-sm font-semibold text-emerald-700" @click="deliverOffer">Send</button>
        </div>
        <p v-if="deliveryNotice" role="status" class="text-sm text-muted-foreground">{{ deliveryNotice }}</p>
        <button type="button" class="h-10 rounded-xl border border-border text-sm font-medium" @click="finishOfferCeremony">
          I Have Saved The Link
        </button>
      </div>
    </div>

    <div
      v-if="authorityAction"
      class="fixed inset-0 z-50 grid place-items-center bg-slate-950/70 p-4"
      role="dialog"
      aria-modal="true"
      :aria-label="authorityAction.label"
      @click.self="authorityAction = null"
    >
      <form class="grid w-full max-w-lg gap-4 rounded-2xl bg-card p-5 shadow-2xl" @submit.prevent="submitAuthorityAction">
        <div class="flex items-start justify-between gap-3">
          <div>
            <h2 class="text-lg font-semibold">{{ authorityAction.label }}</h2>
            <p class="text-sm text-muted-foreground">
              {{ authorityAction.kind === "activate" ? "Confirm the accepted identity and exact capability pills shown in the immutable snapshot." : (authorityAction.kind === "supersede" ? "The replacement remains active before this predecessor is retired." : "Only authority projected by this envelope will be revoked.") }}
            </p>
          </div>
          <button type="button" class="rounded-lg p-2 hover:bg-muted" aria-label="Close" @click="authorityAction = null">
            <X class="size-4" />
          </button>
        </div>
        <label v-if="authorityAction.kind === 'supersede'" class="grid gap-1.5 text-sm font-medium">
          Active Replacement
          <select v-model="replacementOfferReference" required class="h-10 rounded-xl border border-input bg-background px-3">
            <option value="" disabled>Choose an activated replacement</option>
            <option v-for="replacement in authorityAction.replacements" :key="replacement.offer_reference" :value="replacement.offer_reference">
              {{ replacement.purpose || replacement.request_reference }}
            </option>
          </select>
        </label>
        <label v-if="authorityAction.kind !== 'activate'" class="grid gap-1.5 text-sm font-medium">
          Reason
          <textarea v-model="authorityReason" required maxlength="255" rows="3" class="rounded-xl border border-input bg-background px-3 py-2" />
        </label>
        <button type="submit" :class="authorityAction.kind === 'activate' ? 'bg-violet-600 hover:bg-violet-700' : 'bg-rose-600 hover:bg-rose-700'" class="h-11 rounded-xl px-4 text-sm font-semibold text-white">
          Confirm {{ authorityAction.kind === "activate" ? "Activation" : (authorityAction.kind === "supersede" ? "Supersession" : "Revocation") }}
        </button>
      </form>
    </div>

    <div
      v-if="terminalAction"
      class="fixed inset-0 z-50 grid place-items-center bg-slate-950/70 p-4"
      role="dialog"
      aria-modal="true"
      :aria-label="terminalAction.label"
      @click.self="terminalAction = null"
    >
      <form class="grid w-full max-w-lg gap-4 rounded-2xl bg-card p-5 shadow-2xl" @submit.prevent="submitTerminalAction">
        <div class="flex items-start justify-between gap-3">
          <div>
            <h2 class="text-lg font-semibold">{{ terminalAction.label }}</h2>
            <p class="text-sm text-muted-foreground">The reason becomes append-only lifecycle evidence.</p>
          </div>
          <button type="button" class="rounded-lg p-2 hover:bg-muted" aria-label="Close" @click="terminalAction = null">
            <X class="size-4" />
          </button>
        </div>
        <label class="grid gap-1.5 text-sm font-medium">
          Reason
          <textarea v-model="terminalReason" required maxlength="255" rows="3" class="rounded-xl border border-input bg-background px-3 py-2" />
        </label>
        <button type="submit" :class="terminalAction.kind === 'reject' ? 'bg-rose-600 hover:bg-rose-700' : 'bg-slate-800 hover:bg-slate-900'" class="h-11 rounded-xl px-4 text-sm font-semibold text-white">
          Confirm {{ terminalAction.kind === "reject" ? "Rejection" : "Withdrawal" }}
        </button>
      </form>
    </div>
  </CockpitLayout>
</template>
