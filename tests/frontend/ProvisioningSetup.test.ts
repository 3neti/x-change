import { mount } from '@vue/test-utils';
import { afterEach, describe, expect, it, vi } from 'vitest';
import ProvisioningSetup from '../../resources/js/components/x-change/ProvisioningSetup.vue';

vi.mock('@/components/ui/badge', () => ({
    Badge: {
        template: '<span><slot /></span>',
    },
}));

vi.mock('@/components/ui/button', () => ({
    Button: {
        emits: ['click'],
        props: ['as', 'href', 'disabled'],
        template: '<component :is="as || \'button\'" :href="href" :disabled="disabled" type="button" @click="$emit(\'click\')"><slot /></component>',
    },
}));

vi.mock('@/components/ui/card', () => ({
    Card: { template: '<div><slot /></div>' },
    CardContent: { template: '<div><slot /></div>' },
    CardDescription: { template: '<div><slot /></div>' },
    CardFooter: { template: '<div><slot /></div>' },
    CardHeader: { template: '<div><slot /></div>' },
    CardTitle: { template: '<div><slot /></div>' },
}));

vi.mock('@/components/ui/separator', () => ({
    Separator: { template: '<hr />' },
}));

vi.mock('@/components/ui/alert', () => ({
    Alert: { template: '<div><slot /></div>', props: ['variant'] },
    AlertDescription: { template: '<div><slot /></div>' },
}));

vi.mock('lucide-vue-next', () => ({
    ArrowRight: { template: '<span />' },
    CheckCircle2: { template: '<span />' },
    Loader2: { template: '<span />' },
    RefreshCcw: { template: '<span />' },
    ShieldAlert: { template: '<span />' },
}));

describe('ProvisioningSetup', () => {
    afterEach(() => {
        vi.restoreAllMocks();
    });

    it('renders descriptor-driven setup content', () => {
        const wrapper = mount(ProvisioningSetup, {
            props: {
                requirement: {
                    provider: 'paynamics',
                    mode: 'wallet_create',
                    missing: ['provider_customer_wallet'],
                    descriptor: {
                        title: 'Create your Paynamics wallet',
                        description: 'Complete wallet setup so Pay Codes can be issued and paid out.',
                        steps: ['profile', 'wallet', 'kyc', 'ready'],
                        fields: ['mobile', 'name', 'email'],
                        actions: ['continue', 'open_capture_link'],
                    },
                    onboarding: {
                        reference: 'onb-123',
                    },
                },
            },
        });

        expect(wrapper.text()).toContain('Create your Paynamics wallet');
        expect(wrapper.text()).toContain('Complete wallet setup so Pay Codes can be issued and paid out.');
        expect(wrapper.text()).toContain('profile');
        expect(wrapper.text()).toContain('mobile');
        expect(wrapper.text()).toContain('continue');
        expect(wrapper.text()).toContain('provider customer wallet');
    });

    it('emits resume from the primary CTA without checking status first', async () => {
        global.fetch = vi.fn() as typeof fetch;

        const wrapper = mount(ProvisioningSetup, {
            props: {
                requirement: {
                    descriptor: {
                        title: 'Create your Paynamics wallet',
                    },
                    onboarding: {
                        reference: 'onb-123',
                        links: {
                            status_url: '/api/onboarding/onb-123',
                        },
                    },
                },
            },
        });

        await wrapper.findAll('button')[1]?.trigger('click');

        expect(global.fetch).not.toHaveBeenCalled();
        expect(wrapper.emitted('resume')).toHaveLength(1);
    });

    it('links the primary CTA to the onboarding web surface when projected', () => {
        const wrapper = mount(ProvisioningSetup, {
            props: {
                requirement: {
                    descriptor: {
                        title: 'Create your Paynamics wallet',
                    },
                    onboarding: {
                        reference: 'onb-123',
                        links: {
                            resume_url: '/onboarding/onb-123',
                        },
                    },
                },
            },
        });

        const link = wrapper.find('a[href^="/onboarding/onb-123"]');

        expect(link.exists()).toBe(true);
        expect(link.attributes('href')).toContain('return_url=');
    });

    it('checks onboarding status from the secondary CTA and emits resume when setup is complete', async () => {
        global.fetch = vi.fn().mockResolvedValue({
            ok: true,
            json: async () => ({
                success: true,
                data: {
                    status: 'completed',
                },
            }),
        }) as typeof fetch;

        const wrapper = mount(ProvisioningSetup, {
            props: {
                requirement: {
                    descriptor: {
                        title: 'Add payout destination',
                    },
                    onboarding: {
                        reference: 'onb-claim-123',
                        links: {
                            status_url: '/api/onboarding/onb-claim-123',
                        },
                    },
                },
            },
        });

        await wrapper.findAll('button')[0]?.trigger('click');

        expect(global.fetch).toHaveBeenCalledWith('/api/onboarding/onb-claim-123', expect.any(Object));
        expect(wrapper.emitted('resume')).toHaveLength(1);
    });
});
