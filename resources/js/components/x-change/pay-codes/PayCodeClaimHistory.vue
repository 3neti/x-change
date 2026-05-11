<script setup lang="ts">
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';

interface Claim {
    id?: number | string;
    status?: string | null;
    mobile?: string | null;
    account_number?: string | null;
    transaction_id?: string | null;
    created_at?: string | null;
    redeemed_at?: string | null;
    error?: string | null;
}

interface Props {
    claims?: Claim[] | null;
}

defineProps<Props>();

function dateLabel(value?: string | null): string {
    if (!value) return '—';

    return new Intl.DateTimeFormat('en-PH', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    }).format(new Date(value));
}
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle class="text-base">Claim History</CardTitle>
        </CardHeader>

        <CardContent>
            <div v-if="claims?.length" class="space-y-3">
                <div
                    v-for="claim in claims"
                    :key="claim.id ?? claim.transaction_id ?? claim.created_at"
                    class="rounded-lg border p-3"
                >
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <p class="text-sm font-medium">
                                {{ claim.mobile ?? claim.account_number ?? 'Claim attempt' }}
                            </p>
                            <p class="text-xs text-muted-foreground">
                                {{ dateLabel(claim.redeemed_at ?? claim.created_at) }}
                            </p>
                        </div>

                        <Badge variant="secondary">
                            {{ claim.status ?? 'recorded' }}
                        </Badge>
                    </div>

                    <p v-if="claim.transaction_id" class="mt-2 break-all font-mono text-xs text-muted-foreground">
                        {{ claim.transaction_id }}
                    </p>

                    <p v-if="claim.error" class="mt-2 text-xs text-destructive">
                        {{ claim.error }}
                    </p>
                </div>
            </div>

            <div v-else class="rounded-lg border border-dashed p-6 text-center">
                <p class="text-sm font-medium">No claim history yet</p>
                <p class="mt-1 text-xs text-muted-foreground">
                    Claims will appear here after redemption attempts.
                </p>
            </div>
        </CardContent>
    </Card>
</template>
