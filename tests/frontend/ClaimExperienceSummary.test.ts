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
                    content: '<section>Issuer configured splash</section>',
                    content_type: 'html',
                    timeout: 3,
                },
            },
        });

        const message = wrapper.find('[data-testid="claim-experience-message"]');
        const splash = wrapper.find('[data-testid="claim-experience-splash"]');

        expect(wrapper.text()).toContain('Claim Experience');
        expect(message.attributes('sandbox')).toBe('');
        expect(splash.attributes('sandbox')).toBe('');
        expect(message.attributes('srcdoc')).toContain('Claim complete message.');
        expect(splash.attributes('srcdoc')).toContain('Issuer configured splash');
        expect(wrapper.text()).toContain('3s in live flow');
    });

    it('renders rider URL and OG meta without starting a redirect', () => {
        const wrapper = mount(ClaimExperienceSummary, {
            props: {
                redirect: {
                    url: 'https://example.test/rider',
                    delay_seconds: 5,
                    show_countdown: true,
                },
                og_meta: {
                    title: 'A little something',
                    description: 'Open this Pay Code when you are ready.',
                    image_url: '/x/claim/ABCD/share-card.png',
                    image_alt: 'A little something preview',
                },
            },
        });

        const link = wrapper.find('[data-testid="claim-experience-redirect"] a');
        const image = wrapper.find('[data-testid="claim-experience-og-meta"] img');

        expect(link.attributes('href')).toBe('https://example.test/rider');
        expect(wrapper.text()).toContain('example.test');
        expect(wrapper.text()).toContain('A little something');
        expect(wrapper.text()).toContain('Open this Pay Code when you are ready.');
        expect(image.attributes('src')).toBe('/x/claim/ABCD/share-card.png');
    });
});
