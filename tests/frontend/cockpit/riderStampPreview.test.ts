import { describe, expect, it } from 'vitest';
import {
    buildRiderStampPreviewDocument,
    buildRiderSplashContent,
    resolveRiderStampPreview,
} from '../../../resources/js/cockpit/riderStampPreview';

describe('Rider Stamp preview helpers', () => {
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
            label: 'Rider Stamp Preview',
            title: 'Splash headline',
            description: 'Splash body',
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
            label: 'Rider Stamp Preview',
            title: 'The quick brown fox jumps over the lazy dog.',
            reference: 'Rider Message',
        });
    });

    it('builds sandboxed documents without leaking raw text into markup', () => {
        const splash = buildRiderSplashContent({
            headline: 'Issuer <headline>',
            body: 'Plain body & copy',
            cta: 'Continue <now>',
        });

        expect(splash).toContain('Issuer &lt;headline&gt;');
        expect(splash).toContain('Plain body &amp; copy');

        const document = buildRiderStampPreviewDocument(
            resolveRiderStampPreview({
                source: 'splash',
                splashHeadline: 'Issuer splash',
                splashBody: 'Plain splash body',
            }),
            splash,
        );

        expect(document).toContain('Content-Security-Policy');
        expect(document).toContain('Issuer &lt;headline&gt;');
        expect(document).toContain('overflow: hidden');
        expect(document).toContain('overflow-wrap: anywhere');
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
