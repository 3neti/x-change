export type RiderOgPreviewSource = 'default' | 'message' | 'url' | 'splash';

export type RiderOgPreview = {
    source: RiderOgPreviewSource;
    label: string;
    title: string;
    description: string;
    reference: string;
};

export type RiderOgPreviewInput = {
    source?: string | null;
    message?: string | null;
    url?: string | null;
    splashHeadline?: string | null;
    splashBody?: string | null;
    splashCta?: string | null;
};

export function escapeHtml(value: string): string {
    return value
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}

export function looksLikeHtml(value: string): boolean {
    return /<\/?[a-z][\s\S]*>/i.test(value);
}

export function buildRiderSplashContent(input: {
    headline?: string | null;
    body?: string | null;
    cta?: string | null;
}): string {
    const headline = (input.headline ?? '').trim();
    const body = (input.body ?? '').trim();
    const cta = (input.cta ?? '').trim();

    if (headline === '' && cta === '') {
        return body;
    }

    return [
        headline === '' ? null : `<h1>${escapeHtml(headline)}</h1>`,
        body === ''
            ? null
            : looksLikeHtml(body)
              ? body
              : `<p>${escapeHtml(body)}</p>`,
        cta === '' ? null : `<p><strong>${escapeHtml(cta)}</strong></p>`,
    ]
        .filter((item): item is string => item !== null)
        .join('\n');
}

export function resolveRiderOgPreview(
    input: RiderOgPreviewInput,
): RiderOgPreview {
    const source = normalizeSource(input.source);
    const message = (input.message ?? '').trim();
    const url = (input.url ?? '').trim();
    const splashHeadline = (input.splashHeadline ?? '').trim();
    const splashBody = (input.splashBody ?? '').trim();
    const splashCta = (input.splashCta ?? '').trim();

    if (source === 'message') {
        return {
            source,
            label: 'Message Preview',
            title: message === '' ? 'No Message Yet' : message,
            description: 'Preview based on the recipient message.',
            reference: 'Message',
        };
    }

    if (source === 'url') {
        return {
            source,
            label: 'Action Link Preview',
            title: url === '' ? 'No Action URL Yet' : url,
            description: 'Preview based on the selected action link.',
            reference: 'Action URL',
        };
    }

    if (source === 'splash') {
        return {
            source,
            label: 'Claim Introduction Preview',
            title:
                splashHeadline === ''
                    ? splashBody || 'No Introduction Yet'
                    : splashHeadline,
            description:
                splashCta === ''
                    ? splashBody || 'No Introduction Message Yet.'
                    : `${splashBody || 'No Introduction Message Yet.'} · ${splashCta}`,
            reference: 'Claim Introduction',
        };
    }

    return {
        source,
        label: 'Default Preview',
        title:
            splashHeadline ||
            message ||
            (url === '' ? 'Default Claim Preview' : url),
        description:
            splashBody ||
            message ||
            'Uses the first available message, link, or introduction.',
        reference: 'Automatic',
    };
}

export function shouldRenderRiderOgSplash(
    preview: RiderOgPreview,
    splashContent: string,
): boolean {
    return (
        (preview.source === 'default' || preview.source === 'splash') &&
        splashContent.trim() !== ''
    );
}

export function buildRiderOgPreviewDocument(
    preview: RiderOgPreview,
    splashContent: string,
): string {
    return buildSandboxedPreviewDocument(
        shouldRenderRiderOgSplash(preview, splashContent)
            ? splashContent
            : `<h1>${escapeHtml(preview.title)}</h1><p>${escapeHtml(preview.description)}</p>`,
    );
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
html, body { margin: 0; min-height: 100%; background: #020617; color: #f8fafc; font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; }
body { padding: 0; }
img { max-width: 100%; height: auto; }
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
