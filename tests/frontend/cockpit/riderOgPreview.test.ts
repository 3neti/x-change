import { describe, expect, it } from 'vitest';
import {
    buildRiderOgPreviewDocument,
    buildRiderSplashContent,
    resolveRiderOgPreview,
} from '../../../resources/js/cockpit/riderOgPreview';

describe('rider OG preview helpers', () => {
    it('resolves the default preview from splash, message, and URL inputs', () => {
        const preview = resolveRiderOgPreview({
            source: null,
            message: 'Issuer message',
            url: 'https://example.test/rider',
            splashHeadline: 'Splash headline',
            splashBody: 'Splash body',
        });

        expect(preview).toMatchObject({
            source: 'default',
            label: 'Default Preview',
            title: 'Splash headline',
            description: 'Splash body',
            reference: 'Automatic',
        });
    });

    it('resolves explicit message previews', () => {
        const preview = resolveRiderOgPreview({
            source: 'message',
            message: 'The quick brown fox jumps over the lazy dog.',
        });

        expect(preview).toMatchObject({
            source: 'message',
            label: 'Message Preview',
            title: 'The quick brown fox jumps over the lazy dog.',
            reference: 'Message',
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

        const document = buildRiderOgPreviewDocument(
            resolveRiderOgPreview({
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

    it('renders resolved action link artwork as a full-bleed safe image', () => {
        const preview = resolveRiderOgPreview({
            source: 'url',
            url: 'https://open.spotify.com/track/example',
            urlArtwork: {
                available: true,
                source: 'spotify',
                title: 'An Example Track',
                description: 'Spotify',
                image_url: 'https://i.scdn.co/image/example-artwork',
                reference: 'Spotify',
            },
        });
        const document = buildRiderOgPreviewDocument(preview, '');

        expect(preview).toMatchObject({
            title: 'An Example Track',
            description: 'Spotify',
            reference: 'Spotify',
            imageUrl: 'https://i.scdn.co/image/example-artwork',
        });
        expect(document).toContain(
            'src="https://i.scdn.co/image/example-artwork"',
        );
        expect(document).toContain('class="artwork-cover"');
        expect(document).not.toContain('<iframe');
    });
});
