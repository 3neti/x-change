import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import ClaimExperienceSummary from '../../resources/js/components/x-change/ClaimExperienceSummary.vue';

vi.mock('lucide-vue-next', () => ({
    ExternalLink: { template: '<span />' },
    ImageIcon: { template: '<span />' },
    MessageSquareText: { template: '<span />' },
    Share2: { template: '<span />' },
}));

describe('ClaimExperienceSummary', () => {
    it('renders nothing when no rider experience exists', () => {
        const wrapper = mount(ClaimExperienceSummary, {
            props: {},
        });

        expect(wrapper.find('[data-testid="claim-experience-summary"]').exists()).toBe(false);
    });

    it('renders rider message and splash as sandboxed static previews', () => {
        const wrapper = mount(ClaimExperienceSummary, {
            props: {
                message: {
                    content: '<p>Claim complete message.</p>',
                    content_type: 'html',
                },
                splash: {
                    content: '<section class="relative"><img src="/splash.png"><div class="absolute inset-0 flex items-center justify-end text-white">Issuer configured splash</div></section>',
                    content_type: 'html',
                    timeout: 3,
                },
            },
        });

        const message = wrapper.find('[data-testid="claim-experience-message"]');
        const splash = wrapper.find('[data-testid="claim-experience-splash"]');

        expect(wrapper.text()).toContain('Claim Experience');
        expect(message.attributes('sandbox')).toBe('allow-same-origin');
        expect(splash.attributes('sandbox')).toBe('allow-same-origin');
        expect(message.attributes('scrolling')).toBe('no');
        expect(splash.attributes('scrolling')).toBe('no');
        expect(message.attributes('srcdoc')).toContain('Claim complete message.');
        expect(splash.attributes('srcdoc')).toContain('Issuer configured splash');
        expect(splash.attributes('srcdoc')).toContain('.absolute{position:absolute;}');
        expect(splash.attributes('srcdoc')).toContain('.inset-0{inset:0;}');
        expect(splash.attributes('srcdoc')).toContain('.items-center{align-items:center;}');
        expect(wrapper.text()).toContain('3s in live flow');
    });

    it('renders rider URL metadata without starting a redirect', () => {
        const wrapper = mount(ClaimExperienceSummary, {
            props: {
                redirect: {
                    url: 'https://open.spotify.com/track/example',
                    delay_seconds: 5,
                    show_countdown: true,
                },
                og_meta: {
                    title: 'An Example Track',
                    description: 'An Example Artist',
                    url: 'https://open.spotify.com/track/example',
                    site_name: 'Spotify',
                    image_url: 'data:image/jpeg;base64,ZmFrZQ==',
                    image_alt: 'An Example Track preview',
                },
            },
        });

        const link = wrapper.find('[data-testid="claim-experience-redirect"] a');
        const image = wrapper.find('[data-testid="claim-experience-og-meta"] img');

        expect(link.attributes('href')).toBe('https://open.spotify.com/track/example');
        expect(wrapper.text()).toContain('open.spotify.com');
        expect(wrapper.text()).toContain('Rider URL preview');
        expect(wrapper.text()).toContain('Link preview copy');
        expect(wrapper.text()).toContain('An Example Track');
        expect(wrapper.text()).toContain('An Example Artist');
        expect(wrapper.text()).not.toContain('₱25.00');
        expect(wrapper.text()).not.toContain('Enjoy the ride.');
        expect(image.attributes('src')).toBe('data:image/jpeg;base64,ZmFrZQ==');
        expect(wrapper.find('[data-testid="claim-experience-og-image"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="claim-experience-og-copy"]').exists()).toBe(true);
    });
});
