import { mount } from '@vue/test-utils';
import { computed, defineComponent, nextTick, ref } from 'vue';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { usePayCodeCostEstimate } from '../../../resources/js/composables/usePayCodeCostEstimate';

const EstimateHarness = defineComponent({
    setup() {
        const amount = ref(0);
        const requestPayload = computed(() => ({
            cash: {
                amount: amount.value,
                currency: 'PHP',
            },
        }));
        const canEstimate = computed(() => amount.value > 0);
        const { estimate, estimating, estimateError } = usePayCodeCostEstimate(
            requestPayload,
            canEstimate,
        );

        return {
            amount,
            estimate,
            estimating,
            estimateError,
        };
    },
    template: `
        <div>
            <button data-testid="amount" @click="amount = amount + 1">Amount</button>
            <span data-testid="total">{{ estimate?.total ?? 'none' }}</span>
            <span data-testid="loading">{{ estimating }}</span>
            <span data-testid="error">{{ estimateError ?? '' }}</span>
        </div>
    `,
});

describe('usePayCodeCostEstimate', () => {
    beforeEach(() => {
        vi.useFakeTimers();
    });

    afterEach(() => {
        vi.useRealTimers();
        vi.unstubAllGlobals();
    });

    it('debounces a typed Wayfinder request until pricing can be estimated', async () => {
        const fetchMock = vi.fn().mockResolvedValue({
            ok: true,
            json: async () => ({
                success: true,
                data: {
                    currency: 'PHP',
                    total: 17,
                },
            }),
        });
        vi.stubGlobal('fetch', fetchMock);
        const wrapper = mount(EstimateHarness);

        expect(fetchMock).not.toHaveBeenCalled();

        await wrapper.find('[data-testid="amount"]').trigger('click');
        await vi.advanceTimersByTimeAsync(499);
        expect(fetchMock).not.toHaveBeenCalled();

        await vi.advanceTimersByTimeAsync(1);
        await nextTick();

        expect(fetchMock).toHaveBeenCalledTimes(1);
        expect(fetchMock).toHaveBeenCalledWith(
            '/api/x/v1/pay-codes/estimate',
            expect.objectContaining({
                method: 'POST',
                credentials: 'same-origin',
            }),
        );
        expect(
            JSON.parse(String(fetchMock.mock.calls[0]?.[1]?.body)),
        ).toMatchObject({
            cash: {
                amount: 1,
                currency: 'PHP',
            },
        });
        expect(wrapper.get('[data-testid="total"]').text()).toBe('17');
    });

    it('keeps the last good estimate when a later refresh fails', async () => {
        const fetchMock = vi
            .fn()
            .mockResolvedValueOnce({
                ok: true,
                json: async () => ({
                    success: true,
                    data: {
                        currency: 'PHP',
                        total: 12,
                    },
                }),
            })
            .mockResolvedValueOnce({
                ok: false,
                status: 422,
                json: async () => ({
                    success: false,
                    message: 'Pricing is temporarily unavailable.',
                }),
            });
        vi.stubGlobal('fetch', fetchMock);
        const wrapper = mount(EstimateHarness);

        await wrapper.find('[data-testid="amount"]').trigger('click');
        await vi.advanceTimersByTimeAsync(500);
        await nextTick();
        expect(wrapper.get('[data-testid="total"]').text()).toBe('12');

        await wrapper.find('[data-testid="amount"]').trigger('click');
        await vi.advanceTimersByTimeAsync(500);
        await nextTick();

        expect(wrapper.get('[data-testid="total"]').text()).toBe('12');
        expect(wrapper.get('[data-testid="error"]').text()).toBe(
            'Pricing is temporarily unavailable.',
        );
    });
});
