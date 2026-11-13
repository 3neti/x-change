import { flushPromises, mount } from '@vue/test-utils';
import { expect, it, vi } from 'vitest';
import OwnerApiClients from '../../resources/js/pages/x-change/cockpit/OwnerApiClients.vue';

vi.mock('@inertiajs/vue3', () => ({ Head: { template: '<span />' }, router: { reload: vi.fn(), post: vi.fn() } }));
vi.mock('@/cockpit/layouts/CockpitLayout.vue', () => ({ default: { template: '<div><slot /></div>' } }));

it('submits owner settings and displays the returned one-time secret', async () => {
    const fetchMock = vi.fn().mockResolvedValue({ ok: true, json: async () => ({ client_id: 'client-1', client_secret: 'secret-once' }) });
    vi.stubGlobal('fetch', fetchMock);
    const wrapper = mount(OwnerApiClients, { props: { clients: [], scopes: { 'pay-codes:issue': 'Issue Pay Codes' }, store_url: '/owner-api', csrf_token: 'csrf' } });
    await wrapper.get('input[required][maxlength="120"]').setValue('My client');
    await wrapper.get('input[type="checkbox"][required]').setValue(true);
    await wrapper.get('form').trigger('submit');
    await flushPromises();
    const payload = JSON.parse(fetchMock.mock.calls[0][1].body);
    expect(payload).not.toHaveProperty('issuer_id');
    expect(payload).not.toHaveProperty('issuer_type');
    expect(wrapper.text()).toContain('secret-once');
    await wrapper.get('section button').trigger('click');
    expect(wrapper.text()).not.toContain('secret-once');
    vi.unstubAllGlobals();
});
