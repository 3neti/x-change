import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import XRayClaimPreview from '../../resources/js/components/x-change/XRayClaimPreview.vue';

vi.mock('lucide-vue-next', () => ({
    AlertCircle: {
        template: '<span />',
    },
    CheckCircle2: {
        template: '<span />',
    },
    Clock: {
        template: '<span />',
    },
    HelpCircle: {
        template: '<span />',
    },
    XCircle: {
        template: '<span />',
    },
    Camera: {
        template: '<span />',
    },
    IdCard: {
        template: '<span />',
    },
    KeyRound: {
        template: '<span />',
    },
    MapPin: {
        template: '<span />',
    },
    PenLine: {
        template: '<span />',
    },
    ShieldCheck: {
        template: '<span />',
    },
    Smartphone: {
        template: '<span />',
    },
    Wallet: {
        template: '<span />',
    },
}));

describe('XRayClaimPreview', () => {
    it('renders a friendly "Pay Code preview" panel with a human status label, not the raw status string', () => {
        const wrapper = mount(XRayClaimPreview, {
            props: {
                result: {
                    visible: true,
                    status: 'claimable',
                    disclosures: [
                        {
                            key: 'status',
                            label: 'Status',
                            value: 'claimable',
                        },
                    ],
                    requirements: [],
                },
            },
        });

        expect(wrapper.text()).toContain('Pay Code preview');
        expect(wrapper.text()).toContain('Ready to claim');
        expect(wrapper.get('[data-testid="xray-status-description"]').text()).toBe(
            'This Pay Code is ready to be claimed.',
        );
        // The raw status disclosure row is redundant with the friendly badge
        // above and should not be dumped verbatim.
        expect(wrapper.text()).not.toContain('claimable');
    });

    it('renders friendly status labels for every claimability state', () => {
        const cases: Array<[string, string]> = [
            ['claimable', 'Ready to claim'],
            ['partially_claimable', 'Partially claimable'],
            ['redeemed', 'Already claimed'],
            ['expired', 'Expired'],
            ['hidden', 'Unavailable'],
            ['not_found', 'Not found'],
        ];

        for (const [status, label] of cases) {
            const wrapper = mount(XRayClaimPreview, {
                props: { result: { visible: status !== 'not_found', status } },
            });

            expect(wrapper.text()).toContain(label);
        }
    });

    it('renders compact, human-readable requirement chips with icons', () => {
        const wrapper = mount(XRayClaimPreview, {
            props: {
                result: {
                    visible: true,
                    status: 'claimable',
                    requirements: [
                        {
                            key: 'mobile',
                            label: 'Mobile',
                            description: 'Mobile number is required.',
                        },
                        {
                            key: 'otp',
                            label: 'Otp',
                        },
                    ],
                },
            },
        });

        expect(wrapper.text()).toContain("What you'll need");
        expect(wrapper.text()).toContain('Mobile number');
        expect(wrapper.text()).toContain('OTP');
        // Friendly copy replaces debug-style backend labels/keys.
        expect(wrapper.text()).not.toContain('Otp');
        expect(wrapper.text()).not.toContain('mobile');
    });

    it('renders issuer preview stages under a human heading', () => {
        const wrapper = mount(XRayClaimPreview, {
            props: {
                result: {
                    visible: true,
                    status: 'claimable',
                    stages: [
                        {
                            type: 'message',
                            payload: {
                                message: 'Issuer preview message.',
                            },
                        },
                    ],
                },
            },
        });

        expect(wrapper.text()).toContain('Note from the issuer');
        expect(wrapper.text()).toContain('Issuer preview message.');
    });

    it('shows a quiet redaction footer outside of any card or alert, never a loud warning', () => {
        const wrapper = mount(XRayClaimPreview, {
            props: {
                result: {
                    visible: true,
                    status: 'claimable',
                    redactions: [{ key: 'amount' }],
                },
            },
        });

        const footer = wrapper.get('[data-testid="xray-redaction-footer"]');

        expect(footer.text()).toBe(
            'Private issuer and payout details are protected until they are needed.',
        );
        expect(footer.text().toLowerCase()).not.toContain('warning');
        expect(footer.text().toLowerCase()).not.toContain('hidden');

        // The footer must not be nested inside any Card/Alert -- it should
        // read as a small aside, not a prominent notice.
        for (const card of wrapper.findAll('[data-testid="card"]')) {
            expect(card.text()).not.toContain('Private issuer');
        }
        expect(wrapper.find('[data-testid="alert"]').exists()).toBe(false);
    });

    it('renders no redaction footer at all when nothing was redacted', () => {
        const wrapper = mount(XRayClaimPreview, {
            props: {
                result: {
                    visible: true,
                    status: 'claimable',
                    redactions: [],
                },
            },
        });

        expect(wrapper.find('[data-testid="xray-redaction-footer"]').exists()).toBe(false);
    });

    it('renders inspection errors', () => {
        const wrapper = mount(XRayClaimPreview, {
            props: {
                error: 'Unable to inspect this Pay Code.',
            },
        });

        expect(wrapper.text()).toContain('Unable to inspect this Pay Code.');
    });

    it('summarizes html preview stages as readable text', () => {
        const wrapper = mount(XRayClaimPreview, {
            props: {
                result: {
                    visible: true,
                    status: 'claimable',
                    stages: [
                        {
                            type: 'splash',
                            payload: {
                                content_type: 'html',
                                content: '<h1>Claim walkthrough</h1><p>This splash confirms the Pay Code.</p>',
                            },
                        },
                    ],
                },
            },
        });

        expect(wrapper.text()).toContain('Claim walkthrough This splash confirms the Pay Code.');
        expect(wrapper.text()).not.toContain('<h1>');
    });
});
