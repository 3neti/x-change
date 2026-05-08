<script setup lang="ts">
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Link } from '@inertiajs/vue3';
import { Plus, Wallet, List } from 'lucide-vue-next';

const actions = [
    {
        label: 'Create Pay Code',
        href: '/x/pay-codes/create',
        icon: Plus,
        variant: 'default' as const,
    },
    {
        label: 'View Pay Codes',
        href: '/x/pay-codes',
        icon: List,
        variant: 'outline' as const,
    },
    {
        label: 'View Balances',
        href: '/x/balances',
        icon: Wallet,
        variant: 'outline' as const,
    },
];
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle>Quick Actions</CardTitle>
        </CardHeader>
        <CardContent>
            <div class="grid grid-cols-2 gap-3 md:grid-cols-3">
                <Button
                    v-for="action in actions"
                    :key="action.href"
                    :variant="action.variant"
                    as-child
                    class="h-auto flex-col gap-2 py-4"
                >
                    <Link :href="action.href">
                        <component :is="action.icon" class="h-5 w-5" />
                        <span class="text-xs">{{ action.label }}</span>
                    </Link>
                </Button>
            </div>
        </CardContent>
    </Card>
</template>
