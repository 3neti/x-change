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

    const fields: any[] = [];

    if (props.form?.require_mobile !== false) {
        fields.push({
            name: 'mobile',
            type: 'tel',
            label: 'Mobile Number',
            required: true,
            persist: true,
        });
    }

    if (props.form?.require_bank_account !== false) {
        fields.push({
            name: 'bank_code',
            type: 'bank_account',
            label: 'Bank',
            required: true,
        });

        fields.push({
            name: 'account_number',
            type: 'text',
            label: 'Account Number',
            required: true,
        });
    }

    return {
        amount: props.form?.amount ?? null,
        quantity: props.form?.quantity ?? 1,
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
        rider: {
            message: props.form?.rider_message || null,
            url: props.form?.rider_url || null,
        },
        splash: {
            enabled: props.form?.splash_enabled === true,
            timeout: props.form?.splash_timeout ?? null,
            title: props.form?.splash_title || null,
            content: props.form?.splash_content || null,
        },
        code: {
            prefix: props.form?.prefix || null,
            mask: props.form?.mask || null,
            length: props.form?.code_length || null,
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
