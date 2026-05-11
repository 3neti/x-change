<script setup lang="ts">
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

interface Props {
    modelValue?: Record<string, any>;
}

defineProps<Props>();

defineEmits<{
    'update:modelValue': [value: Record<string, any>];
}>();
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle class="text-base">Basic Pay Code Details</CardTitle>
        </CardHeader>

        <CardContent>
            <p class="text-sm text-muted-foreground">
                Basic generation fields will be wired in Phase 4.
            </p>
        </CardContent>
    </Card>
</template>
