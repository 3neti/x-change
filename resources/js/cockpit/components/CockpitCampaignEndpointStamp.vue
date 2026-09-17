<script setup lang="ts">
import { QrCode } from 'lucide-vue-next';

withDefaults(
    defineProps<{
        campaign: {
            title: string;
            description?: string | null;
            status: string;
            merchant_display_name?: string;
            public_url?: string | null;
            qr_data_uri?: string | null;
            usage_label?: string;
            template?: { name: string } | null;
        };
        availability?: string;
        exposure?: string;
        statusClass?: string;
        statusLabel?: string;
    }>(),
    {
        availability: 'Ready for one customer',
        exposure: 'Paired seller display',
        statusClass: 'bg-white/15 text-white',
        statusLabel: undefined,
    },
);
</script>

<template>
    <div
        class="mt-4 overflow-hidden rounded-[1.75rem] border border-slate-200 bg-slate-950 text-white shadow-inner dark:border-slate-800"
        data-testid="campaign-endpoint-stamp-preview"
    >
        <div
            class="grid gap-4 bg-[radial-gradient(circle_at_top_left,rgba(16,185,129,0.35),transparent_32%),linear-gradient(135deg,#020617,#111827_52%,#064e3b)] p-5"
        >
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <p
                        class="text-[0.65rem] font-semibold uppercase tracking-[0.22em] text-emerald-200"
                    >
                        {{ campaign.usage_label }}
                        campaign
                    </p>
                    <h3 class="mt-2 text-2xl font-semibold leading-tight">
                        {{ campaign.title }}
                    </h3>
                    <p class="mt-2 text-sm leading-5 text-slate-200">
                        {{
                            campaign.description ||
                            campaign.template?.name ||
                            'Scan to start this Pay Code experience.'
                        }}
                    </p>
                </div>
                <span
                    :class="statusClass"
                    class="shrink-0 rounded-full px-2 py-1 text-[0.65rem] font-semibold"
                    >{{
                        statusLabel ?? campaign.status.replaceAll('_', ' ')
                    }}</span
                >
            </div>

            <div
                class="grid gap-4 rounded-2xl bg-white/95 p-4 text-slate-950 shadow-xl sm:grid-cols-[10rem_1fr]"
            >
                <div
                    class="flex min-h-40 items-center justify-center rounded-xl border border-slate-200 bg-white p-2"
                >
                    <img
                        v-if="campaign.qr_data_uri"
                        :src="campaign.qr_data_uri"
                        :alt="`Endpoint stamp code for ${campaign.title}`"
                        class="size-36"
                        data-testid="campaign-endpoint-stamp-qr"
                    />
                    <QrCode
                        v-else
                        class="size-16 text-slate-300"
                        aria-hidden="true"
                    />
                </div>
                <div class="grid content-between gap-4">
                    <div>
                        <p
                            class="text-[0.65rem] font-semibold uppercase tracking-[0.18em] text-slate-500"
                        >
                            Presented by
                        </p>
                        <p class="mt-1 text-lg font-semibold">
                            {{ campaign.merchant_display_name }}
                        </p>
                    </div>
                    <dl class="grid gap-2 text-xs">
                        <div>
                            <dt
                                class="font-semibold uppercase tracking-[0.14em] text-slate-500"
                            >
                                Availability
                            </dt>
                            <dd class="mt-0.5 font-medium">
                                {{ availability }}
                            </dd>
                        </div>
                        <div>
                            <dt
                                class="font-semibold uppercase tracking-[0.14em] text-slate-500"
                            >
                                Exposure
                            </dt>
                            <dd class="mt-0.5 font-medium">
                                {{ exposure }}
                            </dd>
                        </div>
                    </dl>
                </div>
            </div>

            <div
                class="rounded-2xl border border-white/10 bg-white/10 p-3 text-xs text-slate-100"
            >
                <p
                    class="font-semibold uppercase tracking-[0.18em] text-emerald-200"
                >
                    Public endpoint
                </p>
                <p
                    class="mt-1 break-all font-medium"
                    data-testid="campaign-endpoint-stamp-url"
                >
                    {{ campaign.public_url }}
                </p>
            </div>
        </div>
    </div>
</template>
