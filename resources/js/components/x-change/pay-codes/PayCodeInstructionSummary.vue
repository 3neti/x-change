<script setup lang="ts">
import { computed } from 'vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Separator } from '@/components/ui/separator';

interface Props {
    instructions?: Record<string, any> | null;
}

const props = defineProps<Props>();

const inputs = computed(() => props.instructions?.inputs?.fields ?? []);
const validation = computed(() => props.instructions?.validation ?? {});
const rider = computed(() => props.instructions?.rider ?? {});
const feedback = computed(() => props.instructions?.feedback ?? {});
const evidence = computed(() => {
    const result: string[] = [];

    if (props.instructions?.kyc?.required || props.instructions?.requires_kyc) result.push('KYC');
    if (props.instructions?.otp?.required || props.instructions?.requires_otp) result.push('OTP');
    if (props.instructions?.location?.required || props.instructions?.requires_location) result.push('Location');
    if (props.instructions?.selfie?.required || props.instructions?.requires_selfie) result.push('Selfie');
    if (props.instructions?.signature?.required || props.instructions?.requires_signature) result.push('Signature');

    return result;
});
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle class="text-base">Instructions</CardTitle>
        </CardHeader>

        <CardContent class="space-y-5">
            <div v-if="inputs.length" class="space-y-2">
                <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">
                    Required Inputs
                </p>

                <div class="flex flex-wrap gap-2">
                    <Badge
                        v-for="field in inputs"
                        :key="field.name ?? field"
                        variant="secondary"
                    >
                        {{ field.label ?? field.name ?? field }}
                    </Badge>
                </div>
            </div>

            <Separator v-if="inputs.length && evidence.length" />

            <div v-if="evidence.length" class="space-y-2">
                <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">
                    Evidence
                </p>

                <div class="flex flex-wrap gap-2">
                    <Badge
                        v-for="item in evidence"
                        :key="item"
                        variant="outline"
                    >
                        {{ item }}
                    </Badge>
                </div>
            </div>

            <Separator v-if="Object.keys(validation).length" />

            <div v-if="Object.keys(validation).length" class="space-y-2">
                <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">
                    Validation
                </p>

                <pre class="max-h-48 overflow-auto rounded-md bg-muted p-3 text-xs">{{ JSON.stringify(validation, null, 2) }}</pre>
            </div>

            <Separator v-if="rider?.message || rider?.url" />

            <div v-if="rider?.message || rider?.url" class="space-y-2">
                <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">
                    Rider
                </p>

                <p v-if="rider.message" class="text-sm">
                    {{ rider.message }}
                </p>

                <p v-if="rider.url" class="break-all font-mono text-xs text-muted-foreground">
                    {{ rider.url }}
                </p>
            </div>

            <Separator v-if="Object.keys(feedback).length" />

            <div v-if="Object.keys(feedback).length" class="space-y-2">
                <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">
                    Feedback
                </p>

                <pre class="max-h-48 overflow-auto rounded-md bg-muted p-3 text-xs">{{ JSON.stringify(feedback, null, 2) }}</pre>
            </div>
        </CardContent>
    </Card>
</template>
