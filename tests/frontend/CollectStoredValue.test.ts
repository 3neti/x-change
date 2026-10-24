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
        const page = mount(CollectStoredValue, { props: { channel: 'cockpit', endpoints, fixed_amount_minor: 0 } });

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

    it('uses the configured fare and collects an authorized scan without OTP', async () => {
        const fetch = vi.fn()
            .mockResolvedValueOnce(reply({ reference: '01FARE', status: 'ready', authorization_method: 'collector', amount_minor: 2500, currency: 'PHP', available_minor: 10000 }))
            .mockResolvedValueOnce(reply({ reference: '01FARE', status: 'committed', authorization_method: 'collector', amount_minor: 2500, currency: 'PHP', balance_after_minor: 7500 }));
        vi.stubGlobal('fetch', fetch);
        const page = mount(CollectStoredValue, { props: { channel: 'cockpit', endpoints, fixed_amount_minor: 2500 } });

        await page.get('#collection-code').setValue('|| ABCD-1234 ||');
        expect(page.find('#collection-amount').exists()).toBe(false);
        expect(page.text()).toContain('Fare · ₱25.00');
        expect(page.text()).toContain('configured fare');
        expect(page.text()).toContain('balance is shown after collection authorization');
        await page.get('form').trigger('submit');
        await flushPromises();

        expect(JSON.parse(fetch.mock.calls[0][1].body)).toEqual({ code: 'ABCD-1234', currency: 'PHP' });
        expect(page.text()).toContain('Collector authorized.');
        expect(page.text()).toContain('Check the amount before collecting.');
        expect(page.find('#collection-otp').exists()).toBe(false);

        await page.findAll('button').find((button) => button.text().includes('Collect ₱25.00'))!.trigger('click');
        await flushPromises();
        expect(page.text()).toContain('Payment collected');
        expect(fetch).toHaveBeenCalledTimes(2);
    });

    it('opens an unfinished collection from the exception view', async () => {
        const reference = '01ARZ3NDEKTSV4RRFFQ69G5FAV';
        const fetch = vi.fn().mockResolvedValueOnce(reply({
            reference,
            status: 'ready',
            amount_minor: 2500,
            currency: 'PHP',
            available_minor: 10000,
        }));
        vi.stubGlobal('fetch', fetch);
        const page = mount(CollectStoredValue, {
            props: {
                channel: 'cockpit',
                endpoints,
                fixed_amount_minor: 2500,
                view: 'exceptions',
                recent_collections: [{ reference, status: 'ready', amount_minor: 2500, currency: 'PHP', created_at: null }],
            },
        });

        expect(page.get('[data-testid="stored-value-collection-activity"]').text()).toContain(reference);
        expect(page.find('#collection-code').exists()).toBe(false);
        await page.get('[data-testid="stored-value-collection-activity"] button').trigger('click');
        await flushPromises();

        expect(fetch.mock.calls[0][0]).toBe(`/x/cockpit/stored-value-collections/${reference}`);
        expect(page.text()).toContain('Collect ₱25.00');
        await page.findAll('button').find((button) => button.text() === 'Start a new collection')!.trigger('click');
        expect(page.find('#collection-code').exists()).toBe(true);
    });
});
