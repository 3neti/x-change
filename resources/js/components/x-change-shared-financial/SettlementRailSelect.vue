<script setup lang="ts">
import { computed } from 'vue';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { AlertTriangle, Info } from 'lucide-vue-next';
import { isEMI, AMOUNT_LIMITS } from '@/config/bank-restrictions';

interface Props {
    modelValue?: string | null;
    amount?: number;
    bankCode?: string | null;
    disabled?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    modelValue: null,
    amount: 0,
    bankCode: null,
    disabled: false,
});

const emit = defineEmits<{
    'update:modelValue': [value: string | null];
}>();

const localValue = computed({
    get: () => props.modelValue || 'auto',
    set: (value) => emit('update:modelValue', value === 'auto' ? null : value),
});

// Determine actual rail based on auto logic
const effectiveRail = computed(() => {
    if (props.modelValue && props.modelValue !== 'auto') {
        return props.modelValue;
    }
    return props.amount < AMOUNT_LIMITS.INSTAPAY.max ? 'INSTAPAY' : 'PESONET';
});

// Fee information
const fees = { INSTAPAY: 10, PESONET: 25 };
const estimatedFee = computed(() => fees[effectiveRail.value as keyof typeof fees] || 10);

// Validation for EMIs and PESONET
const validation = computed(() => {
    const rail = effectiveRail.value;
    const amount = props.amount;
    
    if (rail === 'PESONET') {
        const bankIsEMI = isEMI(props.bankCode);
        
        if (amount > AMOUNT_LIMITS.INSTAPAY.max) {
            return {
                type: 'warning',
                message: `Note: EMIs (GCash, PayMaya, etc.) do not support PESONET. Redeemers with EMI accounts cannot claim this voucher as the amount (₱${amount.toLocaleString()}) exceeds the INSTAPAY limit (₱${AMOUNT_LIMITS.INSTAPAY.max.toLocaleString()}).`,
            };
        }
        
        if (bankIsEMI || !props.bankCode) {
            return {
                type: 'info',
                message: 'Note: EMIs (GCash, PayMaya, etc.) do not support PESONET. Redeemers with EMI accounts will need to provide a traditional bank account.',
            };
        }
    }
    
    return null;
});
</script>

<template>
    <div class="space-y-2">
        <Select v-model="localValue" :disabled="disabled">
            <SelectTrigger>
                <SelectValue placeholder="Select settlement rail" />
            </SelectTrigger>
            <SelectContent>
                <SelectItem value="auto">
                    Auto (based on amount)
                </SelectItem>
                <SelectItem value="INSTAPAY">
                    INSTAPAY (Real-time, ₱10 fee, <₱50k)
                </SelectItem>
                <SelectItem value="PESONET">
                    PESONET (Next-day, ₱25 fee, up to ₱1M)
                </SelectItem>
            </SelectContent>
        </Select>
        
        <p class="text-xs text-muted-foreground">
            Selected: <strong>{{ effectiveRail }}</strong> · Est. fee: ₱{{ estimatedFee }}
        </p>
        
        <!-- Validation warning -->
        <Alert
            v-if="validation"
            :variant="validation.type === 'warning' ? 'destructive' : 'default'"
        >
            <AlertTriangle v-if="validation.type === 'warning'" class="h-4 w-4" />
            <Info v-else class="h-4 w-4" />
            <AlertDescription class="text-sm">
                {{ validation.message }}
            </AlertDescription>
        </Alert>
    </div>
</template>
