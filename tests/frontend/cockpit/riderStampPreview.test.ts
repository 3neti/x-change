import { describe, expect, it } from 'vitest';
import {
    buildRiderStampPreviewDocument,
    buildRiderSplashContent,
    buildSandboxedPreviewDocument,
    firstRiderArtworkImageUrl,
    normalizeRiderArtworkUrl,
    normalizeRiderStampComposition,
    resolveRiderStampPreview,
    resolveRiderStampDesign,
} from '../../../resources/js/cockpit/riderStampPreview';

describe('Rider Stamp preview helpers', () => {
    it('resolves the draft Stamp design from the issuer experience theme', () => {
        expect(resolveRiderStampDesign({ experienceTheme: 'amber' })).toEqual({
            id: 'x-change-amber',
            version: 1,
        });
        expect(
            resolveRiderStampDesign({ experienceTheme: 'steampunk' }),
        ).toEqual({
            id: 'x-change-steampunk',
            version: 1,
        });
    });

    it('keeps an issued Stamp design fixed when personal appearance changes', () => {
        const preview = resolveRiderStampPreview({
            experienceTheme: 'amber',
            designId: 'x-change-steampunk',
            designVersion: 1,
        });

        expect(preview.design).toEqual({
            id: 'x-change-steampunk',
            version: 1,
        });
        expect(buildRiderStampPreviewDocument(preview, '')).toContain(
            'stamp-design-x-change-steampunk',
        );
    });

    it('extracts only trusted thumbnail image schemes from Rider content', () => {
        expect(
            firstRiderArtworkImageUrl(
                '<img src="https://example.test/stamp.jpg" alt="">',
            ),
        ).toBe('https://example.test/stamp.jpg');
        expect(
            firstRiderArtworkImageUrl('<img src="javascript:alert(1)" alt="">'),
        ).toBeNull();
        expect(firstRiderArtworkImageUrl('<p>No artwork</p>')).toBeNull();
    });

    it('normalizes layered Stamp composition independently of legacy source', () => {
        expect(
            normalizeRiderStampComposition({
                source: 'splash',
                artworkSource: 'url',
                artworkTreatment: 'artwork',
                copySource: 'message',
                showLogo: false,
                showTagline: true,
                claimMarker: 'both',
                claimMarkerPosition: 'top_right',
            }),
        ).toEqual({
            artworkSource: 'url',
            artworkTreatment: 'artwork',
            copySource: 'message',
            showLogo: false,
            showTagline: true,
            claimMarker: 'both',
            claimMarkerPosition: 'top_right',
            version: 2,
        });
    });

    it('maps legacy Stamp sources into the v2 composition defaults', () => {
        expect(
            normalizeRiderStampComposition({
                source: 'url',
            }),
        ).toMatchObject({
            artworkSource: 'url',
            artworkTreatment: 'automatic',
            copySource: 'url',
            showLogo: true,
            showTagline: true,
            claimMarker: 'qr',
            claimMarkerPosition: 'bottom_right',
            version: 2,
        });
    });

    it('resolves the default preview from splash, message, and URL inputs', () => {
        const preview = resolveRiderStampPreview({
            source: null,
            message: 'Issuer message',
            url: 'https://example.test/rider',
            splashHeadline: 'Splash headline',
            splashBody: 'Splash body',
        });

        expect(preview).toMatchObject({
            source: 'default',
            label: 'Rider Stamp',
            title: 'Issuer message',
            description: '',
            reference: 'x-change',
        });
    });

    it('resolves explicit message previews', () => {
        const preview = resolveRiderStampPreview({
            source: 'message',
            message: 'The quick brown fox jumps over the lazy dog.',
        });

        expect(preview).toMatchObject({
            source: 'message',
            label: 'Rider Stamp',
            title: 'The quick brown fox jumps over the lazy dog.',
            reference: 'x-change',
        });
    });

    it('keeps image-backed Splash icons in the canvas artwork layer', () => {
        const splash = buildRiderSplashContent({
            headline: 'Issuer <headline>',
            body: `
                <div class="relative">
                    <img src="https://example.test/rose.png" alt="A rose" />
                    <p>🤝 ❤️ ✌️ 🔫 ✈️ ⭐</p>
                </div>
            `,
            cta: 'Continue <now>',
            format: 'html',
        });

        expect(splash).toContain('Issuer &lt;headline&gt;');

        const document = buildRiderStampPreviewDocument(
            resolveRiderStampPreview({
                source: 'splash',
                splashHeadline: 'Issuer splash',
                splashBody: splash,
                artworkSource: 'splash',
            }),
            splash,
        );

        expect(document).toContain('Content-Security-Policy');
        expect(document).toContain('class="splash-canvas-artwork"');
        expect(document).toContain('class="splash-symbols"');
        expect(document).toContain('🤝 ❤️ ✌️ 🔫 ✈️ ⭐');
        expect(document).toContain('src="https://example.test/rose.png"');
        expect(document).not.toContain('Issuer &lt;headline&gt;');
        expect(document).toContain('overflow: hidden');
        expect(document).toContain('overflow-wrap: anywhere');
    });

    it('includes system color-emoji fallbacks in isolated Rider documents', () => {
        const document = buildSandboxedPreviewDocument(
            '<p>🤝 ❤️ ✌️ 🔫 ✈️ ⭐</p>',
        );

        expect(document).toContain('"Apple Color Emoji"');
        expect(document).toContain('"Segoe UI Emoji"');
        expect(document).toContain('"Noto Color Emoji"');
        expect(document).toContain('🤝 ❤️ ✌️ 🔫 ✈️ ⭐');
    });

    it('extracts safe Stamp copy from a reloaded HTML Splash', () => {
        const splash = `
            <div class="presentation">
                <img src="https://example.test/rose.png" alt="A rose" />
                <h2>i carry your heart with me</h2>
                <p>(i carry it in my heart)</p>
                <p>🤝 &nbsp; ❤️ &nbsp; ✌️ &nbsp; 🔫 &nbsp; ✈️ &nbsp; ⭐</p>
                <p>&mdash; e.e. cummings</p>
            </div>
        `;
        const preview = resolveRiderStampPreview({
            source: 'splash',
            splashBody: splash,
            artworkSource: 'splash',
            copySource: 'splash',
        });

        expect(preview).toMatchObject({
            title: 'i carry your heart with me',
            description:
                '(i carry it in my heart) · 🤝 ❤️ ✌️ 🔫 ✈️ ⭐ · — e.e. cummings',
        });
        expect(preview.title).not.toContain('<');
        expect(preview.description).not.toContain('<');
    });

    it('renders explicit Rider Splash formats without guessing from content', () => {
        const plain = buildRiderSplashContent({
            body: '<strong>Plain</strong>',
            format: 'plain',
        });
        const markdown = buildRiderSplashContent({
            body: '**Bold** [unsafe](javascript:alert(1))',
            format: 'markdown',
        });
        const html = buildRiderSplashContent({
            body: '<strong>HTML</strong>',
            format: 'html',
        });

        expect(plain).toContain('&lt;strong&gt;Plain&lt;/strong&gt;');
        expect(markdown).toContain('<strong>Bold</strong>');
        expect(markdown).not.toContain('javascript:');
        expect(html).toBe('<strong>HTML</strong>');
    });

    it('normalizes GitHub Splash artwork for both the claim preview and canvas', () => {
        const githubUrl =
            'https://github.com/lbhurtado/failure-of-simultaneity/blob/main/planetary-rose.PNG?raw=true';
        const rawUrl =
            'https://raw.githubusercontent.com/lbhurtado/failure-of-simultaneity/main/planetary-rose.PNG';
        const splash = buildRiderSplashContent({
            body: `<img src="${githubUrl}" alt="Planetary rose">`,
            format: 'html',
        });
        const claimPreview = buildSandboxedPreviewDocument(splash);
        const canvas = buildRiderStampPreviewDocument(
            resolveRiderStampPreview({
                source: 'splash',
                splashBody: splash,
                artworkSource: 'splash',
            }),
            splash,
            'canvas',
        );

        expect(normalizeRiderArtworkUrl(githubUrl)).toBe(rawUrl);
        expect(splash).toContain(`src="${rawUrl}"`);
        expect(claimPreview).toContain(`src="${rawUrl}"`);
        expect(canvas).toContain(`src="${rawUrl}"`);
        expect(claimPreview).not.toContain('github.com/lbhurtado');
    });

    it('renders URL artwork according to the Rider Stamp presentation contract', () => {
        const preview = resolveRiderStampPreview({
            source: 'url',
            url: 'https://open.spotify.com/track/example',
            fit: 'contain',
            position: 'top',
            scrim: 24,
            theme: 'dark',
            urlArtwork: {
                available: true,
                source: 'spotify',
                title: 'An Example Track',
                description: 'Spotify',
                image_url: 'https://i.scdn.co/image/example-artwork',
                reference: 'Spotify',
            },
        });
        const canvasDocument = buildRiderStampPreviewDocument(
            preview,
            '',
            'canvas',
        );
        const stampDocument = buildRiderStampPreviewDocument(
            preview,
            '',
            'stamp',
        );

        expect(preview).toMatchObject({
            title: 'An Example Track',
            description: 'Spotify',
            reference: 'Spotify',
            imageUrl: 'https://i.scdn.co/image/example-artwork',
        });
        expect(canvasDocument).toContain(
            'src="https://i.scdn.co/image/example-artwork"',
        );
        expect(canvasDocument).toContain('class="artwork-backdrop"');
        expect(canvasDocument).toContain('class="artwork-contain"');
        expect(canvasDocument).toContain('object-position: center top');
        expect(stampDocument).toContain('class="stamp-copy"');
        expect(stampDocument).toContain('opacity: 0.24');
        expect(stampDocument).toContain('stamp-theme-dark');
        expect(stampDocument).not.toContain('<iframe');
    });

    it('honors Rider Stamp copy overrides independently of the source', () => {
        const preview = resolveRiderStampPreview({
            source: 'message',
            message: 'Source message',
            title: 'Share title',
            description: 'Share description',
        });

        expect(preview).toMatchObject({
            source: 'message',
            title: 'Share title',
            description: 'Share description',
            fit: 'cover',
            position: 'center',
            scrim: 18,
            theme: 'automatic',
        });
    });
});
