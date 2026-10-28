<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import BankEMISelect from './BankEMISelect.vue';

const props = withDefaults(defineProps<{
    modelValue?: string;
    disabled?: boolean;
    storedValue?: boolean;
}>(), { modelValue: '', disabled: false, storedValue: false });
const emit = defineEmits<{ 'update:modelValue': [value: string] }>();
const external = ref(false);
const bank = ref('');
const account = ref('');
const clientFundsMobile = computed(() => /^account\.mobile:(09[0-9]{9})\.client_funds$/.exec(props.modelValue)?.[1]);
const address = computed(() => external.value ? `instapay.${bank.value.toLowerCase()}.account:${account.value}` : '');

watch(() => props.modelValue, value => {
    if (value === address.value) return;
    const match = /^instapay\.([a-z0-9_-]+)\.(?:mobile|account):([0-9]*)$/i.exec(value);
    external.value = Boolean(value) && !value.startsWith('account.mobile:');
    bank.value = match?.[1]?.toUpperCase() ?? '';
    account.value = match?.[2] ?? '';
}, { immediate: true });

function update(): void {
    emit('update:modelValue', address.value);
}
function setAccount(event: Event): void {
    account.value = (event.target as HTMLInputElement).value.replace(/\D/g, '');
    update();
}
</script>

<template>
    <fieldset class="grid min-w-0 gap-3 border-t border-slate-200 pt-4 dark:border-slate-800" data-testid="value-flow-destination" :disabled="disabled">
        <legend class="text-xs font-semibold text-slate-700 dark:text-slate-200">Where should the value go?</legend>
        <select v-model="external" aria-label="Value destination" class="h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm text-slate-900 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100" @change="update">
            <option :value="false">{{ clientFundsMobile ? `Client Funds · ${clientFundsMobile}` : 'Client Funds' }}</option>
            <option :value="true">Bank / Wallet</option>
        </select>
        <div v-if="external" class="grid min-w-0 gap-3">
            <BankEMISelect v-model="bank" settlement-rail="INSTAPAY" :disabled="disabled" @update:model-value="update" />
            <label class="grid gap-1.5 text-xs font-medium text-slate-700 dark:text-slate-200">
                Account Number
                <input :value="account" inputmode="numeric" autocomplete="off" maxlength="34" placeholder="Enter account number" class="h-14 w-full rounded-xl border border-slate-300 bg-white px-3 text-center text-2xl font-semibold tracking-widest text-slate-950 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-50" @input="setAccount" />
            </label>
            <p class="text-xs leading-relaxed text-slate-600 dark:text-slate-400">{{ storedValue ? 'Every spend from this Pay Code goes to this account via InstaPay.' : 'Payments collected through this Pay Code go to this account via InstaPay.' }}</p>
        </div>
    </fieldset>
</template>
