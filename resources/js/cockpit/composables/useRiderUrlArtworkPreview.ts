import CockpitRiderArtworkPreviewController from '@/actions/LBHurtado/XChange/Http/Controllers/Web/Cockpit/CockpitRiderArtworkPreviewController';
import { onUnmounted, ref, watch, type Ref } from 'vue';
import type { RiderUrlArtworkPreview } from '../riderOgPreview';

type ArtworkPreviewResponse = RiderUrlArtworkPreview & {
    schema: string;
};

type UseRiderUrlArtworkPreview = {
    preview: Ref<RiderUrlArtworkPreview | null>;
    resolving: Ref<boolean>;
    message: Ref<string | null>;
};

export function useRiderUrlArtworkPreview(
    source: Ref<string>,
    url: Ref<string>,
    debounceMs = 350,
): UseRiderUrlArtworkPreview {
    const preview = ref<RiderUrlArtworkPreview | null>(null);
    const resolving = ref(false);
    const message = ref<string | null>(null);
    let timer: ReturnType<typeof setTimeout> | null = null;
    let requestId = 0;
    let abortController: AbortController | null = null;

    function clearTimer(): void {
        if (timer === null) {
            return;
        }

        clearTimeout(timer);
        timer = null;
    }

    function schedule(): void {
        clearTimer();
        abortController?.abort();
        preview.value = null;
        message.value = null;
        resolving.value = false;

        if (
            typeof window === 'undefined' ||
            source.value !== 'url' ||
            url.value.trim() === ''
        ) {
            return;
        }

        resolving.value = true;
        timer = setTimeout(() => {
            void resolve();
        }, debounceMs);
    }

    async function resolve(): Promise<void> {
        const targetUrl = url.value.trim();
        const currentRequestId = ++requestId;
        const route = CockpitRiderArtworkPreviewController();
        abortController = new AbortController();

        try {
            const response = await fetch(route.url, {
                method: route.method.toUpperCase(),
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    ...csrfHeader(),
                },
                body: JSON.stringify({ url: targetUrl }),
                signal: abortController.signal,
            });
            const payload = await safeJson(response);

            if (
                currentRequestId !== requestId ||
                targetUrl !== url.value.trim()
            ) {
                return;
            }

            if (!response.ok) {
                throw new Error('Artwork preview request failed.');
            }

            preview.value = payload;
            message.value = payload.available
                ? `${payload.reference} artwork ready.`
                : 'This link will use a clean text fallback.';
        } catch (error) {
            if (
                currentRequestId !== requestId ||
                (error instanceof DOMException && error.name === 'AbortError')
            ) {
                return;
            }

            preview.value = null;
            message.value =
                'Link artwork could not be loaded. The action link remains active.';
        } finally {
            if (currentRequestId === requestId) {
                resolving.value = false;
            }
        }
    }

    watch([source, url], schedule, { immediate: true });

    onUnmounted(() => {
        clearTimer();
        abortController?.abort();
    });

    return {
        preview,
        resolving,
        message,
    };
}

async function safeJson(response: Response): Promise<ArtworkPreviewResponse> {
    const payload: unknown = await response.json();

    if (!isArtworkPreviewResponse(payload)) {
        throw new Error('Artwork preview response is invalid.');
    }

    return payload;
}

function isArtworkPreviewResponse(
    value: unknown,
): value is ArtworkPreviewResponse {
    if (typeof value !== 'object' || value === null) {
        return false;
    }

    const payload = value as Record<string, unknown>;

    return (
        typeof payload.schema === 'string' &&
        typeof payload.available === 'boolean' &&
        typeof payload.source === 'string' &&
        typeof payload.title === 'string' &&
        typeof payload.description === 'string' &&
        (typeof payload.image_url === 'string' ||
            payload.image_url === null) &&
        typeof payload.reference === 'string'
    );
}

function csrfHeader(): Record<string, string> {
    if (typeof document === 'undefined') {
        return {};
    }

    const token = document
        .querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
        ?.getAttribute('content')
        ?.trim();

    return token ? { 'X-CSRF-TOKEN': token } : {};
}
