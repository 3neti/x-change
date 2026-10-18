import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import Designer from '../../../resources/js/cockpit/components/CockpitStampDesigner.vue';
import {
    defaultStampPresentation,
    changeStampPresentation,
} from '../../../resources/js/cockpit/stampQrPresentation';
const cats = {
    id: 'cats',
    revision: 7,
    name: 'Cats',
    thumbnail: '/cats.png',
    customization: {
        controls: ['crop', 'darkening', 'tagline', 'qr'],
        defaults: {},
    },
};
describe('simple Stamp designer', () => {
    it('resets to template defaults and drops frozen configuration only on an explicit edit', () => {
        expect(defaultStampPresentation(cats)?.options).toEqual({
            preset: 'transparent',
            crop: 'center',
            darkening: 0,
            show_tagline: true,
        });
        const frozen = {
            options: { preset: 'clear' as const },
            resolved: { paper: 1 },
        };
        expect(changeStampPresentation(frozen, { darkening: 20 })).toEqual({
            options: { preset: 'clear', darkening: 20 },
        });
        expect(frozen.resolved.paper).toBe(1);
        expect(defaultStampPresentation()).toBeNull();
    });
    it('keeps old revisions pinned until a design card is selected and hides unsupported crop', async () => {
        const wrapper = mount(Designer, {
            props: {
                designs: [
                    cats,
                    {
                        ...cats,
                        id: 'steampunk',
                        name: 'Steampunk',
                        customization: { controls: ['qr'], defaults: {} },
                    },
                ],
                designId: 'cats',
                revision: 6,
                presentation: null,
                customMessage: false,
                message: '',
            },
        });
        expect(wrapper.text()).toContain('Saved design preserved');
        expect(wrapper.find('[aria-label="Artwork crop"]').exists()).toBe(
            false,
        );
        expect(wrapper.text()).not.toContain('v7');
        await wrapper.get('button').trigger('click');
        expect(wrapper.emitted('choose')).toEqual([['cats']]);
        await wrapper.setProps({ revision: 7 });
        expect(wrapper.find('[aria-label="Artwork crop"]').exists()).toBe(true);
        await wrapper.get('[aria-label="QR appearance"]').setValue('soft');
        expect(wrapper.emitted('change')).toEqual([[{ preset: 'soft' }]]);
        await wrapper.setProps({ designId: 'steampunk' });
        expect(wrapper.find('[aria-label="Artwork crop"]').exists()).toBe(
            false,
        );
        await wrapper.setProps({ legacyArtwork: true });
        expect(wrapper.find('[aria-label="QR appearance"]').exists()).toBe(
            false,
        );
        expect(wrapper.text()).toContain('Custom artwork is active');
        wrapper.unmount();
    });
});
