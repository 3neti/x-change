import { flushPromises, mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import CockpitDocumentReview from '../../resources/js/cockpit/components/CockpitDocumentReview.vue';

const { reload } = vi.hoisted(() => ({ reload: vi.fn() }));
vi.mock('@inertiajs/vue3', () => ({ router: { reload } }));

describe('document review', () => {
    it('requires a reason before requesting resubmission', async () => {
        const request = vi.fn();
        vi.stubGlobal('fetch', request);
        const wrapper = mount(CockpitDocumentReview, { props: { reviewUrl: '/review' } });
        await wrapper.findAll('button')[1].trigger('click');
        await wrapper.findAll('button')[2].trigger('click');
        expect(wrapper.get('[role="alert"]').text()).toContain('Enter a reason');
        expect(request).not.toHaveBeenCalled();
        vi.unstubAllGlobals();
    });

    it('submits an approval and reloads the workspace', async () => {
        const request = vi.fn().mockResolvedValue({ ok: true });
        vi.stubGlobal('fetch', request);
        const wrapper = mount(CockpitDocumentReview, { props: { reviewUrl: '/review' } });
        await wrapper.findAll('button')[0].trigger('click');
        await flushPromises();
        expect(request).toHaveBeenCalledWith('/review', expect.objectContaining({ method: 'POST', body: JSON.stringify({ decision: 'accepted' }) }));
        expect(reload).toHaveBeenCalledOnce();
        vi.unstubAllGlobals();
    });
});
