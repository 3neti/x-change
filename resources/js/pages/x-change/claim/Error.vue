<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { AlertCircle } from 'lucide-vue-next';

defineOptions({ layout: null });

defineProps<{
    message: string;
    code?: string;
}>();
</script>

<template>
    <Head title="Claim Error" />

    <div class="flex min-h-svh flex-col items-center justify-center gap-6 bg-gradient-to-b from-destructive/5 via-background to-background p-6">
        <div class="w-full max-w-sm">
            <Card>
                <CardHeader class="text-center">
                    <AlertCircle class="mx-auto h-12 w-12 text-destructive" />
                    <CardTitle class="mt-4">Unable to Claim</CardTitle>
                </CardHeader>
                <CardContent class="space-y-4 text-center">
                    <p class="text-sm text-muted-foreground">{{ message }}</p>
                    <p v-if="code" class="font-mono text-xs text-muted-foreground">Code: {{ code }}</p>
                    <Button class="w-full" variant="outline" @click="$inertia.visit('/x/dashboard')">
                        Go to Dashboard
                    </Button>
                </CardContent>
            </Card>
        </div>
    </div>
</template>
