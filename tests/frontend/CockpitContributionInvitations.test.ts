import { flushPromises, mount } from '@vue/test-utils';
import { afterEach, expect, it, vi } from 'vitest';
import CockpitContributionInvitations from '../../resources/js/cockpit/components/CockpitContributionInvitations.vue';

afterEach(() => vi.unstubAllGlobals());

it('creates a scoped link and can revoke it without losing the link', async () => {
    const fetchMock = vi.fn()
        .mockResolvedValueOnce({ ok: true, json: async () => ({ id: 1, token: 'secret-token', expires_at: '2030-01-01T00:00:00Z', revoke_url: '/revoke/1' }) })
        .mockResolvedValueOnce({ ok: true });
    vi.stubGlobal('fetch', fetchMock);
    const wrapper = mount(CockpitContributionInvitations, { props: { createUrl: '/create', pageUrl: 'https://example.test/x/contribute', types: ['INVOICE'], invitations: [] } });
    await wrapper.get('input[type="checkbox"]').setValue(true);
    await wrapper.get('form').trigger('submit');
    await flushPromises();
    expect((wrapper.get('input[readonly]').element as HTMLInputElement).value).toBe('https://example.test/x/contribute#secret-token');
    expect(JSON.parse(fetchMock.mock.calls[0][1].body).document_types).toEqual(['INVOICE']);
    await wrapper.get('button[type="button"]').trigger('click');
    await flushPromises();
    expect(fetchMock.mock.calls[1][0]).toBe('/revoke/1');
    expect(wrapper.text()).toContain('Revoked');
    wrapper.unmount();
});
