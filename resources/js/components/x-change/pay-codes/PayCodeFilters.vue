<script setup lang="ts">
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Search } from 'lucide-vue-next';

interface Props {
    search: string;
    status: string;
}

defineProps<Props>();

const emit = defineEmits<{
    'update:search': [value: string];
    'update:status': [value: string];
}>();
</script>

<template>
    <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
        <div class="relative w-full md:max-w-sm">
            <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
            <Input
                :model-value="search"
                placeholder="Search code, mobile, account..."
                class="pl-9"
                @update:model-value="emit('update:search', String($event))"
            />
        </div>

        <Select
            :model-value="status"
            @update:model-value="emit('update:status', String($event))"
        >
            <SelectTrigger class="w-full md:w-[180px]">
                <SelectValue placeholder="Status" />
            </SelectTrigger>
            <SelectContent>
                <SelectItem value="all">All statuses</SelectItem>
                <SelectItem value="awaiting_approval">Awaiting approval</SelectItem>
                <SelectItem value="active">Active</SelectItem>
                <SelectItem value="redeemed">Redeemed</SelectItem>
                <SelectItem value="expired">Expired</SelectItem>
                <SelectItem value="pending">Pending</SelectItem>
                <SelectItem value="failed">Failed</SelectItem>
            </SelectContent>
        </Select>
    </div>
</template>
