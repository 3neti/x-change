<script setup lang="ts">
import { computed } from 'vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';

interface Props {
    form?: Record<string, any>;
    instructions?: Record<string, any> | null;
}

const props = withDefaults(defineProps<Props>(), {
    form: () => ({}),
    instructions: null,
});

const preview = computed(() => {
    if (props.instructions) {
        return props.instructions;
    }

    const fields: string[] = [];

    if (props.form?.require_mobile !== false) fields.push('mobile');

    if (props.form?.require_name === true) fields.push('name');
    if (props.form?.require_email === true) fields.push('email');
    if (props.form?.require_birth_date === true) fields.push('birth_date');
    if (props.form?.require_address === true) fields.push('address');
    if (props.form?.require_reference_code === true) fields.push('reference_code');
    if (props.form?.require_gross_monthly_income === true) fields.push('gross_monthly_income');

    if (props.form?.require_kyc === true) fields.push('kyc');
    if (props.form?.require_otp === true) fields.push('otp');
    if (props.form?.require_location === true) fields.push('location');
    if (props.form?.require_selfie === true) fields.push('selfie');
    if (props.form?.require_signature === true) fields.push('signature');

    return {
        amount: props.form?.amount ?? null,
        quantity: props.form?.quantity ?? 1,

        cash: {
            validation: {
                secret: props.form?.validation_secret ? 'configured' : null,
            },
        },

        inputs: {
            fields,
        },

        evidence: {
            kyc: props.form?.require_kyc === true,
            otp: props.form?.require_otp === true,
            location: props.form?.require_location === true,
            selfie: props.form?.require_selfie === true,
            signature: props.form?.require_signature === true,
        },

        feedback: {
            email: null,
            mobile: null,
            webhook: null,
        },

        rider: {
            message: props.form?.rider_message || null,
            url: props.form?.rider_url || null,
            splash: props.form?.splash_enabled ? props.form?.splash_content || null : null,
            splash_timeout: props.form?.splash_enabled ? props.form?.splash_timeout ?? null : null,
        },

        code: {
            prefix: props.form?.prefix || null,
            mask: props.form?.mask || null,
            length: props.form?.code_length || null,
        },

        timing: {
            starts_at: props.form?.starts_at || null,
            expires_at: props.form?.expires_at || null,
            ttl_minutes: props.form?.ttl_minutes || null,
        },
    };
});

const activeEvidence = computed(() => {
    const evidence = preview.value.evidence ?? {};

    return Object.entries(evidence)
        .filter(([, enabled]) => enabled === true)
        .map(([key]) => key.toUpperCase());
});
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle class="text-base">Instruction Preview</CardTitle>
        </CardHeader>

        <CardContent class="space-y-4">
            <div class="space-y-2">
                <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">
                    Evidence
                </p>

                <div v-if="activeEvidence.length" class="flex flex-wrap gap-2">
                    <Badge
                        v-for="item in activeEvidence"
                        :key="item"
                        variant="secondary"
                    >
                        {{ item }}
                    </Badge>
                </div>

                <p v-else class="text-sm text-muted-foreground">
                    No additional evidence required.
                </p>
            </div>

            <pre class="max-h-96 overflow-auto rounded-md bg-muted p-3 text-xs">{{ JSON.stringify(preview, null, 2) }}</pre>
        </CardContent>
    </Card>
</template>
