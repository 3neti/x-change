<script setup lang="ts">
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';

interface Props {
    status?: string | null;
    redeemed_at?: string | null;
    expires_at?: string | null;
}

const props = defineProps<Props>();

const normalizedStatus = computed(() => {
    if (props.status) {
        return String(props.status).toLowerCase();
    }

    if (props.redeemed_at) {
        return 'redeemed';
    }

    if (props.expires_at && new Date(props.expires_at).getTime() < Date.now()) {
        return 'expired';
    }

    return 'active';
});

const label = computed(() => {
    switch (normalizedStatus.value) {
        case 'awaiting_approval':
            return 'Awaiting approval';
        case 'redeemed':
            return 'Redeemed';
        case 'expired':
            return 'Expired';
        case 'pending':
            return 'Pending';
        case 'failed':
            return 'Failed';
        case 'cancelled':
        case 'canceled':
            return 'Cancelled';
        case 'active':
        default:
            return 'Active';
    }
});

const variant = computed(() => {
    switch (normalizedStatus.value) {
        case 'failed':
        case 'expired':
            return 'destructive';
        case 'awaiting_approval':
            return 'outline';
        case 'redeemed':
            return 'secondary';
        case 'pending':
            return 'outline';
        case 'active':
        default:
            return 'default';
    }
});
</script>

<template>
    <Badge :variant="variant">
        {{ label }}
    </Badge>
</template>
