<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: '/x/dashboard' },
            { title: 'Pay Codes', href: '/x/pay-codes' },
            { title: 'Create', href: '/x/pay-codes/create' },
        ],
    },
});
</script>

<template>
    <Head title="Create Pay Code" />

    <div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
        <Card class="mx-auto w-full max-w-2xl">
            <CardHeader>
                <CardTitle>Create Pay Code</CardTitle>
                <CardDescription>
                    Generate a new pay code for disbursement. The form-flow
                    integration will handle the full creation workflow.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <p class="text-sm text-muted-foreground">
                    Pay Code creation form will be integrated via form-flow.
                    Use the API directly at
                    <code class="rounded bg-muted px-2 py-1 text-xs">POST /api/x/v1/pay-codes</code>
                    or the CLI
                    <code class="rounded bg-muted px-2 py-1 text-xs">php artisan x-change:generate-pay-code</code>.
                </p>
            </CardContent>
        </Card>
    </div>
</template>
