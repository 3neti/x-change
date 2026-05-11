<script setup lang="ts">
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

interface Props {
    form?: Record<string, any>;
}

defineProps<Props>();
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle class="text-base">Cost Estimate</CardTitle>
        </CardHeader>

        <CardContent>
            <p class="text-sm text-muted-foreground">
                Cost estimate will be wired to the generation form in Phase 4.
            </p>
        </CardContent>
    </Card>
</template>
