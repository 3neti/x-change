<script setup lang="ts">
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Copy, ExternalLink, Check } from 'lucide-vue-next';

interface Props {
    code: string;
    claim_url?: string | null;
    qr_code?: string | null;
}

const props = defineProps<Props>();

const copied = ref(false);

const claimUrl = computed(() => {
    const url = props.claim_url || `/x/claim?code=${props.code}`;

    if (url.startsWith('http')) return url;

    return `${window.location.origin}${url}`;
});

async function copyUrl(): Promise<void> {
    await navigator.clipboard.writeText(claimUrl.value);
    copied.value = true;

    window.setTimeout(() => {
        copied.value = false;
    }, 1500);
}

function openClaim(): void {
    window.open(claimUrl.value, '_blank', 'noopener,noreferrer');
}
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle class="text-base">Claim Link</CardTitle>
        </CardHeader>

        <CardContent class="space-y-4">
            <div v-if="qr_code" class="flex justify-center">
                <img
                    :src="qr_code"
                    :alt="`QR code for ${code}`"
                    class="h-40 w-40 rounded-lg border bg-white p-2"
                />
            </div>

            <div class="rounded-lg bg-muted px-3 py-2 font-mono text-xs break-all">
                {{ claimUrl }}
            </div>

            <div class="grid grid-cols-2 gap-2">
                <Button variant="outline" @click="copyUrl">
                    <Check v-if="copied" class="mr-1.5 h-4 w-4" />
                    <Copy v-else class="mr-1.5 h-4 w-4" />
                    {{ copied ? 'Copied' : 'Copy' }}
                </Button>

                <Button @click="openClaim">
                    <ExternalLink class="mr-1.5 h-4 w-4" />
                    Open
                </Button>
            </div>
        </CardContent>
    </Card>
</template>
