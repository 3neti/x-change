<script setup lang="ts">
import { computed } from 'vue';
import { Card, CardContent } from '@/components/ui/card';
import { ExternalLink } from 'lucide-vue-next';
import type { OgMeta } from '@/types/voucher';

interface Props {
    url: string;
    ogMeta?: OgMeta | null;
}

const props = defineProps<Props>();

const hostname = computed(() => {
    try {
        return new URL(props.url).hostname;
    } catch {
        return props.url;
    }
});

const faviconUrl = computed(() => {
    try {
        const url = new URL(props.url);
        return `https://www.google.com/s2/favicons?domain=${url.hostname}&sz=32`;
    } catch {
        return null;
    }
});

const hasImage = computed(() => !!props.ogMeta?.og_image);
const hasContent = computed(() => !!props.ogMeta?.og_title || !!props.ogMeta?.og_description);
</script>

<template>
    <a
        :href="url"
        target="_blank"
        rel="noopener noreferrer"
        class="block group"
    >
        <Card class="overflow-hidden transition-all hover:ring-1 hover:ring-primary/20">
            <!-- OG Image -->
            <div v-if="hasImage" class="aspect-[2.4/1] overflow-hidden bg-muted">
                <img
                    :src="ogMeta!.og_image!"
                    :alt="ogMeta?.og_title || hostname"
                    class="w-full h-full object-cover transition-transform duration-300 group-hover:scale-[1.02]"
                    loading="lazy"
                    @error="($event.target as HTMLImageElement).style.display = 'none'"
                />
            </div>

            <CardContent :class="hasImage ? 'pt-2 pb-2' : 'pt-3 pb-3'">
                <div class="space-y-1.5">
                    <!-- Title -->
                    <p v-if="ogMeta?.og_title" class="text-sm font-medium leading-snug line-clamp-2">
                        {{ ogMeta.og_title }}
                    </p>

                    <!-- Description -->
                    <p v-if="ogMeta?.og_description" class="text-xs text-muted-foreground leading-relaxed line-clamp-1">
                        {{ ogMeta.og_description }}
                    </p>

                    <!-- Domain + favicon -->
                    <div class="flex items-center gap-1.5 pt-0.5">
                        <img
                            v-if="faviconUrl"
                            :src="faviconUrl"
                            :alt="hostname"
                            class="h-3.5 w-3.5 rounded-sm"
                            loading="lazy"
                            @error="($event.target as HTMLImageElement).style.display = 'none'"
                        />
                        <span class="text-[11px] text-muted-foreground truncate">{{ hostname }}</span>
                        <ExternalLink class="h-3 w-3 text-muted-foreground/50 shrink-0 ml-auto opacity-0 group-hover:opacity-100 transition-opacity" />
                    </div>
                </div>
            </CardContent>
        </Card>
    </a>
</template>
