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
    it('renders a verified Pay Code panel without repeating the claim action', () => {
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

        expect(wrapper.text()).toContain('Pay Code verified');
        expect(wrapper.text()).not.toContain('Ready to claim');
        expect(wrapper.find('[data-testid="xray-status-description"]').exists()).toBe(false);
        // The raw status disclosure row is redundant with the friendly badge
        // above and should not be dumped verbatim.
        expect(wrapper.text()).not.toContain('claimable');
    });

    it('renders friendly status labels for every claimability state', () => {
        const cases: Array<[string, string]> = [
            ['claimable', 'Pay Code verified'],
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

    it('shows labeled slice choices on the first claim preview', () => {
        const wrapper = mount(XRayClaimPreview, {
            props: {
                result: {
                    visible: true,
                    status: 'partially_claimable',
                    disclosures: [
                        {
                            key: 'remaining_slices',
                            label: 'Remaining Slices',
                            value: [
                                {
                                    id: 'slice_1',
                                    label: 'Morning fare',
                                    amount_minor: 2500,
                                    status: 'available',
                                    status_label: 'Available',
                                },
                            ],
                        },
                    ],
                },
            },
        });

        expect(wrapper.get('[data-testid="xray-slice-plan"]').text()).toContain(
            'Choose a slice',
        );
        expect(wrapper.text()).toContain('Morning fare');
        expect(wrapper.text()).toContain('₱25.00');
        expect(wrapper.text()).not.toContain('[object Object]');
    });

    it('does not render a disclosure-style redaction footer on the claim page', () => {
        const wrapper = mount(XRayClaimPreview, {
            props: {
                result: {
                    visible: true,
                    status: 'claimable',
                    redactions: [{ key: 'amount' }],
                },
            },
        });

        expect(wrapper.find('[data-testid="xray-redaction-footer"]').exists()).toBe(false);
        expect(wrapper.text()).not.toContain('Private issuer');
        expect(wrapper.text()).not.toContain('protected until they are needed');
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
