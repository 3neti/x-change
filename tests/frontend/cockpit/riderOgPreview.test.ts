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
            label: 'Default preview',
            title: 'Splash headline',
            description: 'Splash body',
            reference: 'rider.og_source: default',
        });
    });

    it('resolves explicit message previews', () => {
        const preview = resolveRiderOgPreview({
            source: 'message',
            message: 'The quick brown fox jumps over the lazy dog.',
        });

        expect(preview).toMatchObject({
            source: 'message',
            label: 'Message preview',
            title: 'The quick brown fox jumps over the lazy dog.',
            reference: 'rider.message',
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
    });
});
