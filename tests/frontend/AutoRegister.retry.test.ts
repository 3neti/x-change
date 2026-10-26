import { flushPromises, mount } from '@vue/test-utils';
import { afterEach, beforeEach, expect, it, vi } from 'vitest';
import AutoRegister from '../../resources/js/pages/x-change/public/AutoRegister.vue';

const props = {
    amount_minor: 10000,
    currency: 'PHP',
    endpoints: { start: '/x/auto-register/start', status: '/x/auto-register/__REFERENCE__', complete: '/x/auto-register/__REFERENCE__/complete' },
    turnstile: { enabled: false },
};

beforeEach(() => sessionStorage.clear());
afterEach(() => vi.unstubAllGlobals());

it('reuses one attempt after a provider failure and clears it after checkout starts', async () => {
    const fetch = vi.fn()
        .mockResolvedValueOnce({ ok: false, status: 503, json: async () => ({ message: 'Payment setup is temporarily unavailable.' }) })
        .mockResolvedValueOnce({ ok: true, status: 201, json: async () => ({ reference: 'REG-1', status: 'active' }) });
    vi.stubGlobal('fetch', fetch);
    const wrapper = mount(AutoRegister, { props });

    await wrapper.get('button').trigger('click');
    await flushPromises();
    expect(wrapper.text()).toContain('Payment setup is temporarily unavailable.');
    await wrapper.get('button').trigger('click');
    await flushPromises();

    const firstKey = JSON.parse(fetch.mock.calls[0][1].body).idempotency_key;
    const secondKey = JSON.parse(fetch.mock.calls[1][1].body).idempotency_key;
    expect(secondKey).toBe(firstKey);
    expect(sessionStorage.getItem('x-change.public-auto-register.idempotency-key')).toBeNull();
    expect(sessionStorage.getItem('x-change.public-auto-register.reference')).toBe('REG-1');
    wrapper.unmount();
});

it('starts a fresh attempt after the server rejects an expired attempt key', async () => {
    const fetch = vi.fn()
        .mockResolvedValueOnce({ ok: false, status: 422, json: async () => ({ message: 'This registration attempt expired.', errors: { idempotency_key: ['Expired'] } }) })
        .mockResolvedValueOnce({ ok: true, status: 201, json: async () => ({ reference: 'REG-2', status: 'active' }) });
    vi.stubGlobal('fetch', fetch);
    const wrapper = mount(AutoRegister, { props });

    await wrapper.get('button').trigger('click');
    await flushPromises();
    await wrapper.get('button').trigger('click');
    await flushPromises();

    expect(JSON.parse(fetch.mock.calls[1][1].body).idempotency_key)
        .not.toBe(JSON.parse(fetch.mock.calls[0][1].body).idempotency_key);
    wrapper.unmount();
});
