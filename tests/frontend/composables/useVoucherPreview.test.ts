import { effectScope, nextTick } from 'vue';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { useVoucherPreview } from '../../../resources/js/composables/useVoucherPreview';

describe('useVoucherPreview', () => {
    beforeEach(() => {
        vi.useFakeTimers();
    });

    afterEach(() => {
        vi.useRealTimers();
        vi.unstubAllGlobals();
    });

    it('inspects pay codes through the x-ray endpoint instead of the legacy lifecycle endpoint', async () => {
        const fetchMock = vi.fn().mockResolvedValue({
            ok: true,
            json: async () => ({
                success: true,
                data: {
                    xray: {
                        visible: true,
                        status: 'claimable',
                        disclosures: [{ key: 'status', value: 'claimable' }],
                        requirements: [{ key: 'mobile' }],
                        redactions: [],
                    },
                },
            }),
        });

        vi.stubGlobal('fetch', fetchMock);

        const scope = effectScope();
        const preview = scope.run(() => useVoucherPreview({ debounceMs: 1 }))!;

        preview.code.value = 's3xr';
        await nextTick();
        await vi.runAllTimersAsync();
        await nextTick();

        expect(fetchMock).toHaveBeenCalledTimes(1);
        expect(fetchMock).toHaveBeenCalledWith(
            '/api/x/v1/pay-codes/x-ray',
            expect.objectContaining({
                method: 'POST',
                body: JSON.stringify({
                    code: 'S3XR',
                    channel: 'claim',
                }),
            }),
        );
        expect(fetchMock.mock.calls[0][0]).not.toContain('/vouchers/code/');
        expect(preview.error.value).toBeNull();
        expect(preview.voucherData.value?.code).toBe('S3XR');
        expect(preview.voucherData.value?.status).toBe('active');

        scope.stop();
    });

    it('maps paid x-ray status to a non-active redeemed preview state', async () => {
        const fetchMock = vi.fn().mockResolvedValue({
            ok: true,
            json: async () => ({
                success: true,
                data: {
                    xray: {
                        visible: true,
                        status: 'paid',
                        disclosures: [],
                        requirements: [],
                        redactions: [],
                    },
                },
            }),
        });

        vi.stubGlobal('fetch', fetchMock);

        const scope = effectScope();
        const preview = scope.run(() => useVoucherPreview({ debounceMs: 1 }))!;

        preview.code.value = 'S3XR';
        await nextTick();
        await vi.runAllTimersAsync();
        await nextTick();

        expect(preview.voucherData.value?.status).toBe('redeemed');
        expect(preview.error.value).toBeNull();

        scope.stop();
    });
});
