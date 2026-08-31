<script setup lang="ts">
import { computed, ref } from 'vue';
import { Form, Head } from '@inertiajs/vue3';
import { CircleCheck, CircleOff, KeyRound, Landmark, ShieldCheck } from 'lucide-vue-next';
import CampaignPayoutRecoveryController from '@/actions/LBHurtado/XChange/Http/Controllers/Web/Claim/CampaignPayoutRecoveryController';
import ClaimStepShell from '@/components/x-change/ClaimStepShell.vue';
import BankEMISelect from '@/components/financial/BankEMISelect.vue';

defineOptions({ layout: null });

const props = defineProps<{
    code: string;
    status: string;
    amount: {
        minor: number;
        currency: string;
    };
    settlement_rail: string;
    expires_at?: string | null;
    otp_expires_at?: string | null;
    notice?: string | null;
}>();

const bankCode = ref('');

const routeParameters = computed(() => ({
    code: props.code,
}));

const formattedAmount = computed(() =>
    new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: props.amount.currency || 'PHP',
    }).format(props.amount.minor / 100),
);

const isUnavailable = computed(() =>
    ['expired', 'locked', 'identity_changed'].includes(props.status),
);
</script>

<template>
    <Head :title="`Claim Pay Code ${code}`" />

    <ClaimStepShell
        width="lg"
        :centered="false"
        :tone="status === 'consumed' ? 'success' : isUnavailable ? 'danger' : 'warning'"
    >
        <div class="space-y-5" data-testid="campaign-payout-recovery-page">
            <header class="space-y-2">
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-muted-foreground">
                    Claim protected value
                </p>
                <h1 class="text-2xl font-bold tracking-tight">Pay Code {{ code }}</h1>
                <p class="text-sm leading-6 text-muted-foreground">
                    The bank could not complete the original payout. The value remains protected.
                    Verify the beneficiary mobile to claim it using a corrected destination.
                </p>
            </header>

            <div class="rounded-xl border border-border/70 bg-muted/35 p-4">
                <div>
                    <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Protected value</p>
                    <p class="mt-1 text-xl font-bold" data-testid="recovery-amount">{{ formattedAmount }}</p>
                </div>
            </div>

            <p
                v-if="notice"
                class="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-800"
                data-testid="recovery-notice"
            >
                {{ notice }}
            </p>

            <section v-if="status === 'available'" class="space-y-4" data-testid="recovery-start-step">
                <div class="flex items-start gap-3">
                    <ShieldCheck class="mt-0.5 h-5 w-5 shrink-0 text-amber-600" />
                    <p class="text-sm leading-6 text-muted-foreground">
                        Request a one-time code. It will be sent only to the beneficiary mobile saved
                        in the approved campaign.
                    </p>
                </div>
                <Form
                    v-bind="CampaignPayoutRecoveryController.start.form(routeParameters)"
                    #default="{ processing }"
                >
                    <button
                        type="submit"
                        :disabled="processing"
                        class="inline-flex min-h-11 w-full items-center justify-center rounded-xl bg-primary px-4 text-sm font-semibold text-primary-foreground disabled:opacity-60"
                        data-testid="recovery-request-code"
                    >
                        {{ processing ? 'Sending code…' : 'Send verification code' }}
                    </button>
                </Form>
            </section>

            <section v-else-if="status === 'otp_pending'" class="space-y-4" data-testid="recovery-otp-step">
                <div class="flex items-start gap-3">
                    <KeyRound class="mt-0.5 h-5 w-5 shrink-0 text-amber-600" />
                    <p class="text-sm leading-6 text-muted-foreground">
                        Enter the six-digit code sent to the approved beneficiary mobile. The code
                        cannot authorize a different Pay Code or beneficiary.
                    </p>
                </div>
                <Form
                    v-bind="CampaignPayoutRecoveryController.verify.form(routeParameters)"
                    reset-on-success
                    #default="{ errors, processing }"
                    class="space-y-3"
                >
                    <label class="grid gap-1.5 text-sm font-semibold">
                        Verification code
                        <input
                            name="code"
                            inputmode="numeric"
                            autocomplete="one-time-code"
                            maxlength="6"
                            pattern="[0-9]{6}"
                            class="h-12 rounded-xl border border-input bg-background px-4 text-center text-xl font-bold tracking-[0.35em] outline-none focus:ring-2 focus:ring-primary"
                            data-testid="recovery-otp-code"
                        />
                        <span v-if="errors.code" class="text-xs font-normal text-destructive">{{ errors.code }}</span>
                    </label>
                    <button
                        type="submit"
                        :disabled="processing"
                        class="inline-flex min-h-11 w-full items-center justify-center rounded-xl bg-primary px-4 text-sm font-semibold text-primary-foreground disabled:opacity-60"
                        data-testid="recovery-verify-code"
                    >
                        {{ processing ? 'Verifying…' : 'Verify mobile' }}
                    </button>
                </Form>
            </section>

            <section v-else-if="status === 'verified'" class="space-y-4" data-testid="recovery-destination-step">
                <div class="flex items-start gap-3">
                    <Landmark class="mt-0.5 h-5 w-5 shrink-0 text-emerald-600" />
                    <p class="text-sm leading-6 text-muted-foreground">
                        Enter the corrected bank or wallet destination. Submitting creates one
                        correction attempt for the same Pay Code; it does not issue another voucher.
                    </p>
                </div>
                <Form
                    v-bind="CampaignPayoutRecoveryController.submit.form(routeParameters)"
                    #default="{ errors, processing }"
                    class="space-y-3"
                >
                    <label class="grid gap-1.5 text-sm font-semibold">
                        Bank or wallet
                        <input type="hidden" name="bank_code" :value="bankCode" />
                        <BankEMISelect
                            v-model="bankCode"
                            :settlement-rail="settlement_rail"
                            :disabled="processing"
                        />
                        <span v-if="errors.bank_code" class="text-xs font-normal text-destructive">{{ errors.bank_code }}</span>
                    </label>
                    <label class="grid gap-1.5 text-sm font-semibold">
                        Account number
                        <input
                            name="account_number"
                            inputmode="numeric"
                            autocomplete="off"
                            class="h-12 rounded-xl border border-input bg-background px-4 text-base outline-none focus:ring-2 focus:ring-primary"
                            data-testid="recovery-account-number"
                        />
                        <span v-if="errors.account_number" class="text-xs font-normal text-destructive">{{ errors.account_number }}</span>
                    </label>
                    <label class="grid gap-1.5 text-sm font-semibold">
                        Contact mobile <span class="font-normal text-muted-foreground">(optional)</span>
                        <input
                            name="mobile"
                            inputmode="tel"
                            autocomplete="tel"
                            class="h-12 rounded-xl border border-input bg-background px-4 text-base outline-none focus:ring-2 focus:ring-primary"
                        />
                        <span v-if="errors.mobile" class="text-xs font-normal text-destructive">{{ errors.mobile }}</span>
                    </label>
                    <button
                        type="submit"
                        :disabled="processing || bankCode === ''"
                        class="inline-flex min-h-11 w-full items-center justify-center rounded-xl bg-primary px-4 text-sm font-semibold text-primary-foreground disabled:opacity-60"
                        data-testid="recovery-submit-destination"
                    >
                        {{ processing ? 'Submitting correction…' : `Send ${formattedAmount}` }}
                    </button>
                </Form>
            </section>

            <section v-else-if="status === 'submitting'" class="space-y-3 text-center" data-testid="recovery-submitting-step">
                <ShieldCheck class="mx-auto h-9 w-9 text-amber-600" />
                <h2 class="text-lg font-bold">Correction is being verified</h2>
                <p class="text-sm leading-6 text-muted-foreground">
                    Do not submit another destination. An operator can reconcile an uncertain provider outcome safely.
                </p>
            </section>

            <section v-else-if="status === 'consumed'" class="space-y-3 text-center" data-testid="recovery-complete-step">
                <CircleCheck class="mx-auto h-10 w-10 text-emerald-600" />
                <h2 class="text-xl font-bold">Correction submitted</h2>
                <p class="text-sm leading-6 text-muted-foreground">
                    This Pay Code claim has been completed. The Pay Code remains the canonical payout record.
                </p>
            </section>

            <section v-else class="space-y-3 text-center" data-testid="recovery-unavailable-step">
                <CircleOff class="mx-auto h-10 w-10 text-destructive" />
                <h2 class="text-xl font-bold">Recovery unavailable</h2>
                <p class="text-sm leading-6 text-muted-foreground">
                    This claim is expired, locked, or no longer matches the approved beneficiary. Contact the sender.
                </p>
            </section>
        </div>
    </ClaimStepShell>
</template>
