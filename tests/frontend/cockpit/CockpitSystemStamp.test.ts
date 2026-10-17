import { mount, flushPromises } from '@vue/test-utils';
import { afterEach, describe, expect, it, vi } from 'vitest';
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
            json: async () => ({ image: 'data:image/png;base64,abc' }),
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
