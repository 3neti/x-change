<script setup lang="ts">
import { computed, reactive } from 'vue';
import { Card, CardContent } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { ExternalLink, ImageIcon, MessageSquareText, Share2 } from 'lucide-vue-next';

export interface ClaimExperienceContent {
    content?: string | null;
    content_type?: string | null;
    presentation?: string | null;
    timeout?: number | string | null;
    meta?: Record<string, unknown> | null;
}

export interface ClaimExperienceRedirect {
    url?: string | null;
    delay_seconds?: number | string | null;
    show_countdown?: boolean | null;
}

export interface ClaimExperienceOgMeta {
    title?: string | null;
    description?: string | null;
    url?: string | null;
    site_name?: string | null;
    image_url?: string | null;
    image_alt?: string | null;
    amount_label?: string | null;
    message_preview?: string | null;
}

export interface ClaimExperienceSummaryProps {
    message?: ClaimExperienceContent | null;
    splash?: ClaimExperienceContent | null;
    redirect?: ClaimExperienceRedirect | null;
    og_meta?: ClaimExperienceOgMeta | null;
    options?: Record<string, unknown> | null;
}

const props = defineProps<ClaimExperienceSummaryProps>();

const hasMessage = computed(() => Boolean(props.message?.content));
const hasSplash = computed(() => Boolean(props.splash?.content));
const hasRedirect = computed(() => Boolean(props.redirect?.url));
const hasOgMeta = computed(
    () =>
        Boolean(props.og_meta?.title) ||
        Boolean(props.og_meta?.description) ||
        Boolean(props.og_meta?.image_url),
);

const hasAnyExperience = computed(
    () => hasMessage.value || hasSplash.value || hasRedirect.value || hasOgMeta.value,
);

const messageDocument = computed(() => previewDocument(props.message?.content));
const splashDocument = computed(() => previewDocument(props.splash?.content));
const frameHeights = reactive({
    message: 112,
    splash: 288,
});

const redirectHost = computed(() => {
    const url = props.redirect?.url;

    if (!url) {
        return null;
    }

    try {
        return new URL(url).host;
    } catch {
        return url;
    }
});

function previewDocument(content?: string | null): string {
    return `<!doctype html>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<base target="_blank">
<style>
html,body{margin:0;padding:0;background:transparent;color:#111827;font-family:ui-sans-serif,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;}
body{padding:12px;overflow:hidden;}
img,video,iframe{max-width:100%;height:auto;}
*{box-sizing:border-box;}
.preview-root{width:100%;overflow:hidden;}
.preview-root>*:first-child{margin-top:0;}
.preview-root>*:last-child{margin-bottom:0;}
</style>
</head>
<body>
<div class="preview-root">${content ?? ''}</div>
</body>
</html>`;
}

function resizeExperienceFrame(kind: 'message' | 'splash', event: Event): void {
    const frame = event.target as HTMLIFrameElement | null;
    const documentElement = frame?.contentDocument?.documentElement;
    const body = frame?.contentDocument?.body;

    if (!documentElement || !body) {
        return;
    }

    const measure = (): void => {
        frameHeights[kind] = Math.max(
            kind === 'message' ? 112 : 288,
            documentElement.scrollHeight,
            body.scrollHeight,
        );
    };

    measure();

    for (const image of Array.from(body.querySelectorAll('img'))) {
        image.addEventListener('load', measure, { once: true });
    }

    window.setTimeout(measure, 120);
    window.setTimeout(measure, 500);
}
</script>

<template>
    <section
        v-if="hasAnyExperience"
        class="space-y-3"
        data-testid="claim-experience-summary"
    >
        <div class="flex items-start justify-between gap-3">
            <div class="space-y-1">
                <h3 class="text-sm font-semibold text-foreground">Claim Experience</h3>
                <p class="text-xs leading-relaxed text-muted-foreground">
                    Static preview of the rider content attached to this Pay Code.
                </p>
            </div>
            <Badge variant="secondary" class="shrink-0 text-[0.65rem]">
                Preview only
            </Badge>
        </div>

        <div class="space-y-3">
            <Card v-if="hasMessage" class="border-border/70 bg-muted/30">
                <CardContent class="space-y-2 p-3">
                    <div class="flex items-center gap-2 text-xs font-medium text-muted-foreground">
                        <MessageSquareText class="h-3.5 w-3.5" />
                        Rider message
                    </div>
                    <iframe
                        title="Rider message preview"
                        class="w-full rounded-md border border-border/60 bg-background"
                        data-testid="claim-experience-message"
                        sandbox="allow-same-origin"
                        scrolling="no"
                        :style="{ height: `${frameHeights.message}px` }"
                        :srcdoc="messageDocument"
                        @load="resizeExperienceFrame('message', $event)"
                    />
                </CardContent>
            </Card>

            <Card v-if="hasSplash" class="overflow-hidden border-border/70 bg-background">
                <CardContent class="space-y-2 p-3">
                    <div class="flex items-center justify-between gap-2">
                        <div class="flex items-center gap-2 text-xs font-medium text-muted-foreground">
                            <ImageIcon class="h-3.5 w-3.5" />
                            Rider splash
                        </div>
                        <span
                            v-if="splash?.timeout"
                            class="text-[0.65rem] text-muted-foreground"
                        >
                            {{ splash.timeout }}s in live flow
                        </span>
                    </div>
                    <iframe
                        title="Rider splash preview"
                        class="w-full rounded-md border border-border/60 bg-background"
                        data-testid="claim-experience-splash"
                        sandbox="allow-same-origin"
                        scrolling="no"
                        :style="{ height: `${frameHeights.splash}px` }"
                        :srcdoc="splashDocument"
                        @load="resizeExperienceFrame('splash', $event)"
                    />
                </CardContent>
            </Card>

            <Card
                v-if="hasRedirect || hasOgMeta"
                class="border-border/70 bg-background"
            >
                <CardContent class="space-y-3 p-3">
                    <div
                        v-if="hasRedirect"
                        class="flex min-w-0 items-start gap-3"
                        data-testid="claim-experience-redirect"
                    >
                        <ExternalLink class="mt-0.5 h-4 w-4 shrink-0 text-muted-foreground" />
                        <div class="min-w-0 space-y-0.5">
                            <p class="text-xs font-medium text-muted-foreground">
                                Rider URL
                            </p>
                            <a
                                :href="redirect?.url ?? undefined"
                                class="block truncate text-sm font-medium text-foreground underline-offset-4 hover:underline"
                                target="_blank"
                                rel="noreferrer"
                            >
                                {{ redirectHost }}
                            </a>
                        </div>
                    </div>

                    <div
                        v-if="hasOgMeta"
                        class="grid gap-3 rounded-md border border-border/60 bg-muted/20 p-2 sm:grid-cols-[5rem_1fr]"
                        data-testid="claim-experience-og-meta"
                    >
                        <div
                            class="flex aspect-[1.91/1] items-center justify-center overflow-hidden rounded bg-muted sm:aspect-square"
                        >
                            <img
                                v-if="og_meta?.image_url"
                                :src="og_meta.image_url"
                                :alt="og_meta.image_alt || ''"
                                class="h-full w-full object-cover"
                            />
                            <Share2 v-else class="h-5 w-5 text-muted-foreground" />
                        </div>
                        <div class="min-w-0 space-y-1">
                            <p class="truncate text-xs font-medium text-muted-foreground">
                                OG preview
                            </p>
                            <p
                                v-if="og_meta?.amount_label"
                                class="text-sm font-semibold text-foreground"
                            >
                                {{ og_meta.amount_label }}
                            </p>
                            <p
                                v-if="og_meta?.title"
                                class="line-clamp-2 text-sm font-semibold text-foreground"
                            >
                                {{ og_meta.title }}
                            </p>
                            <p
                                v-if="og_meta?.description"
                                class="line-clamp-2 text-xs leading-relaxed text-muted-foreground"
                            >
                                {{ og_meta.description }}
                            </p>
                            <p
                                v-if="og_meta?.message_preview"
                                class="line-clamp-2 text-xs leading-relaxed text-muted-foreground"
                            >
                                {{ og_meta.message_preview }}
                            </p>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    </section>
</template>
