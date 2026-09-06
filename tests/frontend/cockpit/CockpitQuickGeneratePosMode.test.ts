import { flushPromises, mount, shallowMount } from '@vue/test-utils';
import { afterEach, describe, expect, it, vi } from 'vitest';
import CockpitQuickGeneratePosPanel from '../../../resources/js/cockpit/components/CockpitQuickGeneratePosPanel.vue';
import QuickGenerate from '../../../resources/js/cockpit/pages/QuickGenerate.vue';

const poll = vi.hoisted(() => ({
    start: vi.fn(),
    stop: vi.fn(),
    usePoll: vi.fn(),
}));

vi.mock('@inertiajs/vue3', () => ({
    Link: {
        props: ['href'],
        template: '<a :href="href?.url ?? href"><slot /></a>',
    },
    usePoll: (...args: unknown[]) => {
        poll.usePoll(...args);

        return {
            start: poll.start,
            stop: poll.stop,
        };
    },
}));

const mutationContract = {
    runtime_enabled: true,
    route: 'x-change.cockpit.quick-generate.store',
    route_url: '/x/cockpit/quick-generate',
    allowed_methods: ['GET', 'POST'],
};

afterEach(() => {
    vi.clearAllMocks();
    vi.unstubAllGlobals();
    vi.useRealTimers();
});

describe('Cockpit Quick Generate POS mode', () => {
    it('switches the existing Issuance surface between Composer and POS', async () => {
        const wrapper = shallowMount(QuickGenerate, {
            props: {
                collection_destination: {
                    schema: 'x-change.cockpit.collection-destination.v1',
                    label: 'Your Client Funds',
                    description:
                        'Payments are credited to the collection account authorized for the signed-in operator.',
                    authority: 'authenticated_operator',
                    status: 'ready',
                    editable: false,
                    managed_automatically: true,
                },
                quick_generate_read_model: {
                    status: 'available',
                    authorized: true,
                    mutation_contract: mutationContract,
                },
            },
            global: {
                stubs: {
                    CockpitLayout: {
                        template: '<main><slot /></main>',
                    },
                },
            },
        });

        expect(
            wrapper
                .findComponent({ name: 'CockpitQuickGenerateSubmitPanel' })
                .exists(),
        ).toBe(true);

        wrapper
            .findComponent({ name: 'CockpitQuickGenerateSubmitPanel' })
            .vm.$emit('update:issuanceSurface', 'pos');
        await wrapper.vm.$nextTick();

        expect(
            wrapper
                .findComponent({ name: 'CockpitQuickGeneratePosPanel' })
                .exists(),
        ).toBe(true);
        wrapper
            .findComponent({ name: 'CockpitQuickGeneratePosPanel' })
            .vm.$emit('update:issuanceSurface', 'composer');
        await wrapper.vm.$nextTick();

        expect(
            wrapper
                .findComponent({ name: 'CockpitQuickGenerateSubmitPanel' })
                .exists(),
        ).toBe(true);
    });

    it('keeps the compact surface switch available inside the POS workspace', async () => {
        const wrapper = mount(CockpitQuickGeneratePosPanel, {
            props: { mutationContract },
        });

        expect(
            wrapper
                .get('[data-testid="cockpit-quick-generate-surface-pos"]')
                .attributes('aria-pressed'),
        ).toBe('true');

        await wrapper
            .get('[data-testid="cockpit-quick-generate-surface-composer"]')
            .trigger('click');

        expect(wrapper.emitted('update:issuanceSurface')).toEqual([
            ['composer'],
        ]);
    });

    it('issues from amount and reference, renders QR, polls, confirms payment, and resets', async () => {
        vi.useFakeTimers();
        vi.setSystemTime(new Date('2026-08-27T03:45:00Z'));

        const fetchMock = vi
            .fn()
            .mockResolvedValueOnce({
                ok: true,
                json: vi.fn().mockResolvedValue({
                    status: 'issued',
                    result: {
                        code: 'POS5',
                        pos_reference: {
                            sale_reference: 'POS-20260827-01HZZZZZZZZZZZZZZZZZZZZZZZ',
                            order_reference: 'ORDER-2048',
                            purpose: 'Merienda',
                        },
                        links: {
                            collection_attempt:
                                '/x/cockpit/pay-codes/POS5/collection-attempts',
                            payment: '/x/pay/POS5',
                        },
                    },
                }),
            })
            .mockResolvedValueOnce({
                ok: true,
                json: vi.fn().mockResolvedValue({
                    attempt: {
                        reference: '01POSATTEMPT',
                        status: 'awaiting_payment',
                        provider: 'netbank',
                        amount_minor: 5075,
                        currency: 'PHP',
                        expires_at: '2026-08-27T04:00:00Z',
                        qr_code: {
                            mime_type: 'image/png',
                            base64_payload: 'POSQR',
                            qr_mode: 'dynamic',
                            transaction_type: 'P2M',
                            embedded_amount: true,
                        },
                    },
                }),
            });
        vi.stubGlobal('fetch', fetchMock);

        const wrapper = mount(CockpitQuickGeneratePosPanel, {
            props: { mutationContract },
        });

        await wrapper
            .get('[data-testid="cockpit-pos-amount"]')
            .setValue('50.75');
        await wrapper
            .get('[data-testid="cockpit-pos-purpose"]')
            .setValue('Merienda');
        await wrapper
            .get('[data-testid="cockpit-pos-order-reference"]')
            .setValue('ORDER-2048');
        await wrapper
            .get('[data-testid="cockpit-pos-generate"]')
            .trigger('submit');
        await flushPromises();

        const issuanceRequest = fetchMock.mock.calls[0];
        const payload = JSON.parse(
            (issuanceRequest[1] as RequestInit).body as string,
        );

        expect(issuanceRequest[0]).toBe('/x/cockpit/quick-generate');
        expect(payload).toMatchObject({
            cash: {
                amount: 50.75,
                currency: 'PHP',
                validation: { country: 'PH' },
            },
            voucher_type: 'payable',
            target_amount: 50.75,
            count: 1,
            metadata: {
                custom: {
                    cockpit: {
                        source: 'cockpit.quick-generate',
                        builder: 'pos',
                        purpose: 'Merienda',
                        order_reference: 'ORDER-2048',
                        payee: {
                            kind: 'open',
                            explicit_secret: false,
                        },
                    },
                },
            },
        });
        expect(payload.metadata.custom).not.toHaveProperty('external_reference');
        expect(payload.metadata.custom.cockpit).not.toHaveProperty('sale_reference');
        expect(payload.metadata).not.toHaveProperty('collection_wallet_id');
        expect((issuanceRequest[1] as RequestInit).headers).toMatchObject({
            Accept: 'application/json',
            'Idempotency-Key': expect.any(String),
        });
        expect(fetchMock.mock.calls[1][0]).toBe(
            '/x/cockpit/pay-codes/POS5/collection-attempts',
        );
        expect(
            wrapper
                .get('[data-testid="cockpit-pos-payment-qr"]')
                .attributes('src'),
        ).toBe('data:image/png;base64,POSQR');
        expect(wrapper.get('[data-testid="cockpit-pos-sale-reference"]').text()).toContain('POS-20260827');
        expect(wrapper.get('[data-testid="cockpit-pos-issued-order-reference"]').text()).toContain('ORDER-2048');
        expect(wrapper.get('[data-testid="cockpit-pos-issued-purpose"]').text()).toBe('Merienda');
        expect(poll.start).toHaveBeenCalledTimes(1);
        expect(poll.usePoll).toHaveBeenCalledWith(5000, expect.any(Function), {
            autoStart: false,
            mode: 'rest',
        });

        await wrapper.setProps({
            posVoucher: {
                code: 'POS5',
                status: 'paid',
                collection: {
                    schema: 'x-change.cockpit.pay-code-collection.v1',
                    consumer_status: 'paid',
                    currency: 'PHP',
                    target_amount_minor: 5075,
                    collected_total_minor: 5075,
                    remaining_to_collect_minor: 0,
                    is_fully_collected: true,
                    is_overpaid: false,
                    overpaid_amount_minor: 0,
                },
            },
        });
        await wrapper.vm.$nextTick();

        expect(
            wrapper.get('[data-testid="cockpit-pos-paid"]').text(),
        ).toContain('₱50.75');
        expect(
            wrapper.get('[data-testid="cockpit-pos-confirmed-at"]').text(),
        ).toContain('Confirmed on this screen');
        expect(poll.stop).toHaveBeenCalled();

        await wrapper
            .get('[data-testid="cockpit-pos-new-sale"]')
            .trigger('click');

        expect(
            wrapper.get<HTMLInputElement>('[data-testid="cockpit-pos-amount"]')
                .element.value,
        ).toBe('');
        expect(
            wrapper.get<HTMLInputElement>(
                '[data-testid="cockpit-pos-purpose"]',
            ).element.value,
        ).toBe('');
        expect(
            wrapper.get<HTMLInputElement>(
                '[data-testid="cockpit-pos-order-reference"]',
            ).element.value,
        ).toBe('');
        expect(
            wrapper.find('[data-testid="cockpit-pos-payment-qr"]').exists(),
        ).toBe(false);
    });

    it('keeps the issued payment fallback visible if QR preparation is throttled', async () => {
        vi.stubGlobal(
            'fetch',
            vi
                .fn()
                .mockResolvedValueOnce({
                    ok: true,
                    json: vi.fn().mockResolvedValue({
                        result: {
                            code: 'SAFE',
                            links: {
                                collection_attempt:
                                    '/x/cockpit/pay-codes/SAFE/collection-attempts',
                                payment: '/x/pay/SAFE',
                            },
                        },
                    }),
                })
                .mockResolvedValueOnce({
                    ok: false,
                    json: vi.fn().mockResolvedValue({
                        message: 'Too Many Attempts.',
                    }),
                }),
        );

        const wrapper = mount(CockpitQuickGeneratePosPanel, {
            props: { mutationContract },
        });

        await wrapper.get('[data-testid="cockpit-pos-amount"]').setValue('50');
        await wrapper
            .get('[data-testid="cockpit-pos-generate"]')
            .trigger('submit');
        await flushPromises();

        expect(
            wrapper.get('[data-testid="cockpit-pos-error"]').text(),
        ).toContain('Too Many Attempts.');
        expect(
            wrapper
                .get('[data-testid="cockpit-pos-error"] a')
                .attributes('href'),
        ).toBe('/x/pay/SAFE');
        expect(poll.start).toHaveBeenCalledTimes(1);
    });

    it('shows the first Laravel field error instead of the generic validation message', async () => {
        vi.stubGlobal(
            'fetch',
            vi.fn().mockResolvedValueOnce({
                ok: false,
                json: vi.fn().mockResolvedValue({
                    message: 'The given data was invalid.',
                    errors: {
                        target_amount: [
                            'The amount to collect must be greater than zero.',
                        ],
                    },
                }),
            }),
        );

        const wrapper = mount(CockpitQuickGeneratePosPanel, {
            props: { mutationContract },
        });

        await wrapper.get('[data-testid="cockpit-pos-amount"]').setValue('50');
        await wrapper
            .get('[data-testid="cockpit-pos-generate"]')
            .trigger('submit');
        await flushPromises();

        expect(
            wrapper.get('[data-testid="cockpit-pos-error"]').text(),
        ).toContain('The amount to collect must be greater than zero.');
        expect(
            wrapper.get('[data-testid="cockpit-pos-error"]').text(),
        ).not.toContain('The given data was invalid.');
    });
});
