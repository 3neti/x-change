<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import {
    challenge,
    verify,
} from '@/routes/x-change/onboarding/mobile-verification';

type Challenge = {
    status: string;
    expires_at?: string | null;
    attempts: number;
};

defineProps<{
    mobile: string;
    verified: boolean;
    challenge?: Challenge | null;
    local_code?: string | null;
    status?: string | null;
}>();

const challengeForm = useForm({});
const verifyForm = useForm({
    code: '',
});

function requestCode(): void {
    challengeForm.post(challenge(), {
        preserveScroll: true,
    });
}

function submitCode(): void {
    verifyForm.post(verify(), {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head title="Verify mobile" />

    <main
        class="min-h-screen bg-slate-100 px-4 py-10 text-slate-950 dark:bg-slate-950 dark:text-white"
    >
        <section
            class="mx-auto max-w-lg overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900"
            data-testid="mobile-verification-page"
        >
            <header class="bg-slate-950 px-6 py-6 text-white">
                <p
                    class="text-xs font-semibold tracking-[0.18em] text-sky-300 uppercase"
                >
                    Secure onboarding
                </p>
                <h1 class="mt-2 text-2xl font-semibold">Verify your mobile</h1>
                <p class="mt-2 text-sm leading-6 text-slate-300">
                    QR Ph funding can resolve an Account only from a mobile
                    number that was verified here first.
                </p>
            </header>

            <div class="space-y-5 p-6">
                <div
                    v-if="status"
                    class="rounded-lg bg-emerald-50 px-3 py-2 text-sm text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-200"
                    role="status"
                >
                    {{ status }}
                </div>

                <div
                    class="flex items-center justify-between gap-4 rounded-xl border border-slate-200 px-4 py-3 dark:border-slate-800"
                >
                    <div>
                        <p class="text-xs text-slate-500">Mobile identity</p>
                        <p class="mt-1 font-semibold">{{ mobile }}</p>
                    </div>
                    <span
                        class="rounded-full px-2.5 py-1 text-xs font-semibold"
                        :class="
                            verified
                                ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-200'
                                : 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-200'
                        "
                    >
                        {{ verified ? 'Verified' : 'Verification required' }}
                    </span>
                </div>

                <div v-if="!verified" class="space-y-4">
                    <button
                        type="button"
                        class="h-11 w-full rounded-lg border border-sky-300 bg-sky-50 px-4 text-sm font-semibold text-sky-800 transition hover:bg-sky-100 disabled:cursor-not-allowed disabled:opacity-50 dark:border-sky-900 dark:bg-sky-950/40 dark:text-sky-200"
                        :disabled="challengeForm.processing"
                        data-testid="request-mobile-verification"
                        @click="requestCode"
                    >
                        {{
                            challengeForm.processing
                                ? 'Requesting code…'
                                : challenge?.status === 'pending'
                                  ? 'Request a new code'
                                  : 'Send verification code'
                        }}
                    </button>

                    <form class="space-y-3" @submit.prevent="submitCode">
                        <label class="block">
                            <span
                                class="text-xs font-semibold tracking-wide text-slate-500 uppercase"
                            >
                                Six-digit code
                            </span>
                            <input
                                v-model="verifyForm.code"
                                inputmode="numeric"
                                autocomplete="one-time-code"
                                maxlength="6"
                                placeholder="000000"
                                class="mt-1.5 h-12 w-full rounded-lg border border-slate-300 bg-white px-3 text-center font-mono text-xl tracking-[0.35em] outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 dark:border-slate-700 dark:bg-slate-950"
                                data-testid="mobile-verification-code"
                            />
                            <span
                                v-if="verifyForm.errors.code"
                                class="mt-1 block text-xs text-rose-600"
                            >
                                {{ verifyForm.errors.code }}
                            </span>
                        </label>
                        <button
                            type="submit"
                            class="h-11 w-full rounded-lg bg-sky-600 px-4 text-sm font-semibold text-white transition hover:bg-sky-700 disabled:cursor-not-allowed disabled:opacity-50"
                            :disabled="
                                verifyForm.processing ||
                                verifyForm.code.length !== 6
                            "
                            data-testid="submit-mobile-verification"
                        >
                            {{
                                verifyForm.processing
                                    ? 'Verifying…'
                                    : 'Verify mobile'
                            }}
                        </button>
                    </form>

                    <p
                        v-if="local_code"
                        class="rounded-lg bg-violet-50 px-3 py-2 text-xs leading-5 text-violet-800 dark:bg-violet-950/40 dark:text-violet-200"
                    >
                        Local simulation code:
                        <strong class="font-mono">{{ local_code }}</strong
                        >. This hint is never available in production.
                    </p>
                </div>

                <p
                    v-else
                    class="text-sm leading-6 text-emerald-700 dark:text-emerald-300"
                >
                    Your mobile is verified and can now resolve your Account
                    during provider-confirmed QR Ph funding.
                </p>

                <p class="text-xs leading-5 text-slate-500">
                    A webhook never creates a user and never verifies a mobile.
                    Unknown payer mobiles stop before Funding Intent creation or
                    Account credit.
                </p>
            </div>
        </section>
    </main>
</template>
