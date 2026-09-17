import { flushPromises, mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import Panel from '../../../resources/js/cockpit/components/CockpitQuickGenerateQrPanel.vue';
import type { DisplaySession } from '../../../resources/js/cockpit/pairedDisplay';

const campaign = {
    reference: 'CAMPAIGN',
    title: 'Coffee',
    description: 'Freshly brewed',
    merchant_display_name: 'Cafe',
};
const ready: DisplaySession = {
    reference: 'SESSION1',
    status: 'ready',
    campaign,
    entry_qr_data_uri: 'data:image/png;base64,ENTRY',
    public_url: '/entry/SESSION1',
    expires_at: '2026-09-17T12:00:00Z',
    attempt: null,
    links: {
        show: '/sessions/SESSION1',
        end: '/sessions/SESSION1/end',
        reset: '/sessions/SESSION1/reset',
    },
};
const payment: DisplaySession = {
    ...ready,
    status: 'awaiting_payment',
    pay_code: 'PAY1',
    attempt: {
        amount_minor: 7500,
        currency: 'PHP',
        qr_code: { mime_type: 'image/png', base64_payload: 'PAYMENT' },
    },
};
const response = (session: DisplaySession) => ({
    ok: true,
    json: async () => ({ session }),
});
const wrappers: ReturnType<typeof mount>[] = [];
function render(props = {}) {
    const wrapper = mount(Panel, {
        props: { campaigns: [campaign], ...props },
    });
    wrappers.push(wrapper);
    return wrapper;
}

describe('paired campaign display', () => {
    it('keeps a completed claim distinct from payment confirmation and stops polling', async () => {
        const fetchMock = vi.fn();
        vi.stubGlobal('fetch', fetchMock);
        const wrapper = render({ session: { ...ready, status: 'completed' } });
        expect(wrapper.text()).toContain('Claim completed');
        expect(wrapper.text()).not.toContain('Payment confirmed');
        expect(wrapper.get('[data-testid="display-reset"]').text()).toBe(
            'Next customer',
        );
        await vi.advanceTimersByTimeAsync(5000);
        expect(fetchMock).not.toHaveBeenCalled();
    });
    beforeEach(() => {
        vi.useFakeTimers();
        vi.setSystemTime(new Date('2026-09-17T10:00:00Z'));
    });
    afterEach(() => {
        wrappers.splice(0).forEach((wrapper) => wrapper.unmount());
        vi.unstubAllGlobals();
        vi.useRealTimers();
        window.history.replaceState({}, '', '/');
        document.cookie = 'XSRF-TOKEN=; Max-Age=0; path=/';
        document.querySelector('meta[name="csrf-token"]')?.remove();
    });

    it('uses the decoded XSRF cookie when the host has no CSRF meta tag', async () => {
        document.cookie = 'XSRF-TOKEN=encrypted%2Btoken%3D; path=/';
        const fetchMock = vi
            .fn()
            .mockResolvedValueOnce(response(ready))
            .mockResolvedValueOnce(response({ ...ready, status: 'ended' }));
        vi.stubGlobal('fetch', fetchMock);
        const wrapper = render({ session: ready });
        await flushPromises();
        await wrapper.get('[data-testid="display-end"]').trigger('click');
        await flushPromises();
        expect(fetchMock).toHaveBeenLastCalledWith(
            '/sessions/SESSION1/end',
            expect.objectContaining({
                method: 'POST',
                credentials: 'same-origin',
                headers: expect.objectContaining({
                    'X-XSRF-TOKEN': 'encrypted+token=',
                }),
            }),
        );
        expect(fetchMock.mock.calls.at(-1)?.[1].headers).not.toHaveProperty(
            'X-CSRF-TOKEN',
        );
        expect(wrapper.find('[data-testid="display-start"]').exists()).toBe(
            true,
        );
    });

    it('retains the CSRF meta token fallback and explains expired sessions', async () => {
        const meta = document.createElement('meta');
        meta.name = 'csrf-token';
        meta.content = 'meta-token';
        document.head.append(meta);
        const fetchMock = vi.fn().mockResolvedValue({ ok: false, status: 419 });
        vi.stubGlobal('fetch', fetchMock);
        const wrapper = render();
        await wrapper.get('form').trigger('submit');
        await flushPromises();
        expect(fetchMock).toHaveBeenCalledWith(
            '/x/cockpit/display-sessions',
            expect.objectContaining({
                headers: expect.objectContaining({
                    'X-CSRF-TOKEN': 'meta-token',
                }),
            }),
        );
        expect(wrapper.get('[role="alert"]').text()).toContain(
            'Reload this page',
        );
    });

    it('starts a session, replaces the campaign stamp with QR Ph, confirms, and starts the next customer', async () => {
        const fetchMock = vi
            .fn()
            .mockResolvedValueOnce(response(ready))
            .mockResolvedValueOnce(response({ ...ready, status: 'claimed' }))
            .mockResolvedValueOnce(response(payment))
            .mockResolvedValueOnce(response({ ...payment, status: 'paid' }))
            .mockResolvedValueOnce(
                response({ ...ready, reference: 'SESSION2' }),
            );
        vi.stubGlobal('fetch', fetchMock);
        const wrapper = render();
        await wrapper.get('form').trigger('submit');
        await flushPromises();
        expect(fetchMock).toHaveBeenCalledWith(
            '/x/cockpit/display-sessions',
            expect.objectContaining({
                method: 'POST',
                body: JSON.stringify({ campaign_reference: 'CAMPAIGN' }),
            }),
        );
        expect(
            wrapper
                .get('[data-testid="campaign-endpoint-stamp-qr"]')
                .attributes('src'),
        ).toContain('ENTRY');
        expect(window.location.search).toContain('display_session=SESSION1');
        await vi.advanceTimersByTimeAsync(5000);
        await flushPromises();
        expect(wrapper.text()).toContain('Customer connected');
        expect(wrapper.find('img').exists()).toBe(false);
        await vi.advanceTimersByTimeAsync(5000);
        await flushPromises();
        expect(
            wrapper.get('[data-testid="display-payment-qr"]').attributes('src'),
        ).toContain('PAYMENT');
        expect(wrapper.text()).toContain('₱75.00');
        expect(
            wrapper.find('[data-testid="campaign-endpoint-stamp-qr"]').exists(),
        ).toBe(false);
        await vi.advanceTimersByTimeAsync(5000);
        await flushPromises();
        expect(wrapper.text()).toContain('Payment confirmed');
        expect(wrapper.find('img').exists()).toBe(false);
        await vi.advanceTimersByTimeAsync(10000);
        expect(fetchMock).toHaveBeenCalledTimes(4);
        await wrapper.get('[data-testid="display-reset"]').trigger('click');
        await flushPromises();
        expect(fetchMock).toHaveBeenLastCalledWith(
            '/sessions/SESSION1/reset',
            expect.objectContaining({ method: 'POST' }),
        );
        expect(window.location.search).toContain('SESSION2');
    });

    it('hides stale QR after a poll failure and recovers with retry', async () => {
        const fetchMock = vi
            .fn()
            .mockResolvedValueOnce(response(payment))
            .mockRejectedValueOnce(new Error('Offline'))
            .mockResolvedValueOnce(response(payment));
        vi.stubGlobal('fetch', fetchMock);
        const wrapper = render({ session: payment });
        await flushPromises();
        await vi.advanceTimersByTimeAsync(5000);
        await flushPromises();
        expect(wrapper.get('[role="alert"]').text()).toContain('Offline');
        expect(wrapper.find('img').exists()).toBe(false);
        await wrapper.get('[role="alert"] button').trigger('click');
        await flushPromises();
        expect(
            wrapper.find('[data-testid="display-payment-qr"]').exists(),
        ).toBe(true);
    });

    it('ends and expires a session without further polling', async () => {
        const fetchMock = vi
            .fn()
            .mockResolvedValueOnce(response(ready))
            .mockResolvedValueOnce(response({ ...ready, status: 'ended' }));
        vi.stubGlobal('fetch', fetchMock);
        const wrapper = render({ session: ready });
        await flushPromises();
        await wrapper.get('[data-testid="display-end"]').trigger('click');
        await flushPromises();
        expect(wrapper.find('img').exists()).toBe(false);
        expect(wrapper.find('[data-testid="display-start"]').exists()).toBe(
            true,
        );
        await vi.advanceTimersByTimeAsync(10000);
        expect(fetchMock).toHaveBeenCalledTimes(2);
        const expired = render({
            session: { ...payment, expires_at: '2026-09-17T09:00:00Z' },
        });
        expect(expired.text()).toContain('Display expired');
        expect(expired.find('img').exists()).toBe(false);
        expect(fetchMock).toHaveBeenCalledTimes(2);
    });

    it('does not overlap polls and cleans up after unmount', async () => {
        const fetchMock = vi.fn().mockReturnValue(new Promise(() => {}));
        vi.stubGlobal('fetch', fetchMock);
        const wrapper = render({ session: ready });
        await vi.advanceTimersByTimeAsync(15000);
        expect(fetchMock).toHaveBeenCalledTimes(1);
        const signal = fetchMock.mock.calls[0][1].signal;
        wrapper.unmount();
        expect(signal.aborted).toBe(true);
        await vi.advanceTimersByTimeAsync(10000);
        expect(fetchMock).toHaveBeenCalledTimes(1);
    });

    it('does not let a delayed poll overwrite a reset session', async () => {
        let resolvePoll!: (value: unknown) => void;
        const fetchMock = vi
            .fn()
            .mockReturnValueOnce(
                new Promise((resolve) => {
                    resolvePoll = resolve;
                }),
            )
            .mockResolvedValueOnce(response({ ...ready, reference: 'NEW' }));
        vi.stubGlobal('fetch', fetchMock);
        const wrapper = render({ session: ready });
        await wrapper.get('[data-testid="display-reset"]').trigger('click');
        await flushPromises();
        resolvePoll(response(payment));
        await flushPromises();
        expect(
            wrapper.find('[data-testid="display-payment-qr"]').exists(),
        ).toBe(false);
        expect(window.location.search).toContain('NEW');
    });

    it('offers full screen and the three workspace choices', async () => {
        const wrapper = render();
        expect(
            wrapper
                .get('[data-testid="cockpit-quick-generate-surface-qr"]')
                .attributes('aria-pressed'),
        ).toBe('true');
        await wrapper
            .get('[data-testid="cockpit-quick-generate-surface-pos"]')
            .trigger('click');
        expect(wrapper.emitted('update:issuanceSurface')).toEqual([['pos']]);
        const button = wrapper
            .findAll('button')
            .find((button) => button.text() === 'Full screen')!;
        await button.trigger('click');
        expect(wrapper.classes()).toContain('fixed');
        document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }));
        await wrapper.vm.$nextTick();
        expect(wrapper.classes()).not.toContain('fixed');
    });

    it('blocks unavailable campaigns and distinguishes review from successful payment', async () => {
        const fetchMock = vi.fn();
        vi.stubGlobal('fetch', fetchMock);
        const unavailable = render({
            campaigns: [
                {
                    ...campaign,
                    available: false,
                    availability: 'Starts limit reached',
                },
            ],
        });
        expect(
            unavailable
                .get('[data-testid="display-start"]')
                .attributes('disabled'),
        ).toBeDefined();
        await unavailable.get('form').trigger('submit');
        expect(fetchMock).not.toHaveBeenCalled();
        expect(unavailable.text()).toContain('Starts limit reached');
        fetchMock.mockResolvedValue(
            response({ ...payment, status: 'review', attempt: null }),
        );
        const review = render({
            session: { ...payment, status: 'review', attempt: null },
        });
        await flushPromises();
        expect(review.text()).toContain('Payment under review');
        expect(review.text()).not.toContain('Payment confirmed');
        expect(review.find('img').exists()).toBe(false);
    });
});
