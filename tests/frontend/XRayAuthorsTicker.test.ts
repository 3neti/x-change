import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import XRayAuthorsTicker from '../../resources/js/components/x-change/XRayAuthorsTicker.vue';

describe('XRayAuthorsTicker', () => {
    it('holds the first author for five seconds and cycles the others from right to left', async () => {
        vi.useFakeTimers();

        try {
            const wrapper = mount(XRayAuthorsTicker, {
                props: { authors: ['Ada', 'Anaïs', 'Lester'], active: true },
            });
            const visibleAuthor = () => wrapper.findAll('[data-testid="xray-current-author"]').at(-1)?.text();

            expect(visibleAuthor()).toBe('Ada');
            expect(wrapper.get('[data-testid="xray-authors"]').text()).toContain('Ada, Anaïs, Lester');

            await vi.advanceTimersByTimeAsync(4999);
            expect(visibleAuthor()).toBe('Ada');

            await vi.advanceTimersByTimeAsync(1);
            expect(visibleAuthor()).toBe('Anaïs');

            await vi.advanceTimersByTimeAsync(2500);
            expect(visibleAuthor()).toBe('Lester');

            await vi.advanceTimersByTimeAsync(2500);
            expect(visibleAuthor()).toBe('Ada');

            await vi.advanceTimersByTimeAsync(4999);
            expect(visibleAuthor()).toBe('Ada');

            await wrapper.setProps({ active: false });
            await vi.advanceTimersByTimeAsync(10000);
            expect(visibleAuthor()).toBe('Ada');

            wrapper.unmount();
        } finally {
            vi.useRealTimers();
        }
    });

    it('pauses while System Info is hidden and shows all names without motion when requested', async () => {
        vi.useFakeTimers();
        vi.stubGlobal('matchMedia', () => ({
            matches: true,
            addEventListener: vi.fn(),
            removeEventListener: vi.fn(),
        }));

        try {
            const wrapper = mount(XRayAuthorsTicker, {
                props: { authors: ['Ada', 'Anaïs'], active: false },
            });

            await wrapper.setProps({ active: true });
            await vi.advanceTimersByTimeAsync(10000);

            expect(wrapper.get('[data-testid="xray-authors"]').text()).toContain('Ada · Anaïs');
            expect(wrapper.find('[data-testid="xray-current-author"]').exists()).toBe(false);

            wrapper.unmount();
        } finally {
            vi.unstubAllGlobals();
            vi.useRealTimers();
        }
    });
});
