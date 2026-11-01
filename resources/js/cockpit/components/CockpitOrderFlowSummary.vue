<script setup lang="ts">
import { computed } from 'vue';

const props = defineProps<{
  voucherType: 'redeemable' | 'payable' | 'settlement';
  amount: string;
  targetAmount: string;
  destination: string;
  defaultDestination: string;
  feeBearer: string;
  repayment: boolean;
  repaymentAmount: string;
  philhealth: boolean;
}>();

const money = (value: string) => {
  const amount = Number(value);
  return Number.isFinite(amount) && amount > 0
    ? new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' }).format(amount)
    : 'the specified amount';
};
const destinationLabel = computed(() => {
  if (props.philhealth && !props.destination) return 'Choose a hospital bank or wallet';
  if (!props.destination) return props.defaultDestination || 'Client Funds';
  const account = /^instapay\.([a-z0-9_-]+)\.(?:mobile|account):([0-9]{6,34})$/i.exec(props.destination);
  if (account) return `${account[1].toUpperCase()} · •••• ${account[2].slice(-4)}`;
  if (props.destination.startsWith('account.mobile:')) return 'Client Funds';
  return 'Complete destination details';
});
const steps = computed(() => {
  if (props.philhealth) return [
    'Patient provides the required evidence',
    'Hospital reviews the settlement',
    'Wait for review, then receive the verified payment',
    props.destination ? `${destinationLabel.value} receives ${money(props.targetAmount)} according to the settlement rules` : 'Choose the hospital payout destination',
  ];
  if (props.voucherType === 'payable') return [
    `Customer pays ${money(props.amount)}`,
    `Payment goes to ${destinationLabel.value}`,
  ];
  if (props.voucherType === 'settlement' && props.repayment) return [
    `Recipient claims ${money(props.amount)}`,
    `After disbursement, collect ${money(props.repaymentAmount)} per payment`,
    `Collect ${money(props.targetAmount)} in total to ${destinationLabel.value}`,
  ];
  if (props.voucherType === 'settlement') return [
    `Settlement amount: ${money(props.amount)}`,
    `Collection target: ${money(props.targetAmount)}`,
    `Collection destination: ${destinationLabel.value}`,
    'Settlement follows the configured conditions',
  ];
  return [`Recipient can claim ${money(props.amount)}`];
});
</script>

<template>
  <section aria-label="Pay Code flow" class="min-w-0 rounded-2xl border border-emerald-200 bg-emerald-50/60 p-4 dark:border-emerald-900 dark:bg-emerald-950/20" data-testid="cockpit-order-flow-summary">
    <p class="text-xs font-semibold uppercase tracking-wide text-emerald-800 dark:text-emerald-200">What will happen</p>
    <ol class="mt-3 grid gap-2 text-sm text-slate-800 dark:text-slate-100">
      <li v-for="(step, index) in steps" :key="index" class="flex min-w-0 gap-3">
        <span class="flex size-6 shrink-0 items-center justify-center rounded-full bg-white text-xs font-semibold text-emerald-800 dark:bg-slate-900 dark:text-emerald-200">{{ index + 1 }}</span>
        <span class="min-w-0 pt-0.5 leading-5">{{ step }}</span>
      </li>
    </ol>
    <p v-if="voucherType !== 'redeemable'" class="mt-3 text-xs text-slate-600 dark:text-slate-300">Receiving account: <span class="font-semibold" data-testid="cockpit-quick-generate-collection-destination-label">{{ destinationLabel }}</span></p>
    <p v-if="voucherType !== 'redeemable'" class="mt-3 border-t border-emerald-200 pt-3 text-xs text-slate-600 dark:border-emerald-900 dark:text-slate-300">
      {{ feeBearer === 'payer' ? 'Customer pays the applicable service fee with the payment.' : 'Issuer pays the applicable service fee at issuance.' }}
    </p>
  </section>
</template>
