import { mount } from '@vue/test-utils';
import { defineComponent, nextTick, ref } from 'vue';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import CockpitPayCodeCopyButton from '../../../resources/js/cockpit/components/CockpitPayCodeCopyButton.vue';
import { usePayCodeExplorerClock } from '../../../resources/js/cockpit/composables/usePayCodeExplorerClock';
import {
    formatAbsoluteTime,
    formatRelativeTime,
} from '../../../resources/js/cockpit/utils/dateTime';

describe('Cockpit Pay Code Explorer row controls', () => {
    beforeEach(() => {
        vi.useFakeTimers();
    });

    it('copies a Pay Code and announces compact success', async () => {
        const writeText = vi.fn().mockResolvedValue(undefined);
        Object.defineProperty(navigator, 'clipboard', {
            configurable: true,
            value: { writeText },
        });
        const wrapper = mount(CockpitPayCodeCopyButton, {
            props: { code: 'ABCD', compact: true },
        });

        await wrapper.get('button').trigger('click');

        expect(writeText).toHaveBeenCalledWith('ABCD');
        expect(wrapper.text()).toContain('Copied');
    });

    it('announces clipboard failure inline', async () => {
        Object.defineProperty(navigator, 'clipboard', {
            configurable: true,
            value: { writeText: vi.fn().mockRejectedValue(new Error('denied')) },
        });
        const wrapper = mount(CockpitPayCodeCopyButton, {
            props: { code: 'ABCD' },
        });

        await wrapper.get('button').trigger('click');

        expect(wrapper.text()).toContain('Failed');
    });

    it('formats native relative timing facts without another date library', () => {
        const now = Date.parse('2026-08-23T00:00:00Z');

        expect(formatRelativeTime('2026-08-23T00:02:00Z', now)).toBe(
            'in 2 minutes',
        );
        expect(formatRelativeTime('2026-08-22T23:00:00Z', now)).toBe(
            '1 hour ago',
        );
        expect(formatAbsoluteTime(null)).toBe('Unavailable');
    });

    it('ticks the shared Explorer clock every thirty seconds only when enabled', async () => {
        vi.setSystemTime('2026-08-23T00:00:00Z');
        const component = defineComponent({
            setup() {
                return { now: usePayCodeExplorerClock(ref(true)) };
            },
            template: '<time>{{ now }}</time>',
        });
        const wrapper = mount(component);
        const initial = wrapper.text();

        vi.advanceTimersByTime(30_000);
        await nextTick();

        expect(Number(wrapper.text())).toBe(Number(initial) + 30_000);
    });
});
