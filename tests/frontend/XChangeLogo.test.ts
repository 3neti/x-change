import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import XChangeLogo from '../../resources/js/components/x-change/XChangeLogo.vue';
import {
    gClefPulleyBrandAssets,
    xChangeBrandAssets,
} from '../../resources/js/components/x-change/brandAssets';

describe('XChangeLogo', () => {
    it('uses the canonical x-change vector by default', () => {
        const wrapper = mount(XChangeLogo);

        expect(wrapper.get('img').attributes()).toMatchObject({
            src: xChangeBrandAssets.logo,
            alt: 'x-change',
        });
    });

    it('selects the light and mark variants from the shared asset map', () => {
        const light = mount(XChangeLogo, {
            props: { variant: 'light' },
        });
        const mark = mount(XChangeLogo, {
            props: { variant: 'mark' },
        });

        expect(light.get('img').attributes('src')).toBe(
            xChangeBrandAssets.light,
        );
        expect(mark.get('img').attributes('src')).toBe(xChangeBrandAssets.mark);
    });

    it('exposes the canonical g-clef presentation and favicon assets', () => {
        expect(gClefPulleyBrandAssets.logo).toContain(
            '/brand-library/g-clef-pulley/svg/g-clef-pulley-logo.svg',
        );
        expect(gClefPulleyBrandAssets.favicon).toBe(
            '/vendor/x-change/favicon.svg',
        );
    });
});
