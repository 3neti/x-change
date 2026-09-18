<script setup lang="ts">
import ClaimStepShell from '@/components/x-change/ClaimStepShell.vue';
import { Form, Head } from '@inertiajs/vue3';
import { ArrowRight, ShieldCheck } from 'lucide-vue-next';

defineOptions({ layout: null });

defineProps<{
    campaign: {
        title: string;
        description?: string | null;
        merchant_display_name?: string | null;
        merchant_slug: string;
        endpoint_slug: string;
        usage_label?: string | null;
        usage_key?: string | null;
    };
    start_url: string;
    can_start: boolean;
    unavailable_message?: string | null;
}>();
</script>

<template>
    <Head :title="campaign.title" />

    <ClaimStepShell
        brand-placement="center"
        brand-size="display"
        brand-variant="mark"
        width="md"
    >
        <div class="space-y-6 text-center">
            <div class="space-y-3">
                <p
                    class="mx-auto inline-flex items-center gap-2 rounded-full border border-border/70 px-3 py-1 text-xs font-semibold uppercase tracking-[0.22em] text-muted-foreground"
                >
                    <ShieldCheck class="size-3.5" />
                    Public campaign
                </p>
                <div class="space-y-2">
                    <p
                        v-if="campaign.merchant_display_name"
                        class="text-sm font-medium text-muted-foreground"
                    >
                        {{ campaign.merchant_display_name }}
                    </p>
                    <h1 class="text-2xl font-semibold tracking-tight">
                        {{ campaign.title }}
                    </h1>
                    <p
                        v-if="campaign.description"
                        class="text-sm leading-6 text-muted-foreground"
                    >
                        {{ campaign.description }}
                    </p>
                </div>
            </div>

            <div
                class="rounded-lg border border-border/70 bg-muted/30 px-4 py-3 text-left text-sm text-muted-foreground"
            >
                <p class="font-medium text-foreground">
                    Start only when you are ready.
                </p>
                <p class="mt-1">
                    A fresh Pay Code is prepared only after you continue. This
                    protects the campaign from accidental scans and link
                    previews.
                </p>
            </div>

            <Form
                v-if="can_start"
                :action="start_url"
                method="post"
                #default="{ processing, errors }"
                class="space-y-3"
            >
                <p
                    v-if="errors.campaign"
                    class="rounded-md border border-destructive/30 bg-destructive/10 px-3 py-2 text-sm text-destructive"
                >
                    {{ errors.campaign }}
                </p>
                <button
                    type="submit"
                    :disabled="processing"
                    class="inline-flex w-full items-center justify-center gap-2 rounded-full bg-foreground px-5 py-3 text-sm font-semibold text-background transition hover:bg-foreground/90 disabled:cursor-not-allowed disabled:opacity-60"
                >
                    {{ processing ? 'Starting…' : 'Start campaign journey' }}
                    <ArrowRight class="size-4" />
                </button>
            </Form>

            <div
                v-else
                class="rounded-lg border border-amber-500/30 bg-amber-500/10 px-4 py-3 text-sm text-amber-700 dark:text-amber-200"
            >
                {{
                    unavailable_message ||
                    'This campaign is not accepting new starts right now.'
                }}
            </div>

            <p class="break-all text-xs text-muted-foreground">
                /x/o/{{ campaign.merchant_slug }}/{{ campaign.endpoint_slug }}
            </p>
        </div>
    </ClaimStepShell>
</template>
