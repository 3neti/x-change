import { mount } from '@vue/test-utils';
import { defineComponent, ref, nextTick } from 'vue';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { router } from '@inertiajs/vue3';
import { useCompletionStatusPoll } from '../../resources/js/pages/x-change/claim/useCompletionStatusPoll';

vi.mock('@inertiajs/vue3', () => ({ router: { reload: vi.fn() } }));

describe('bounded completion presentation polling', () => {
    let wrapper: ReturnType<typeof mount>;
    const processing = ref(true);
    let poll: ReturnType<typeof useCompletionStatusPoll>;
    const options = () => vi.mocked(router.reload).mock.calls.at(-1)![0]!;
    beforeEach(() => {
        vi.useFakeTimers();
        vi.clearAllMocks();
        processing.value = true;
        vi.spyOn(document, 'visibilityState', 'get').mockReturnValue('visible');
        wrapper = mount(defineComponent({
            setup() { poll = useCompletionStatusPoll(processing); return () => null; },
        }));
    });
    afterEach(() => { wrapper.unmount(); vi.useRealTimers(); vi.restoreAllMocks(); });

    it('only refreshes presentation and action, with no overlapping requests', async () => {
        await vi.advanceTimersByTimeAsync(5000);
        expect(options().only).toEqual(['success_presentation', 'success_action']);
        await vi.advanceTimersByTimeAsync(20000);
        poll.check();
        expect(router.reload).toHaveBeenCalledTimes(1);
        options().onFinish?.({} as never);
        await vi.advanceTimersByTimeAsync(5000);
        expect(router.reload).toHaveBeenCalledTimes(2);
    });

    it.each(['ready', 'needs_attention', 'payment_unverified', 'details_required'])('stops when processing becomes %s', async () => {
        await vi.advanceTimersByTimeAsync(5000);
        const cancel = vi.fn();
        options().onCancelToken?.({ cancel });
        processing.value = false;
        await nextTick();
        expect(cancel).toHaveBeenCalledOnce();
        options().onFinish?.({} as never);
        await vi.advanceTimersByTimeAsync(20000);
        expect(router.reload).toHaveBeenCalledTimes(1);
    });

    it('does not poll a different or terminal journey', async () => {
        processing.value = false;
        await nextTick();
        await vi.advanceTimersByTimeAsync(120000);
        expect(router.reload).not.toHaveBeenCalled();
    });

    it('pauses requests while hidden without extending the two minute bound', async () => {
        vi.spyOn(document, 'visibilityState', 'get').mockReturnValue('hidden');
        await vi.advanceTimersByTimeAsync(120000);
        expect(router.reload).not.toHaveBeenCalled();
        expect(poll.stopped.value).toBe(true);
        vi.spyOn(document, 'visibilityState', 'get').mockReturnValue('visible');
        poll.check();
        expect(router.reload).toHaveBeenCalledOnce();
        options().onFinish?.({} as never);
        await vi.advanceTimersByTimeAsync(10000);
        expect(router.reload).toHaveBeenCalledOnce();
    });

    it.each(['onHttpException', 'onNetworkError', 'onError'] as const)('stops on %s and allows manual retry', async (callback) => {
        await vi.advanceTimersByTimeAsync(5000);
        (options()[callback] as Function)({});
        options().onFinish?.({} as never);
        await vi.advanceTimersByTimeAsync(10000);
        expect(poll.stopped.value).toBe(true);
        expect(router.reload).toHaveBeenCalledOnce();
        poll.check();
        expect(router.reload).toHaveBeenCalledTimes(2);
    });

    it('cancels outstanding work on timeout and unmount', async () => {
        await vi.advanceTimersByTimeAsync(5000);
        const cancel = vi.fn();
        options().onCancelToken?.({ cancel });
        await vi.advanceTimersByTimeAsync(115000);
        expect(cancel).toHaveBeenCalledOnce();
        expect(poll.stopped.value).toBe(true);
        options().onFinish?.({} as never);
        wrapper.unmount();
        await vi.advanceTimersByTimeAsync(10000);
        expect(router.reload).toHaveBeenCalledOnce();
    });
});
