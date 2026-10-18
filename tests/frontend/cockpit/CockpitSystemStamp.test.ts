import { mount, flushPromises } from '@vue/test-utils';
import { afterEach, describe, expect, it, vi } from 'vitest';
import Canvas from '../../../resources/js/cockpit/components/CockpitPayCodeCanvas.vue';
import { resolveRiderStampPreview } from '../../../resources/js/cockpit/riderStampPreview';
import Stamp from '../../../resources/js/cockpit/components/CockpitSystemStamp.vue';
afterEach(() => {
    vi.useRealTimers();
    vi.unstubAllGlobals();
});
describe('portable Stamp preview', () => {
    it('renders the selected revision with live data and no theme dependency', async () => {
        vi.useFakeTimers();
        const fetch = vi.fn().mockResolvedValue({
            ok: true,
            json: async () => ({ image: 'data:image/png;base64,abc', provider_identity: { code: 'netbank', name: 'NetBank' } }),
        });
        vi.stubGlobal('fetch', fetch);
        const wrapper = mount(Stamp, {
            props: {
                designId: 'cats',
                revision: 1,
                slots: {
                    brand: 'Pay Code',
                    amount: '₱100',
                    recipient: 'Amelia',
                    purpose: 'Hi',
                },
            },
        });
        await vi.advanceTimersByTimeAsync(350);
        await flushPromises();
        expect(JSON.parse(fetch.mock.calls[0][1].body)).toMatchObject({
            design_id: 'cats',
            revision: 1,
            slots: { amount: '₱100' },
        });
        expect(wrapper.find('img').attributes('src')).toBe(
            'data:image/png;base64,abc',
        );
        expect(wrapper.find('img').attributes('alt')).toContain('Amelia');
        expect(wrapper.find('img').attributes('alt')).toMatch(/^NetBank Pay Code:/);
        wrapper.element.classList.add('dark');
        await wrapper.vm.$nextTick();
        expect(wrapper.find('img').attributes('src')).toBe(
            'data:image/png;base64,abc',
        );
        expect(fetch).toHaveBeenCalledTimes(1);
        wrapper.unmount();
    });
    it('reports a missing design without displaying a previous image', async () => {
        vi.useFakeTimers();
        vi.stubGlobal(
            'fetch',
            vi.fn().mockResolvedValue({
                ok: false,
                json: async () => ({ message: 'Revision unavailable' }),
            }),
        );
        const wrapper = mount(Stamp, {
            props: {
                designId: 'cats',
                revision: 99,
                slots: { amount: '₱100' },
            },
        });
        await vi.advanceTimersByTimeAsync(350);
        await flushPromises();
        expect(wrapper.get('[role="alert"]').text()).toBe(
            'Revision unavailable',
        );
        expect(wrapper.find('img').exists()).toBe(false);
        wrapper.unmount();
    });
});

it('retries a failed preview without keeping the error visible', async () => {
    vi.useFakeTimers();
    vi.stubGlobal(
        'fetch',
        vi
            .fn()
            .mockResolvedValueOnce({
                ok: false,
                json: async () => ({ message: 'Temporarily unavailable' }),
            })
            .mockResolvedValueOnce({
                ok: true,
                json: async () => ({
                    image: 'data:image/png;base64,recovered',
                }),
            }),
    );
    const wrapper = mount(Stamp, {
        props: { designId: 'cats', revision: 1, slots: { amount: '₱100' } },
    });
    await vi.advanceTimersByTimeAsync(350);
    await flushPromises();
    await wrapper.get('button').trigger('click');
    await vi.advanceTimersByTimeAsync(350);
    await flushPromises();
    expect(wrapper.find('[role="alert"]').exists()).toBe(false);
    expect(wrapper.find('img').attributes('src')).toBe(
        'data:image/png;base64,recovered',
    );
    wrapper.unmount();
});

it('does not replace a newer amount with an older response', async () => {
    vi.useFakeTimers();
    let finishFirst: (value: unknown) => void = () => {};
    vi.stubGlobal(
        'fetch',
        vi
            .fn()
            .mockImplementationOnce(
                () =>
                    new Promise((resolve) => {
                        finishFirst = resolve;
                    }),
            )
            .mockResolvedValueOnce({
                ok: true,
                json: async () => ({ image: 'data:image/png;base64,new' }),
            }),
    );
    const wrapper = mount(Stamp, {
        props: { designId: 'cats', revision: 1, slots: { amount: '₱100' } },
    });
    await vi.advanceTimersByTimeAsync(350);
    await wrapper.setProps({ slots: { amount: '₱200' } });
    await vi.advanceTimersByTimeAsync(350);
    await flushPromises();
    finishFirst({
        ok: true,
        json: async () => ({ image: 'data:image/png;base64,old' }),
    });
    await flushPromises();
    expect(wrapper.find('img').attributes('src')).toBe(
        'data:image/png;base64,new',
    );
    expect(wrapper.find('img').attributes('alt')).toContain('₱200');
    wrapper.unmount();
});

it('rerenders expiration and input requirements through the shared Pay Code canvas', async () => {
    vi.useFakeTimers();
    const fetch = vi
        .fn()
        .mockResolvedValueOnce({
            ok: true,
            json: async () => ({ image: 'data:image/png;base64,initial' }),
        })
        .mockResolvedValueOnce({
            ok: true,
            json: async () => ({ image: 'data:image/png;base64,updated' }),
        })
        .mockResolvedValueOnce({
            ok: true,
            json: async () => ({ image: 'data:image/png;base64,cleared' }),
        });
    vi.stubGlobal('fetch', fetch);
    const wrapper = mount(Canvas, {
        props: {
            amount: 150,
            currency: 'PHP',
            claimOutcome: 'provider_disbursement',
            voucherType: 'redeemable',
            expiry: 'No Expiration',
            instructionKeys: [],
            riderStamp: {
                ...resolveRiderStampPreview({}),
                design: { id: 'cats', version: 1 },
            },
        },
    });
    await vi.advanceTimersByTimeAsync(350);
    await flushPromises();
    expect(
        wrapper.get('[data-testid="system-stamp"] img').attributes('src'),
    ).toBe('data:image/png;base64,initial');

    await wrapper.setProps({
        expiry: '3 Days',
        instructionKeys: ['input.name'],
    });
    await vi.advanceTimersByTimeAsync(350);
    await flushPromises();
    expect(JSON.parse(fetch.mock.calls[1][1].body).slots).toMatchObject({
        expiry: '3 Days',
        instructions: 'Receive Funds · Full Name',
    });
    const image = wrapper.get('[data-testid="system-stamp"] img');
    expect(image.attributes('src')).toBe('data:image/png;base64,updated');
    expect(image.attributes('alt')).toContain('3 Days');
    expect(image.attributes('alt')).toContain('Full Name');

    await wrapper.setProps({ expiry: 'No Expiration', instructionKeys: [] });
    await vi.advanceTimersByTimeAsync(350);
    await flushPromises();
    expect(JSON.parse(fetch.mock.calls[2][1].body).slots).toMatchObject({
        expiry: 'No Expiration',
        instructions: 'Receive Funds',
    });
    expect(
        wrapper.get('[data-testid="system-stamp"] img').attributes('src'),
    ).toBe('data:image/png;base64,cleared');
    wrapper.unmount();
});

it.each(['cats', 'x-change-steampunk'])(
    'uses the sidebar claim-entry QR before issuance in %s Stamps',
    async (designId) => {
        vi.useFakeTimers();
        vi.stubGlobal(
            'fetch',
            vi.fn().mockResolvedValue({
                ok: true,
                json: async () => ({
                    image: 'data:image/png;base64,stamp',
                }),
            }),
        );
        const artifact = {
            kind: 'claim_entry' as const,
            destination: 'https://example.test/x/claim',
            image_data_uri: 'data:image/png;base64,entry',
            title: 'Enter Pay Code',
            description: 'Scan to open the Pay Code entry page.',
            identifier: null,
            center_mark: 'pay_code' as const,
        };
        const wrapper = mount(Canvas, {
            props: {
                amount: 150,
                currency: 'PHP',
                claimOutcome: 'provider_disbursement',
                voucherType: 'redeemable',
                riderStamp: {
                    ...resolveRiderStampPreview({}),
                    design: { id: designId, version: 1 },
                },
                claimEntryArtifact: artifact,
                interactiveClaimQr: true,
            },
            global: { stubs: { Teleport: true } },
        });
        await vi.advanceTimersByTimeAsync(350);
        await flushPromises();
        expect(
            wrapper.get('img[alt="Enter Pay Code QR"]').attributes('src'),
        ).toBe(artifact.image_data_uri);
        window.dispatchEvent(
            new CustomEvent('x-change:open-cockpit-claim-entry'),
        );
        await wrapper.vm.$nextTick();
        expect(
            wrapper
                .find('[data-testid="cockpit-claim-entry-launcher"]')
                .exists(),
        ).toBe(false);

        await wrapper
            .get(
                designId === 'cats'
                    ? '[aria-label="Expand claim QR"]'
                    : '[aria-label="Expand claim entry QR"]',
            )
            .trigger('click');
        expect(
            wrapper
                .get('[data-testid="cockpit-claim-entry-qr-image"]')
                .attributes('src'),
        ).toBe(artifact.image_data_uri);
        expect(
            wrapper
                .get('[data-testid="cockpit-claim-entry-open"]')
                .attributes('href'),
        ).toBe(artifact.destination);
        window.dispatchEvent(new PopStateEvent('popstate'));
        await wrapper.vm.$nextTick();

        await wrapper.setProps({
            claimQr: 'data:image/png;base64,direct',
            issuedCode: 'ABCD',
        });
        await vi.advanceTimersByTimeAsync(350);
        await flushPromises();
        expect(
            wrapper.get('img[alt="Claim Pay Code QR"]').attributes('src'),
        ).toBe('data:image/png;base64,direct');
        await wrapper
            .get(
                designId === 'cats'
                    ? '[aria-label="Expand claim QR"]'
                    : '[data-testid="cockpit-pay-code-canvas-claim-qr-button"]',
            )
            .trigger('click');
        expect(wrapper.emitted('claimQrClick')).toHaveLength(1);
        expect(
            wrapper
                .find('[data-testid="cockpit-claim-entry-launcher"]')
                .exists(),
        ).toBe(false);
        wrapper.unmount();
    },
);

it('uses the design paper and brass trim without changing the supplied QR image', async () => {
    vi.useFakeTimers();
    vi.stubGlobal(
        'fetch',
        vi.fn().mockResolvedValue({
            ok: true,
            json: async () => ({
                image: 'data:image/png;base64,stamp',
                marker_palette: { paper: '#F8EDD4', trim: '#DDBB84' },
            }),
        }),
    );
    const wrapper = mount(Stamp, {
        props: {
            designId: 'steampunk',
            revision: 4,
            slots: { amount: '₱100' },
            claimQr: 'data:image/png;base64,qr',
            marker: 'qr',
        },
    });
    await vi.advanceTimersByTimeAsync(350);
    await flushPromises();
    const marker = wrapper.get('[data-testid="system-stamp-claim-marker"]');
    expect((marker.element as HTMLElement).style.backgroundColor).toBe(
        'rgb(248, 237, 212)',
    );
    expect((marker.element as HTMLElement).style.borderColor).toBe(
        'rgb(221, 187, 132)',
    );
    const qr = marker.get('img');
    expect(qr.attributes('src')).toBe('data:image/png;base64,qr');
    expect(qr.classes()).toContain('mix-blend-multiply');
    wrapper.unmount();
});

it('sends the default slogan as two complete sentences without a repeated Pay Code title', async () => {
    vi.useFakeTimers();
    const fetch = vi
        .fn()
        .mockResolvedValue({
            ok: true,
            json: async () => ({ image: 'data:image/png;base64,stamp' }),
        });
    vi.stubGlobal('fetch', fetch);
    const wrapper = mount(Canvas, {
        props: {
            amount: 150,
            currency: 'PHP',
            claimOutcome: 'provider_disbursement',
            voucherType: 'redeemable',
            riderStamp: {
                ...resolveRiderStampPreview({}),
                design: { id: 'steampunk', version: 4 },
            },
        },
    });
    await vi.advanceTimersByTimeAsync(350);
    await flushPromises();
    expect(JSON.parse(fetch.mock.calls[0][1].body).slots.purpose).toBe(
        'Money should adapt to people.\nNot the other way around.',
    );
    await wrapper.setProps({
        riderStamp: {
            ...resolveRiderStampPreview({
                title: 'For Amelia',
                description: 'Thank you for your help.',
            }),
            design: { id: 'steampunk', version: 4 },
        },
    });
    await vi.advanceTimersByTimeAsync(350);
    await flushPromises();
    expect(JSON.parse(fetch.mock.calls[1][1].body).slots.purpose).toBe(
        'For Amelia\nThank you for your help.',
    );
    wrapper.unmount();
});


it('refreshes the shared card when the selected provider changes and ignores stale responses', async () => {
    vi.useFakeTimers();
    let completeFirst: (value: unknown) => void = () => {};
    const fetch = vi.fn()
        .mockImplementationOnce(() => new Promise(resolve => { completeFirst = resolve; }))
        .mockResolvedValue({ ok: true, json: async () => ({ image: 'data:image/png;base64,paynamics' }) });
    vi.stubGlobal('fetch', fetch);
    const wrapper = mount(Canvas, {
        props: {
            amount: 150, currency: 'PHP', provider: 'netbank',
            claimOutcome: 'provider_disbursement', voucherType: 'redeemable',
            riderStamp: { ...resolveRiderStampPreview({}), design: { id: 'cats', version: 5 } },
        },
    });
    await vi.advanceTimersByTimeAsync(350);
    expect(JSON.parse(fetch.mock.calls[0][1].body).provider).toBe('netbank');
    await wrapper.setProps({ provider: 'paynamics' });
    expect(fetch.mock.calls[0][1].signal.aborted).toBe(true);
    await vi.advanceTimersByTimeAsync(350);
    await flushPromises();
    expect(JSON.parse(fetch.mock.calls[1][1].body).provider).toBe('paynamics');
    completeFirst({ ok: true, json: async () => ({ image: 'data:image/png;base64,netbank' }) });
    await flushPromises();
    expect(wrapper.get('[data-testid="system-stamp"] > img').attributes('src')).toBe('data:image/png;base64,paynamics');
    wrapper.unmount();
});
