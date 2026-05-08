<script setup lang="ts">
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import { type Component } from 'vue';
import { router } from '@inertiajs/vue3';

interface Props {
    title: string;
    value: string | number;
    subtitle?: string;
    icon?: Component;
    loading?: boolean;
    href?: string;
}

const props = withDefaults(defineProps<Props>(), {
    loading: false,
});

const handleClick = () => {
    if (props.href) {
        router.visit(props.href);
    }
};
</script>

<template>
    <Card
        :class="[
            'transition-all',
            href ? 'cursor-pointer hover:shadow-md' : '',
        ]"
        @click="handleClick"
    >
        <CardHeader class="flex flex-row items-center justify-between pb-2">
            <CardTitle class="text-sm font-medium">{{ title }}</CardTitle>
            <component
                :is="icon"
                v-if="icon"
                class="h-4 w-4 text-muted-foreground"
            />
        </CardHeader>
        <CardContent>
            <Skeleton v-if="loading" class="h-8 w-24" />
            <div v-else class="text-2xl font-bold">{{ value }}</div>
            <p v-if="subtitle" class="text-xs text-muted-foreground">
                {{ subtitle }}
            </p>
        </CardContent>
    </Card>
</template>
