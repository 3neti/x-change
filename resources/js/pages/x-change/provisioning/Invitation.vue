<script setup lang="ts">
import { Head, Link, router } from "@inertiajs/vue3";
import { CheckCircle2, Clock3, ShieldCheck } from "lucide-vue-next";
import { ref } from "vue";

const props = defineProps<{
  invitation: {
    profile: string;
    label: string;
    purpose: string;
    status: string;
    required_evidence: string[];
    expires_at: string | null;
    authenticated: boolean;
    can_accept: boolean;
    accept_url: string;
    login_url: string;
  };
}>();

const acceptedResponsibility = ref(false);
const processing = ref(false);

function accept(): void {
  if (!acceptedResponsibility.value || processing.value) return;
  router.post(
    props.invitation.accept_url,
    { responsibility_attestation: true },
    {
      preserveScroll: true,
      onStart: () => (processing.value = true),
      onFinish: () => (processing.value = false),
    },
  );
}

function formatDate(value: string | null): string {
  if (!value) return "Not specified";
  return new Intl.DateTimeFormat("en-PH", {
    dateStyle: "long",
    timeStyle: "short",
  }).format(new Date(value));
}
</script>

<template>
  <div class="min-h-screen bg-slate-100 px-4 py-8 text-slate-950 dark:bg-slate-950 dark:text-slate-50">
    <Head title="Provisioning Invitation" />
    <main class="mx-auto grid w-full max-w-2xl gap-5">
      <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-xl dark:border-slate-800 dark:bg-slate-900 sm:p-8">
        <div class="flex items-center gap-3">
          <div class="grid size-11 place-items-center rounded-2xl bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300">
            <ShieldCheck class="size-6" />
          </div>
          <div class="min-w-0">
            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-emerald-700 dark:text-emerald-300">Governed Provisioning</p>
            <h1 class="truncate text-xl font-semibold">{{ invitation.label }}</h1>
          </div>
        </div>

        <p class="mt-5 text-sm leading-6 text-slate-600 dark:text-slate-300">{{ invitation.purpose }}</p>

        <dl class="mt-6 grid gap-3 rounded-2xl bg-slate-50 p-4 text-sm dark:bg-slate-950/60">
          <div class="flex items-start justify-between gap-4">
            <dt class="text-slate-500">Profile</dt>
            <dd class="text-right font-medium capitalize">{{ invitation.profile.replaceAll("_", " ") }}</dd>
          </div>
          <div class="flex items-start justify-between gap-4">
            <dt class="text-slate-500">Status</dt>
            <dd class="text-right font-medium capitalize">{{ invitation.status.replaceAll("_", " ") }}</dd>
          </div>
          <div class="flex items-start justify-between gap-4">
            <dt class="text-slate-500">Expires</dt>
            <dd class="text-right font-medium">{{ formatDate(invitation.expires_at) }}</dd>
          </div>
        </dl>

        <div class="mt-6">
          <h2 class="text-sm font-semibold">Evidence required</h2>
          <div class="mt-2 flex flex-wrap gap-2">
            <span v-for="field in invitation.required_evidence" :key="field" class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium capitalize dark:bg-slate-800">
              {{ field.replaceAll("_", " ") }}
            </span>
          </div>
        </div>

        <div v-if="invitation.status === 'activation_pending' || invitation.status === 'activated'" class="mt-6 rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-900 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-100">
          <div class="flex items-center gap-2 font-semibold"><CheckCircle2 class="size-4" /> Invitation accepted</div>
          <p class="mt-1">The verified acceptance is recorded. Domain authority remains subject to its controlled activation gate.</p>
        </div>

        <template v-else>
          <Link v-if="!invitation.authenticated" :href="invitation.login_url" class="mt-6 inline-flex h-11 w-full items-center justify-center rounded-xl bg-emerald-600 px-4 text-sm font-semibold text-white hover:bg-emerald-700">
            Sign In Or Create Account
          </Link>
          <div v-else-if="invitation.can_accept" class="mt-6 grid gap-4">
            <label class="flex cursor-pointer items-start gap-3 rounded-2xl border border-slate-200 p-4 dark:border-slate-800">
              <input v-model="acceptedResponsibility" type="checkbox" class="mt-0.5 size-4 rounded border-slate-300" />
              <span class="text-sm leading-6">I understand and accept the responsibilities in this approved authority envelope.</span>
            </label>
            <button type="button" :disabled="!acceptedResponsibility || processing" class="h-11 rounded-xl bg-emerald-600 px-4 text-sm font-semibold text-white disabled:opacity-40" @click="accept">
              {{ processing ? "Recording Acceptance…" : "Accept Responsibility" }}
            </button>
          </div>
          <div v-else class="mt-6 flex items-start gap-2 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-100">
            <Clock3 class="mt-0.5 size-4 shrink-0" /> This invitation is not currently available for acceptance.
          </div>
        </template>
      </section>
      <p class="text-center text-xs text-slate-500">Accepting authority never creates liquidity or moves money.</p>
    </main>
  </div>
</template>
