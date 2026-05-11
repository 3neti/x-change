<script setup lang="ts">
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

interface Props {
    instructions?: Record<string, any> | null;
}

defineProps<Props>();
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle class="text-base">Instruction Preview</CardTitle>
        </CardHeader>

        <CardContent>
            <pre class="max-h-80 overflow-auto rounded-md bg-muted p-3 text-xs">{{ JSON.stringify(instructions ?? {}, null, 2) }}</pre>
        </CardContent>
    </Card>
</template>
