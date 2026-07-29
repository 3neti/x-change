import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import CockpitRiderArtworkThumbnail from '../../../resources/js/cockpit/components/CockpitRiderArtworkThumbnail.vue';

describe('Cockpit Rider artwork thumbnail', () => {
    it('renders the native x-change miniature without rich content', () => {
        const wrapper = mount(CockpitRiderArtworkThumbnail, {
            props: {
                source: 'x_change',
            },
        });

        expect(
            wrapper
                .get('[data-testid="cockpit-rider-artwork-thumbnail-x-change"]')
                .text(),
        ).toContain('x-change');
        expect(wrapper.find('iframe').exists()).toBe(false);
        expect(wrapper.find('img').exists()).toBe(false);
    });

    it('renders a resolved Rider Link image as a lazy thumbnail', () => {
        const wrapper = mount(CockpitRiderArtworkThumbnail, {
            props: {
                source: 'url',
                imageUrl: 'https://example.test/artwork.jpg',
                title: 'Resolved artwork',
            },
        });
        const image = wrapper.get(
            '[data-testid="cockpit-rider-artwork-thumbnail-image"]',
        );

        expect(image.attributes('src')).toBe(
            'https://example.test/artwork.jpg',
        );
        expect(image.attributes('loading')).toBe('lazy');
        expect(wrapper.text()).toContain('Resolved artwork');
        expect(wrapper.find('iframe').exists()).toBe(false);
    });

    it('uses display copy when a Splash has no image', () => {
        const wrapper = mount(CockpitRiderArtworkThumbnail, {
            props: {
                source: 'splash',
                title: 'A recipient greeting',
                description: 'Prepared with care',
            },
        });

        expect(
            wrapper
                .get('[data-testid="cockpit-rider-artwork-thumbnail-fallback"]')
                .text(),
        ).toContain('A recipient greeting');
        expect(wrapper.find('iframe').exists()).toBe(false);
    });

    it('renders a neutral miniature when artwork is disabled', () => {
        const wrapper = mount(CockpitRiderArtworkThumbnail, {
            props: {
                source: 'none',
            },
        });

        expect(
            wrapper
                .find('[data-testid="cockpit-rider-artwork-thumbnail-none"]')
                .exists(),
        ).toBe(true);
        expect(wrapper.find('iframe').exists()).toBe(false);
    });

    it('shows a lightweight loading state without rendering an image', () => {
        const wrapper = mount(CockpitRiderArtworkThumbnail, {
            props: {
                source: 'url',
                imageUrl: 'https://example.test/artwork.jpg',
                resolving: true,
            },
        });

        expect(
            wrapper
                .find('[data-testid="cockpit-rider-artwork-thumbnail-loading"]')
                .exists(),
        ).toBe(true);
        expect(wrapper.find('img').exists()).toBe(false);
    });
});
