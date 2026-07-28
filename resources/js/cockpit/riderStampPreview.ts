import type { RiderContentFormat } from './riderContent';
import { escapeRiderHtml, renderRiderContent } from './riderContent';

export type RiderStampPreviewSource = 'default' | 'message' | 'url' | 'splash';
export type RiderStampFit = 'cover' | 'contain';
export type RiderStampPosition = 'center' | 'top' | 'bottom' | 'left' | 'right';
export type RiderStampTheme = 'automatic' | 'light' | 'dark';
export type RiderArtworkSurface = 'canvas' | 'stamp' | 'og-meta';

export type RiderStampPreview = {
    source: RiderStampPreviewSource;
    label: string;
    title: string;
    description: string;
    reference: string;
    imageUrl: string | null;
    fit: RiderStampFit;
    position: RiderStampPosition;
    scrim: number;
    theme: RiderStampTheme;
};

/** @deprecated Use RiderStampPreviewSource. */
export type RiderOgPreviewSource = RiderStampPreviewSource;
/** @deprecated Use RiderStampPreview. */
export type RiderOgPreview = RiderStampPreview;

export type RiderUrlArtworkPreview = {
    available: boolean;
    source: string;
    title: string;
    description: string;
    image_url: string | null;
    reference: string;
};

export type RiderStampPreviewInput = {
    source?: string | null;
    message?: string | null;
    url?: string | null;
    splashHeadline?: string | null;
    splashBody?: string | null;
    splashCta?: string | null;
    urlArtwork?: RiderUrlArtworkPreview | null;
    title?: string | null;
    description?: string | null;
    fit?: string | null;
    position?: string | null;
    scrim?: number | string | null;
    theme?: string | null;
};

/** @deprecated Use RiderStampPreviewInput. */
export type RiderOgPreviewInput = RiderStampPreviewInput;

export function escapeHtml(value: string): string {
    return escapeRiderHtml(value);
}

export function looksLikeHtml(value: string): boolean {
    return /<\/?[a-z][\s\S]*>/i.test(value);
}

export function buildRiderSplashContent(input: {
    headline?: string | null;
    body?: string | null;
    cta?: string | null;
    format?: RiderContentFormat | null;
}): string {
    const headline = (input.headline ?? '').trim();
    const body = (input.body ?? '').trim();
    const cta = (input.cta ?? '').trim();

    const format = input.format;
    const renderedBody =
        body === ''
            ? null
            : format === null || format === undefined
              ? renderRiderContent(body, looksLikeHtml(body) ? 'html' : 'plain')
              : renderRiderContent(body, format);

    return [
        headline === '' ? null : `<h1>${escapeHtml(headline)}</h1>`,
        renderedBody,
        cta === '' ? null : `<p><strong>${escapeHtml(cta)}</strong></p>`,
    ]
        .filter((item): item is string => item !== null)
        .join('\n');
}

export function resolveRiderStampPreview(
    input: RiderStampPreviewInput,
): RiderStampPreview {
    const source = normalizeSource(input.source);
    const message = (input.message ?? '').trim();
    const url = (input.url ?? '').trim();
    const splashHeadline = (input.splashHeadline ?? '').trim();
    const splashBody = (input.splashBody ?? '').trim();
    const splashCta = (input.splashCta ?? '').trim();
    const presentation = {
        fit: normalizeFit(input.fit),
        position: normalizePosition(input.position),
        scrim: normalizeScrim(input.scrim),
        theme: normalizeTheme(input.theme),
    };
    const preview: Omit<
        RiderStampPreview,
        'fit' | 'position' | 'scrim' | 'theme'
    > =
        source === 'message'
            ? {
                  source,
                  label: 'Rider Stamp Preview',
                  title: message === '' ? 'No Message Yet' : message,
                  description: 'Preview based on the Rider Message.',
                  reference: 'Rider Message',
                  imageUrl: null,
              }
            : source === 'url'
              ? resolveUrlStamp(input, source, url)
              : source === 'splash'
                ? {
                      source,
                      label: 'Rider Stamp Preview',
                      title:
                          splashHeadline === ''
                              ? splashBody || 'No Introduction Yet'
                              : splashHeadline,
                      description:
                          splashCta === ''
                              ? splashBody || 'No Introduction Message Yet.'
                              : `${splashBody || 'No Introduction Message Yet.'} · ${splashCta}`,
                      reference: 'Rider Splash',
                      imageUrl: null,
                  }
                : {
                      source,
                      label: 'Rider Stamp Preview',
                      title:
                          splashHeadline ||
                          message ||
                          (url === '' ? 'Default Claim Preview' : url),
                      description:
                          splashBody ||
                          message ||
                          'Uses the first available message, link, or introduction.',
                      reference: 'x-change',
                      imageUrl: null,
                  };

    return {
        ...preview,
        title: input.title?.trim() || preview.title,
        description: input.description?.trim() || preview.description,
        ...presentation,
    };
}

function resolveUrlStamp(
    input: RiderStampPreviewInput,
    source: 'url',
    url: string,
): Omit<RiderStampPreview, 'fit' | 'position' | 'scrim' | 'theme'> {
    const artwork =
        input.urlArtwork?.available === true ? input.urlArtwork : null;

    return {
        source,
        label: 'Rider Stamp Preview',
        title: artwork?.title || (url === '' ? 'No Action URL Yet' : url),
        description: artwork?.description || 'Preview based on the Rider URL.',
        reference: artwork?.reference || 'Rider URL',
        imageUrl: artwork?.image_url || null,
    };
}

/** @deprecated Use resolveRiderStampPreview. */
export function resolveRiderOgPreview(
    input: RiderOgPreviewInput,
): RiderOgPreview {
    return resolveRiderStampPreview(input);
}

export function shouldRenderRiderStampSplash(
    preview: RiderStampPreview,
    splashContent: string,
): boolean {
    return (
        (preview.source === 'default' || preview.source === 'splash') &&
        splashContent.trim() !== ''
    );
}

/** @deprecated Use shouldRenderRiderStampSplash. */
export function shouldRenderRiderOgSplash(
    preview: RiderOgPreview,
    splashContent: string,
): boolean {
    return shouldRenderRiderStampSplash(preview, splashContent);
}

export function buildRiderStampPreviewDocument(
    preview: RiderStampPreview,
    splashContent: string,
    surface: RiderArtworkSurface = 'canvas',
): string {
    const content =
        preview.source === 'url' && preview.imageUrl !== null
            ? buildArtworkMarkup(preview, surface !== 'canvas')
            : shouldRenderRiderStampSplash(preview, splashContent)
              ? splashContent
              : `<h1>${escapeHtml(preview.title)}</h1><p>${escapeHtml(preview.description)}</p>`;

    return buildSandboxedPreviewDocument(buildStampMarkup(content, preview));
}

/** @deprecated Use buildRiderStampPreviewDocument. */
export function buildRiderOgPreviewDocument(
    preview: RiderOgPreview,
    splashContent: string,
    surface: RiderArtworkSurface = 'canvas',
): string {
    return buildRiderStampPreviewDocument(preview, splashContent, surface);
}

function buildArtworkMarkup(
    preview: RiderStampPreview,
    includeCopy: boolean,
): string {
    const safeImageUrl = escapeHtml(preview.imageUrl ?? '');
    const position = artworkPosition(preview.position);
    const artwork =
        preview.fit === 'contain'
            ? `<img class="artwork-backdrop" src="${safeImageUrl}" alt="" />
<img class="artwork-contain" style="object-position: ${position}" src="${safeImageUrl}" alt="" />`
            : `<img class="artwork-cover" style="object-position: ${position}" src="${safeImageUrl}" alt="" />`;
    const copy = includeCopy
        ? `<div class="stamp-copy"><h1>${escapeHtml(preview.title)}</h1><p>${escapeHtml(preview.description)}</p></div>`
        : '';

    return `<div class="artwork-safe">${artwork}${copy}</div>`;
}

function buildStampMarkup(content: string, preview: RiderStampPreview): string {
    const scrimOpacity = (preview.scrim / 100).toFixed(2);

    return `<div class="stamp-root stamp-theme-${preview.theme}">
<div class="stamp-content">${content}</div>
<div class="stamp-scrim" style="opacity: ${scrimOpacity}"></div>
</div>`;
}

function artworkPosition(position: RiderStampPosition): string {
    return {
        center: 'center center',
        top: 'center top',
        bottom: 'center bottom',
        left: 'left center',
        right: 'right center',
    }[position];
}

function normalizeFit(value?: string | null): RiderStampFit {
    return value === 'contain' ? 'contain' : 'cover';
}

function normalizePosition(value?: string | null): RiderStampPosition {
    return value === 'top' ||
        value === 'bottom' ||
        value === 'left' ||
        value === 'right'
        ? value
        : 'center';
}

function normalizeScrim(value?: number | string | null): number {
    const normalized = Number(value);

    return Number.isFinite(normalized)
        ? Math.min(100, Math.max(0, Math.round(normalized)))
        : 18;
}

function normalizeTheme(value?: string | null): RiderStampTheme {
    return value === 'light' || value === 'dark' ? value : 'automatic';
}

export function buildSandboxedPreviewDocument(content: string): string {
    const body =
        content.trim() === '' ? '<p>No Introduction Message Yet.</p>' : content;

    return `<!doctype html>
<html>
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<meta http-equiv="Content-Security-Policy" content="default-src 'none'; img-src https: data:; style-src 'unsafe-inline'; font-src data:; base-uri 'none'; form-action 'none';" />
<style>
* { box-sizing: border-box; }
html, body { margin: 0; min-height: 100%; overflow: hidden; background: #020617; color: #f8fafc; font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; }
body { padding: 0; }
img { max-width: 100%; height: auto; }
.stamp-root { position: relative; width: 100%; height: 100vh; overflow: hidden; background: #020617; color: #f8fafc; }
.stamp-content { position: relative; width: 100%; height: 100%; }
.stamp-content > h1, .stamp-content > p { position: relative; z-index: 3; margin-left: auto; margin-right: auto; max-width: 88%; text-align: center; }
.stamp-content > h1 { padding-top: 18%; font-size: clamp(1.35rem, 5vw, 3rem); }
.stamp-scrim { position: absolute; z-index: 2; inset: 0; pointer-events: none; background: #020617; }
.stamp-theme-light { background: #f8fafc; color: #0f172a; }
.stamp-theme-light .stamp-scrim { background: #fff; }
.stamp-theme-dark { background: #020617; color: #f8fafc; }
.artwork-cover { display: block; width: 100%; height: 100vh; max-width: none; object-fit: cover; }
.artwork-safe { position: relative; width: 100%; height: 100vh; overflow: hidden; background: #020617; }
.artwork-backdrop { position: absolute; inset: -6%; width: 112%; height: 112%; max-width: none; object-fit: cover; filter: blur(18px); opacity: .58; transform: scale(1.08); }
.artwork-contain { position: relative; display: block; width: 100%; height: 100vh; max-width: none; object-fit: contain; }
.stamp-copy { position: absolute; z-index: 2; right: 6%; bottom: 8%; left: 6%; color: #fff; text-shadow: 0 2px 18px rgba(2, 6, 23, .9); }
.stamp-copy h1 { margin-bottom: .35rem; font-size: clamp(1.15rem, 4vw, 2.5rem); line-height: 1.05; }
.stamp-copy p { margin-bottom: 0; max-width: 42rem; font-size: clamp(.75rem, 2vw, 1.1rem); }
h1, h2, h3, p { overflow-wrap: anywhere; }
.text-center { text-align: center; }
.mx-auto { margin-left: auto; margin-right: auto; }
.relative { position: relative; }
.absolute { position: absolute; }
.inset-0 { inset: 0; }
.pointer-events-none { pointer-events: none; }
.flex { display: flex; }
.items-center { align-items: center; }
.justify-end { justify-content: flex-end; }
.overflow-hidden { overflow: hidden; }
.rounded-lg { border-radius: 0.5rem; }
.shadow-lg { box-shadow: 0 10px 15px -3px rgba(0,0,0,.3), 0 4px 6px -4px rgba(0,0,0,.3); }
.bg-black { background: #000; }
.text-white { color: #fff; }
.font-serif { font-family: ui-serif, Georgia, Cambria, "Times New Roman", Times, serif; }
.font-normal { font-weight: 400; }
.italic { font-style: italic; }
.tracking-wide { letter-spacing: .025em; }
.tracking-widest { letter-spacing: .1em; }
.text-xs { font-size: .75rem; line-height: 1rem; }
.text-sm { font-size: .875rem; line-height: 1.25rem; }
.text-lg { font-size: 1.125rem; line-height: 1.75rem; }
.text-2xl { font-size: 1.5rem; line-height: 2rem; }
.mb-3 { margin-bottom: .75rem; }
.mb-8 { margin-bottom: 2rem; }
h1, h2, h3, p { margin-top: 0; }
body > p:last-child strong {
    position: fixed;
    right: 1rem;
    bottom: 1rem;
    z-index: 9999;
    display: inline-flex;
    max-width: calc(100% - 2rem);
    align-items: center;
    justify-content: center;
    border-radius: 9999px;
    background: rgba(249, 115, 22, .94);
    color: #fff;
    padding: .55rem .9rem;
    box-shadow: 0 10px 15px -3px rgba(0,0,0,.35), 0 4px 6px -4px rgba(0,0,0,.35);
    font-size: .8125rem;
    line-height: 1.25rem;
    text-align: center;
}
@media (min-width: 640px) {
    .sm\\:text-sm { font-size: .875rem; line-height: 1.25rem; }
    .sm\\:text-2xl { font-size: 1.5rem; line-height: 2rem; }
    .sm\\:text-4xl { font-size: 2.25rem; line-height: 2.5rem; }
}
</style>
</head>
<body>
${body}
</body>
</html>`;
}

function normalizeSource(source?: string | null): RiderOgPreviewSource {
    if (source === 'message' || source === 'url' || source === 'splash') {
        return source;
    }

    return 'default';
}
