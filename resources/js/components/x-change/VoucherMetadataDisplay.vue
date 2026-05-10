<script setup lang="ts">
import { computed } from 'vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Building2, Copyright, Shield, ExternalLink, Copy } from 'lucide-vue-next';
import { useClipboard } from '@/composables/useClipboard';

interface VoucherMetadata {
    version?: string;
    system_name?: string;
    copyright?: string;
    licenses?: Record<string, string>;
    issuer_id?: string;
    issuer_name?: string;
    issuer_email?: string;
    redemption_urls?: Record<string, string>;
    primary_url?: string;
    created_at?: string;
    issued_at?: string;
}

interface Props {
    metadata: VoucherMetadata | null;
    compact?: boolean;
    showAllFields?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    compact: false,
    showAllFields: true,
});

const hasMetadata = computed(() => !!props.metadata);
const hasLicenses = computed(() => props.metadata?.licenses && Object.keys(props.metadata.licenses).length > 0);
const hasRedemptionUrls = computed(() => props.metadata?.redemption_urls && Object.keys(props.metadata.redemption_urls).length > 0);

const { copy } = useClipboard();

const formatDate = (dateString: string | undefined) => {
    if (!dateString) return 'N/A';
    return new Date(dateString).toLocaleString('en-PH', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};
</script>

<template>
    <div v-if="hasMetadata" :class="compact ? 'space-y-3' : 'space-y-6'">
        <!-- System Information -->
        <Card v-if="showAllFields || metadata?.system_name || metadata?.version">
            <CardHeader v-if="!compact">
                <div class="flex items-center gap-2">
                    <Building2 class="h-5 w-5" />
                    <CardTitle>System Information</CardTitle>
                </div>
                <CardDescription>
                    Voucher system details
                </CardDescription>
            </CardHeader>
            <CardContent :class="compact ? 'py-3 space-y-2' : 'space-y-3'">
                <div v-if="metadata?.system_name" class="flex justify-between items-center">
                    <span :class="compact ? 'text-xs' : 'text-sm'" class="text-muted-foreground">System</span>
                    <span :class="compact ? 'text-sm' : 'text-base'" class="font-medium">{{ metadata.system_name }}</span>
                </div>
                <div v-if="metadata?.version && showAllFields" class="flex justify-between items-center">
                    <span :class="compact ? 'text-xs' : 'text-sm'" class="text-muted-foreground">Version</span>
                    <Badge variant="outline">{{ metadata.version }}</Badge>
                </div>
                <div v-if="metadata?.copyright" class="flex justify-between items-center">
                    <span :class="compact ? 'text-xs' : 'text-sm'" class="text-muted-foreground flex items-center gap-1">
                        <Copyright class="h-3 w-3" /> Copyright
                    </span>
                    <span :class="compact ? 'text-sm' : 'text-base'" class="font-medium">{{ metadata.copyright }}</span>
                </div>
            </CardContent>
        </Card>

        <!-- Regulatory Licenses -->
        <Card v-if="hasLicenses">
            <CardHeader v-if="!compact">
                <div class="flex items-center gap-2">
                    <Shield class="h-5 w-5" />
                    <CardTitle>Regulatory Licenses</CardTitle>
                </div>
                <CardDescription>
                    Government registrations and compliance
                </CardDescription>
            </CardHeader>
            <CardContent :class="compact ? 'py-3' : ''">
                <div class="flex flex-wrap gap-2">
                    <Badge 
                        v-for="(name, code) in metadata?.licenses" 
                        :key="code"
                        variant="secondary"
                        class="px-3 py-1"
                    >
                        <Shield class="h-3 w-3 mr-1" />
                        {{ code }}: {{ name }}
                    </Badge>
                </div>
            </CardContent>
        </Card>

        <!-- Issuer Information -->
        <Card v-if="metadata?.issuer_name && showAllFields">
            <CardHeader v-if="!compact">
                <CardTitle>Issued By</CardTitle>
                <CardDescription>
                    Voucher issuer information
                </CardDescription>
            </CardHeader>
            <CardContent :class="compact ? 'py-3 space-y-2' : 'space-y-3'">
                <div v-if="metadata?.issuer_name" class="flex justify-between items-center">
                    <span :class="compact ? 'text-xs' : 'text-sm'" class="text-muted-foreground">Name</span>
                    <span :class="compact ? 'text-sm' : 'text-base'" class="font-medium">{{ metadata.issuer_name }}</span>
                </div>
                <div v-if="metadata?.issuer_email" class="flex justify-between items-center">
                    <span :class="compact ? 'text-xs' : 'text-sm'" class="text-muted-foreground">Email</span>
                    <span :class="compact ? 'text-sm' : 'text-base'" class="font-medium">{{ metadata.issuer_email }}</span>
                </div>
            </CardContent>
        </Card>

        <!-- Redemption Options -->
        <Card v-if="hasRedemptionUrls && showAllFields">
            <CardHeader v-if="!compact">
                <CardTitle>Redemption Options</CardTitle>
                <CardDescription>
                    Available redemption methods
                </CardDescription>
            </CardHeader>
            <CardContent :class="compact ? 'py-3 space-y-2' : 'space-y-3'">
                <div 
                    v-for="(url, method) in metadata?.redemption_urls" 
                    :key="method"
                    class="flex items-center justify-between gap-2"
                >
                    <span :class="compact ? 'text-xs' : 'text-sm'" class="text-muted-foreground capitalize">{{ method }}</span>
                    <div class="flex items-center gap-2">
                        <a 
                            :href="url" 
                            target="_blank"
                            :class="compact ? 'text-xs' : 'text-sm'"
                            class="text-primary hover:underline truncate max-w-xs"
                        >
                            {{ url }}
                        </a>
                        <Button
                            variant="ghost"
                            size="sm"
                            class="h-6 w-6 p-0"
                            @click="copy(url)"
                            title="Copy URL"
                        >
                            <Copy class="h-3 w-3" />
                        </Button>
                    </div>
                </div>
            </CardContent>
        </Card>

        <!-- Timestamps -->
        <Card v-if="metadata?.issued_at && showAllFields">
            <CardContent :class="compact ? 'py-3 space-y-1' : 'pt-6 space-y-2'">
                <div class="flex justify-between items-center">
                    <span :class="compact ? 'text-xs' : 'text-sm'" class="text-muted-foreground">Issued</span>
                    <span :class="compact ? 'text-xs' : 'text-sm'" class="font-medium">{{ formatDate(metadata.issued_at) }}</span>
                </div>
            </CardContent>
        </Card>
    </div>

    <!-- No metadata message -->
    <Card v-else>
        <CardContent class="py-6 text-center text-sm text-muted-foreground">
            Metadata not available for this voucher.
        </CardContent>
    </Card>
</template>
