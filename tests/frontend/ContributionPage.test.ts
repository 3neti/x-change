import { flushPromises, mount } from '@vue/test-utils';
import { afterEach, expect, it, vi } from 'vitest';
import ContributionPage from '../../resources/js/pages/x-change/public/Contribution.vue';

afterEach(() => { vi.unstubAllGlobals(); window.location.hash = ''; });

it('unlocks a scoped invitation and uploads a document', async () => {
    window.location.hash = 'example-token';
    const fetchMock = vi.fn()
        .mockResolvedValueOnce({ ok: true, json: async () => ({ verified: false, password_required: true, document_types: [], documents: [] }) })
        .mockResolvedValueOnce({ ok: true })
        .mockResolvedValueOnce({ ok: true, json: async () => ({ verified: true, password_required: true, document_types: ['INVOICE'], documents: [] }) })
        .mockResolvedValueOnce({ ok: true })
        .mockResolvedValueOnce({ ok: true, json: async () => ({ verified: true, password_required: true, document_types: ['INVOICE'], documents: [{ id: 1, name: 'invoice.pdf', type: 'INVOICE', status: 'pending', view_url: '/view/1' }] }) });
    vi.stubGlobal('fetch', fetchMock);
    const wrapper = mount(ContributionPage, { props: { contextUrl: '/context', passwordUrl: '/password', uploadUrl: '/upload' } });
    await flushPromises();
    await wrapper.get('input[type="password"]').setValue('password123');
    await wrapper.get('form').trigger('submit');
    await flushPromises();
    const fileInput = wrapper.get('input[type="file"]');
    Object.defineProperty(fileInput.element, 'files', { value: [new File(['data'], 'invoice.pdf', { type: 'application/pdf' })] });
    await fileInput.trigger('change');
    await wrapper.get('form').trigger('submit');
    await flushPromises();
    expect(wrapper.text()).toContain('invoice.pdf');
    expect(wrapper.text()).toContain('pending');
    expect(wrapper.get('form[action="/view/1"]').attributes('method')).toBe('POST');
    expect(fetchMock.mock.calls[3][1].body.get('document_type')).toBe('INVOICE');
    wrapper.unmount();
});
