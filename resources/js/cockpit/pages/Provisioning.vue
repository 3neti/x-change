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

type Profile = { value: string; label: string; description: string };
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
type ProvisioningRequest = {
  reference: string;
  profile: string;
  profile_label: string;
  status: string;
  commissioning: boolean;
  purpose: string;
  required_evidence: string[];
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
  };
  events: { type: string; occurred_at: string | null }[];
  actions: { approve: string; reject: string; withdraw: string; issue: string };
  created_at: string | null;
};
type OneTimeOffer = {
  request_reference: string;
  offer_reference: string;
  claim_url: string;
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
const terminalAction = ref<null | {
  kind: "reject" | "withdraw";
  url: string;
  label: string;
}>(null);
const terminalReason = ref("");
const form = reactive({ seat_reference: "", profile: "", purpose: "" });

const vacantSeats = computed(() =>
  props.provisioning.seats.filter((seat) => seat.status === "vacant"),
);

function createRequest(): void {
  errors.value = {};
  router.post(
    props.provisioningStoreUrl,
    {
      seat_reference: form.seat_reference || null,
      profile: form.seat_reference ? null : form.profile,
      purpose: form.purpose,
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

function finishOfferCeremony(): void {
  oneTimeOffer.value = null;
  copied.value = false;
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
          <select v-model="form.seat_reference" class="h-10 rounded-xl border border-input bg-background px-3">
            <option value="">No seat · choose a profile</option>
            <option v-for="seat in vacantSeats" :key="seat.reference" :value="seat.reference">
              {{ seat.label }}
            </option>
          </select>
        </label>
        <label v-if="!form.seat_reference" class="grid gap-1.5 text-sm font-medium">
          Authority Profile
          <select v-model="form.profile" required class="h-10 rounded-xl border border-input bg-background px-3">
            <option value="" disabled>Choose a profile</option>
            <option v-for="profile in provisioning.profiles" :key="profile.value" :value="profile.value">
              {{ profile.label }}
            </option>
          </select>
        </label>
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
        <button type="button" class="h-10 rounded-xl border border-border text-sm font-medium" @click="finishOfferCeremony">
          I Have Saved The Link
        </button>
      </div>
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
