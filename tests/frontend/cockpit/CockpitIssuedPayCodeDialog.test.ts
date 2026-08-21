import { mount } from '@vue/test-utils';
import { afterEach, describe, expect, it, vi } from 'vitest';
import CockpitIssuedPayCodeDialog from '../../../resources/js/cockpit/components/CockpitIssuedPayCodeDialog.vue';
import CockpitQuickGenerateSubmitPanel from '../../../resources/js/cockpit/components/CockpitQuickGenerateSubmitPanel.vue';
import { cockpitQuickGenerateTemplates } from '../../../resources/js/cockpit/quickGenerateDefaults';

vi.mock('@inertiajs/vue3', () => ({
    Link: {
        props: ['href'],
        template: '<a :href="href?.url ?? href"><slot /></a>',
    },
    router: {
        reload: vi.fn(),
    },
}));

afterEach(() => {
    vi.unstubAllGlobals();
});

describe('issued Pay Code dialog', () => {
    it('renders the finalized front and back canvas with safe share fallbacks', async () => {
        const clipboardWrite = vi.fn().mockResolvedValue(undefined);

        vi.stubGlobal('navigator', {
            clipboard: {
                writeText: clipboardWrite,
            },
        });

        const wrapper = mount(CockpitIssuedPayCodeDialog, {
            props: {
                open: true,
                code: 'PAY-READY-7',
                amount: '125.50',
                currency: 'PHP',
                recipient: '09173011987',
                purpose: 'Family support',
                claimOutcome: 'provider_disbursement',
                voucherType: 'redeemable',
                expiry: '1 day',
                instructionKeys: ['validation.mobile', 'validation.otp'],
                costEstimate: {
                    currency: 'PHP',
                    charges: [
                        {
                            label: 'Pay Code Generation',
                            type: 'generation',
                            price: 12,
                        },
                        {
                            label: 'Selfie Verification',
                            type: 'selfie',
                            price: 5,
                        },
                    ],
                    total: 17,
                },
                claimUrl: 'https://example.test/x/claim/PAY-READY-7/experience',
                detailUrl: '/x/cockpit/pay-codes/PAY-READY-7',
            },
            global: {
                stubs: {
                    Teleport: true,
                },
            },
        });

        expect(wrapper.get('[role="dialog"]').attributes('aria-modal')).toBe(
            'true',
        );
        expect(wrapper.text()).toContain('Pay Code PAY-READY-7 Is Ready');
        expect(wrapper.text()).toContain('Issued Pay Code');
        expect(wrapper.text()).toContain('Final design ready to share.');
        expect(
            wrapper
                .get('[data-testid="cockpit-issued-pay-code-detail"]')
                .attributes('href'),
        ).toBe('/x/cockpit/pay-codes/PAY-READY-7');

        const whatsapp = wrapper
            .get('[data-testid="cockpit-pay-code-share-whatsapp"]')
            .attributes('href');
        const sms = wrapper
            .get('[data-testid="cockpit-pay-code-share-sms"]')
            .attributes('href');
        const email = wrapper
            .get('[data-testid="cockpit-pay-code-share-email"]')
            .attributes('href');

        expect(decodeURIComponent(whatsapp)).toContain(
            'Claim Pay Code || PAY-READY-7 ||',
        );
        expect(decodeURIComponent(whatsapp)).toContain(
            'https://example.test/x/claim/PAY-READY-7/experience',
        );
        expect(wrapper.text()).toContain('Claim this Pay Code');
        expect(wrapper.text()).toContain('PHP 125.50');
        expect(sms).toContain('sms:?body=');
        expect(email).toContain('mailto:?subject=');

        await wrapper
            .get('[data-testid="cockpit-pay-code-canvas-back-button"]')
            .trigger('click');

        expect(
            wrapper
                .get('[data-testid="cockpit-pay-code-canvas-back"]')
                .isVisible(),
        ).toBe(true);
        expect(wrapper.text()).toContain('Issue Cost');
        expect(wrapper.text()).toContain('Pay Code Generation');
        expect(wrapper.text()).toContain('₱12.00');
        expect(wrapper.text()).toContain('Selfie Verification');
        expect(wrapper.text()).toContain('5.00');
        expect(wrapper.text()).toContain('Instruction Subtotal');
        expect(wrapper.text()).toContain('Pay Code Value');
        expect(wrapper.text()).toContain('Total Estimated Cost');
        expect(wrapper.text()).toContain('₱17.00');
        expect(wrapper.text()).toContain('₱125.50');
        expect(wrapper.text()).toContain('₱142.50');
        expect(wrapper.text().match(/₱/g) ?? []).toHaveLength(4);

        await wrapper
            .get('[data-testid="cockpit-pay-code-share-copy"]')
            .trigger('click');

        expect(clipboardWrite).toHaveBeenCalledWith(
            'https://example.test/x/claim/PAY-READY-7/experience',
        );
        expect(wrapper.text()).toContain('Copied');
    });

    it('uses native device sharing when the browser supports it', async () => {
        const nativeShare = vi.fn().mockResolvedValue(undefined);

        vi.stubGlobal('navigator', {
            share: nativeShare,
        });

        const wrapper = mount(CockpitIssuedPayCodeDialog, {
            props: {
                open: true,
                code: 'FUND-NATIVE-1',
                amount: 50,
                currency: 'PHP',
                claimOutcome: 'account_funding',
                voucherType: 'redeemable',
                claimUrl:
                    'https://example.test/x/claim/FUND-NATIVE-1/experience',
            },
            global: {
                stubs: {
                    Teleport: true,
                },
            },
        });

        await wrapper
            .get('[data-testid="cockpit-pay-code-share-native"]')
            .trigger('click');

        expect(nativeShare).toHaveBeenCalledWith({
            title: 'Pay Code FUND-NATIVE-1',
            text: expect.stringContaining('Claim Pay Code || FUND-NATIVE-1 ||'),
            url: 'https://example.test/x/claim/FUND-NATIVE-1/experience',
        });
        expect(wrapper.text()).toContain('Add this Pay Code to your Account');
    });

    it('reuses the compact share card without duplicating the QR already shown on the Stamp canvas', () => {
        const wrapper = mount(CockpitIssuedPayCodeDialog, {
            props: {
                open: true,
                code: 'PAY-COMPACT-1',
                amount: 50,
                currency: 'PHP',
                claimOutcome: 'provider_disbursement',
                voucherType: 'redeemable',
                claimUrl:
                    'https://example.test/x/claim/PAY-COMPACT-1/experience',
                claimQr: 'data:image/png;base64,FAKE-QR',
            },
            global: {
                stubs: {
                    Teleport: true,
                },
            },
        });

        const shareCard = wrapper.get(
            '[data-testid="cockpit-pay-code-share-card"]',
        );

        expect(shareCard.attributes('data-variant')).toBe('compact');
        expect(
            shareCard
                .find('[data-testid="cockpit-pay-code-share-qr"]')
                .exists(),
        ).toBe(false);

        const facebook = shareCard
            .get('[data-testid="cockpit-pay-code-share-facebook"]')
            .attributes('href');

        expect(decodeURIComponent(facebook)).toContain(
            'https://example.test/x/claim/PAY-COMPACT-1/experience',
        );

        const download = shareCard.get(
            '[data-testid="cockpit-pay-code-share-download"]',
        );

        expect(download.attributes('href')).toBe(
            'data:image/png;base64,FAKE-QR',
        );
        expect(download.attributes('download')).toBe(
            'pay-code-pay-compact-1.png',
        );
    });

    it('turns the finalized Stamp into a large QR and restores it without closing the dialog', async () => {
        const wrapper = mount(CockpitIssuedPayCodeDialog, {
            props: {
                open: true,
                code: 'PAY-QR-1',
                amount: 50,
                currency: 'PHP',
                claimOutcome: 'provider_disbursement',
                voucherType: 'redeemable',
                claimQr: 'data:image/png;base64,LARGE-QR',
                shareCardUrl:
                    'https://example.test/x/claim/PAY-QR-1/share-card.png',
            },
            global: {
                stubs: {
                    Teleport: true,
                },
            },
        });

        const stampButton = wrapper.get(
            '[data-testid="cockpit-issued-pay-code-artifact-qr-button"]',
        );

        expect(stampButton.element.tagName).toBe('BUTTON');
        expect(stampButton.attributes('aria-pressed')).toBe('false');

        await stampButton.trigger('click');

        expect(
            wrapper
                .get(
                    '[data-testid="cockpit-issued-pay-code-expanded-qr-image"]',
                )
                .attributes('src'),
        ).toBe('data:image/png;base64,LARGE-QR');
        expect(
            wrapper
                .get(
                    '[data-testid="cockpit-issued-pay-code-expanded-qr-button"]',
                )
                .attributes('aria-pressed'),
        ).toBe('true');
        expect(wrapper.emitted('close')).toBeUndefined();

        await wrapper.get('[role="dialog"]').trigger('keydown.esc');

        expect(
            wrapper
                .find('[data-testid="cockpit-issued-pay-code-expanded-qr"]')
                .exists(),
        ).toBe(false);
        expect(
            wrapper
                .find('[data-testid="cockpit-issued-pay-code-artifact-image"]')
                .exists(),
        ).toBe(true);
        expect(wrapper.emitted('close')).toBeUndefined();
    });

    it('enlarges the QR from the finalized canvas fallback', async () => {
        const wrapper = mount(CockpitIssuedPayCodeDialog, {
            props: {
                open: true,
                code: 'PAY-CANVAS-QR-1',
                amount: 50,
                currency: 'PHP',
                claimOutcome: 'provider_disbursement',
                voucherType: 'redeemable',
                claimQr: 'data:image/png;base64,CANVAS-QR',
                riderStamp: {
                    source: 'default',
                    reference: '',
                    imageUrl: null,
                    title: '',
                    description: '',
                    label: 'Rider Stamp',
                    fit: 'cover',
                    position: 'center',
                    scrim: 18,
                    theme: 'automatic',
                    composition: {
                        artworkSource: 'x_change',
                        artworkTreatment: 'automatic',
                        copySource: 'automatic',
                        showLogo: true,
                        showTagline: true,
                        claimMarker: 'qr',
                        claimMarkerPosition: 'bottom_right',
                        version: 2,
                    },
                    design: {
                        id: 'x-change-default',
                        version: 1,
                    },
                },
            },
            global: {
                stubs: {
                    Teleport: true,
                },
            },
        });

        await wrapper
            .get('[data-testid="cockpit-pay-code-canvas-claim-qr-button"]')
            .trigger('click');

        expect(
            wrapper
                .get(
                    '[data-testid="cockpit-issued-pay-code-expanded-qr-image"]',
                )
                .attributes('src'),
        ).toBe('data:image/png;base64,CANVAS-QR');
    });

    it('preserves explicit rider artwork composition in the finalized canvas', () => {
        const wrapper = mount(CockpitIssuedPayCodeDialog, {
            props: {
                open: true,
                code: 'PAY-ART-1',
                amount: 50,
                currency: 'PHP',
                purpose: 'Family support',
                claimOutcome: 'provider_disbursement',
                voucherType: 'redeemable',
                hasRiderDesign: true,
                riderDesignSource: 'message',
                riderDesignDocument:
                    '<!doctype html><html><body><h1>Family support</h1></body></html>',
            },
            global: {
                stubs: {
                    Teleport: true,
                },
            },
        });

        expect(
            wrapper
                .find('[data-testid="cockpit-pay-code-canvas-rider-og-design"]')
                .exists(),
        ).toBe(true);
        expect(
            wrapper
                .find('[data-testid="cockpit-pay-code-canvas-purpose"]')
                .exists(),
        ).toBe(false);
        expect(
            wrapper
                .find('[data-testid="cockpit-pay-code-canvas-rider-scrim"]')
                .exists(),
        ).toBe(true);
    });

    it('shows quantity math on the finalized Pay Code cost total', async () => {
        const wrapper = mount(CockpitIssuedPayCodeDialog, {
            props: {
                open: true,
                code: 'PAY-BATCH-2',
                amount: 100,
                currency: 'PHP',
                claimOutcome: 'provider_disbursement',
                voucherType: 'redeemable',
                quantity: 2,
                costEstimate: {
                    currency: 'PHP',
                    total: 65.3,
                },
            },
            global: {
                stubs: {
                    Teleport: true,
                },
            },
        });

        await wrapper
            .get('[data-testid="cockpit-pay-code-canvas-back-button"]')
            .trigger('click');

        expect(
            wrapper
                .get('[data-testid="cockpit-pay-code-cost-subtotal"]')
                .text(),
        ).toBe('2 × ₱65.30 = ₱130.60');
        expect(
            wrapper
                .get('[data-testid="cockpit-pay-code-cost-pay-code-value"]')
                .text(),
        ).toBe('₱100.00');
        expect(
            wrapper.get('[data-testid="cockpit-pay-code-cost-total"]').text(),
        ).toBe('₱230.60');
    });

    it('opens automatically with the canonical result after issuance', async () => {
        const fetchMock = vi.fn().mockResolvedValue({
            ok: true,
            json: vi.fn().mockResolvedValue({
                status: 'issued',
                result: {
                    code: 'PAY-MODAL-1',
                    issue_cost: {
                        currency: 'PHP',
                        charges: [
                            {
                                label: 'Pay Code Generation',
                                type: 'generation',
                                price: 12,
                            },
                        ],
                        total: 12,
                    },
                    links: {
                        redeem: 'https://example.test/x/claim/PAY-MODAL-1/experience',
                        redeem_path: '/x/claim/PAY-MODAL-1/experience',
                        share_card:
                            'https://example.test/x/claim/PAY-MODAL-1/share-card.png',
                        cockpit_detail: '/x/cockpit/pay-codes/PAY-MODAL-1',
                    },
                },
            }),
        });

        vi.stubGlobal('fetch', fetchMock);
        vi.stubGlobal('crypto', {
            randomUUID: () => 'cockpit-issued-dialog-idempotency',
        });

        const wrapper = mount(CockpitQuickGenerateSubmitPanel, {
            props: {
                templates: cockpitQuickGenerateTemplates,
                mutationContract: {
                    runtime_enabled: true,
                    route: 'x-change.cockpit.quick-generate.store',
                    route_url: '/x/cockpit/quick-generate',
                    allowed_methods: ['POST'],
                },
            },
            global: {
                stubs: {
                    Teleport: true,
                },
            },
        });

        await wrapper
            .get('[data-testid="cockpit-quick-generate-submit-panel"]')
            .trigger('submit');
        await Promise.resolve();
        await Promise.resolve();
        await wrapper.vm.$nextTick();

        expect(
            wrapper
                .get('[data-testid="cockpit-issued-pay-code-dialog"]')
                .text(),
        ).toContain('Pay Code PAY-MODAL-1 Is Ready');
        expect(
            wrapper
                .get('[data-testid="cockpit-issued-pay-code-detail"]')
                .attributes('href'),
        ).toBe('/x/cockpit/pay-codes/PAY-MODAL-1');
        expect(
            wrapper
                .get('[data-testid="cockpit-issued-pay-code-artifact-image"]')
                .attributes('src'),
        ).toBe('https://example.test/x/claim/PAY-MODAL-1/share-card.png');
        expect(
            wrapper
                .get('[data-testid="cockpit-issued-pay-code-dialog"]')
                .find('[data-testid="cockpit-pay-code-canvas"]')
                .exists(),
        ).toBe(false);
    });
});
