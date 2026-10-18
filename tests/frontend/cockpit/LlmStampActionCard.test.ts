import { flushPromises, mount, type VueWrapper } from '@vue/test-utils';
import { afterEach, describe, expect, it, vi } from 'vitest';
import Prompt from '../../../resources/js/cockpit/components/CockpitQuickGeneratePromptComposer.vue';
import Canvas from '../../../resources/js/cockpit/components/CockpitPayCodeCanvas.vue';
import { resolveRiderStampPreview } from '../../../resources/js/cockpit/riderStampPreview';
import type { PayCodeStampPreviewState } from '../../../resources/js/cockpit/payCodeStampPreview';

const wrappers: VueWrapper[] = [];
afterEach(() => {
    wrappers.splice(0).forEach((wrapper) => wrapper.unmount());
    vi.useRealTimers();
    vi.unstubAllGlobals();
});
const preview: PayCodeStampPreviewState = {
    amount: '500',
    currency: 'PHP',
    provider: 'netbank',
    recipient: 'Anyone',
    purpose: 'School allowance',
    claimOutcome: 'provider_disbursement',
    voucherType: 'redeemable',
    expiry: '3 Days',
    instructionKeys: ['input.signature'],
    hasRiderDesign: false,
    riderDesignSource: 'default',
    riderDesignDocument: '',
    riderStamp: resolveRiderStampPreview({
        designId: 'cats',
        designVersion: 7,
    }),
    stampPresentation: null,
    claimEntryArtifact: null,
    quantity: '1',
};
const ready = (amount = 500) => ({
    status: 'ready',
    instructions: { cash: { amount, currency: 'PHP' } },
    pricing: { account_debit: amount + 15 },
});
function mountPrompt(): VueWrapper {
    const wrapper = mount(Prompt, {
        props: {
            endpoint: '/interpret',
            reviewOnly: true,
            stampPreview: preview,
        },
        global: { stubs: { CockpitPayCodeCanvas: true } },
    });
    wrappers.push(wrapper);
    return wrapper;
}
async function typePrompt(wrapper: VueWrapper, value: string): Promise<void> {
    await wrapper
        .get('[data-testid="cockpit-quick-generate-prompt-input"]')
        .setValue(value);
    await vi.advanceTimersByTimeAsync(450);
    await flushPromises();
}

describe('LLM stamp review action', () => {
    it('projects a valid draft immediately and reviews repeatedly without activating issuance', async () => {
        vi.useFakeTimers();
        vi.stubGlobal(
            'fetch',
            vi.fn().mockResolvedValue({ ok: true, json: async () => ready() }),
        );
        const wrapper = mountPrompt();
        expect(
            wrapper.get('[data-testid="llm-stamp-placeholder"]').classes(),
        ).toContain('aspect-[1200/630]');
        expect(
            wrapper
                .get('[data-testid="cockpit-quick-generate-prompt-go"]')
                .attributes('disabled'),
        ).toBeDefined();
        await typePrompt(wrapper, '500 pesos');
        expect(wrapper.emitted('interpreted')?.[0]?.[0]).toMatchObject({
            cash: { amount: 500 },
        });
        expect(wrapper.findComponent(Canvas).props()).toMatchObject({
            amount: '500',
            expiry: '3 Days',
            previewOnly: true,
        });
        const action = wrapper.get(
            '[data-testid="cockpit-quick-generate-prompt-go"]',
        );
        expect(action.text()).toContain('Review Order');
        expect(wrapper.text()).toContain('₱515.00');
        expect(action.attributes('aria-label')).toBe('Review Order');
        expect(action.element.parentElement).toBe(wrapper.get('[data-testid="cockpit-quick-generate-prompt-input"]').element.parentElement?.parentElement?.lastElementChild);
        await action.trigger('click');
        await action.trigger('click');
        expect(wrapper.emitted('show-stamp')).toHaveLength(2);
        expect(wrapper.emitted('activate')).toBeUndefined();
        await wrapper
            .get('[data-testid="cockpit-quick-generate-prompt-input"]')
            .trigger('keydown.enter');
        expect(wrapper.emitted('show-stamp')).toHaveLength(3);
        const estimate = wrapper.get('[data-testid="llm-estimated-total"]');
        expect(estimate.text()).toBe('Estimated total ₱515.00');
        expect(wrapper.text()).not.toContain('Click the stamp to review');
        await estimate.trigger('click');
        expect(wrapper.emitted('show-cost')).toHaveLength(1);
        expect(wrapper.emitted('show-stamp')).toHaveLength(3);
    });

    it('invalidates review immediately and ignores an older interpretation after the prompt changes', async () => {
        vi.useFakeTimers();
        let finishFirst!: (response: unknown) => void;
        const fetch = vi
            .fn()
            .mockImplementationOnce(
                () =>
                    new Promise((resolve) => {
                        finishFirst = resolve;
                    }),
            )
            .mockResolvedValue({ ok: true, json: async () => ready(700) });
        vi.stubGlobal('fetch', fetch);
        const wrapper = mountPrompt();
        await typePrompt(wrapper, '500 pesos');
        await wrapper
            .get('[data-testid="cockpit-quick-generate-prompt-input"]')
            .setValue('700 pesos');
        expect(
            wrapper
                .get('[data-testid="cockpit-quick-generate-prompt-go"]')
                .attributes('disabled'),
        ).toBeDefined();
        expect(fetch.mock.calls[0][1].signal.aborted).toBe(true);
        await vi.advanceTimersByTimeAsync(450);
        await flushPromises();
        finishFirst({ ok: true, json: async () => ready(500) });
        await flushPromises();
        expect(wrapper.emitted('interpreted')).toEqual([
            [ready(700).instructions],
        ]);
        expect(
            wrapper.text(),
        ).toContain('₱715.00');
        await wrapper
            .get('[data-testid="cockpit-quick-generate-prompt-input"]')
            .setValue('');
        expect(
            wrapper
                .get('[data-testid="cockpit-quick-generate-prompt-go"]')
                .attributes('disabled'),
        ).toBeDefined();
        expect(wrapper.get('[aria-live="polite"]').text()).toContain(
            'Previous preview shown',
        );
    });

    it.each(['clarification', 'error'])(
        'keeps the prior preview stale and review disabled for %s',
        async (failure) => {
            vi.useFakeTimers();
            vi.stubGlobal(
                'fetch',
                vi
                    .fn()
                    .mockResolvedValueOnce({
                        ok: true,
                        json: async () => ready(),
                    })
                    .mockResolvedValue({
                        ok: failure !== 'error',
                        json: async () =>
                            failure === 'error'
                                ? { message: 'Unavailable' }
                                : {
                                      status: 'needs_clarification',
                                      instructions: null,
                                      clarifications: ['Specify an amount'],
                                  },
                    }),
            );
            const wrapper = mountPrompt();
            await typePrompt(wrapper, '500 pesos');
            await typePrompt(wrapper, 'something else');
            expect(wrapper.findComponent(Canvas).exists()).toBe(true);
            expect(
                wrapper
                    .get('[data-testid="cockpit-quick-generate-prompt-go"]')
                    .attributes('disabled'),
            ).toBeDefined();
            expect(wrapper.text()).toContain('Previous preview shown');
            expect(wrapper.emitted('interpreted')).toHaveLength(1);
        },
    );

    it('keeps Order review available with a full-size retry when the thumbnail fails', async () => {
        vi.useFakeTimers();
        vi.stubGlobal(
            'fetch',
            vi.fn().mockResolvedValue({ ok: true, json: async () => ready() }),
        );
        const wrapper = mountPrompt();
        await typePrompt(wrapper, '500 pesos');
        wrapper.findComponent(Canvas).vm.$emit('previewError', true);
        await wrapper.vm.$nextTick();
        const button = wrapper.get(
            '[data-testid="cockpit-quick-generate-prompt-go"]',
        );
        expect(wrapper.text()).toContain('retry in Review Order');
        expect(button.attributes('disabled')).toBeUndefined();
        await button.trigger('click');
        expect(wrapper.emitted('show-stamp')).toHaveLength(1);
    });
});

it('renders a stamp-only canvas without tabs or QR actions at the original aspect ratio', async () => {
    vi.useFakeTimers();
    vi.stubGlobal(
        'fetch',
        vi
            .fn()
            .mockResolvedValue({
                ok: true,
                json: async () => ({
                    image: 'data:image/png;base64,test',
                    marker_bounds: { x: 956, y: 386, width: 190, height: 190 },
                }),
            }),
    );
    const wrapper = mount(Canvas, {
        props: {
            ...preview,
            previewOnly: true,
            claimEntryArtifact: {
                kind: 'claim_entry',
                destination: '/x/claim',
                image_data_uri: 'data:image/png;base64,qr',
                title: 'Enter Pay Code',
                description: 'Enter your code',
                identifier: null,
            },
        },
    });
    wrappers.push(wrapper);
    await vi.advanceTimersByTimeAsync(350);
    await flushPromises();
    expect(wrapper.classes()).toContain('aspect-[1200/630]');
    expect(wrapper.find('[role="tablist"]').exists()).toBe(false);
    expect(wrapper.find('[aria-label="Expand claim QR"]').exists()).toBe(false);
    expect(
        wrapper.find('[data-testid="cockpit-claim-entry-launcher"]').exists(),
    ).toBe(false);
    expect(wrapper.find('img').exists()).toBe(true);
});

it('focuses the LLM input when its workspace mounts without scrolling the page', () => {
    const focus = vi.spyOn(HTMLInputElement.prototype, 'focus');
    mountPrompt();
    expect(focus).toHaveBeenCalledWith({ preventScroll: true });
    focus.mockRestore();
});
