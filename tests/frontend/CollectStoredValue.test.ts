import { mount, flushPromises } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import CollectStoredValue from '../../resources/js/pages/x-change/pos/CollectStoredValue.vue';

vi.mock('@inertiajs/vue3', () => ({ Head: { template: '<div />' } }));

const endpoints = {
    start: '/x/cockpit/stored-value-collections',
    status: '/x/cockpit/stored-value-collections/__REFERENCE__',
    verify: '/x/cockpit/stored-value-collections/__REFERENCE__/verification',
    commit: '/x/cockpit/stored-value-collections/__REFERENCE__/commit',
};

function reply(data: Record<string, unknown>): Response {
    return { ok: true, json: async () => ({ data }) } as Response;
}

describe('stored value collection page', () => {
    beforeEach(() => {
        vi.stubGlobal('crypto', { randomUUID: () => 'sale-001' });
    });

    it('keeps the balance private until OTP and requires a separate collect action', async () => {
        const fetch = vi.fn()
            .mockResolvedValueOnce(reply({ reference: '01TEST', status: 'pending', amount_minor: 2500, currency: 'PHP' }))
            .mockResolvedValueOnce(reply({ reference: '01TEST', status: 'verified', amount_minor: 2500, currency: 'PHP', available_minor: 10000 }))
            .mockResolvedValueOnce(reply({ reference: '01TEST', status: 'committed', amount_minor: 2500, currency: 'PHP', balance_after_minor: 7500 }));
        vi.stubGlobal('fetch', fetch);
        const page = mount(CollectStoredValue, { props: { channel: 'cockpit', endpoints } });

        await page.get('#collection-code').setValue('|| ABCD-1234 ||');
        await page.get('#collection-amount').setValue('25.00');
        await page.get('form').trigger('submit');
        await flushPromises();
        expect(page.text()).toContain('Holder approval code');
        expect(page.text()).not.toContain('Available balance');
        expect(fetch.mock.calls[0][1].headers['Idempotency-Key']).toBe('sale-001');
        expect(JSON.parse(fetch.mock.calls[0][1].body)).toEqual({ code: 'ABCD-1234', amount_minor: 2500, currency: 'PHP' });

        await page.get('#collection-otp').setValue('000000');
        await page.get('form').trigger('submit');
        await flushPromises();
        expect(page.text()).toContain('Available balance: ₱100.00');
        expect(fetch).toHaveBeenCalledTimes(2);

        await page.findAll('button').find((button) => button.text().includes('Collect ₱25.00'))!.trigger('click');
        await flushPromises();
        expect(page.text()).toContain('Payment collected');
        expect(page.text()).toContain('₱75.00');
        expect(fetch.mock.calls[2][0]).toBe('/x/cockpit/stored-value-collections/01TEST/commit');
    });
});
