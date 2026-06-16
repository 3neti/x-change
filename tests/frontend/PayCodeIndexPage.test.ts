import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import IndexPage from '../../resources/js/pages/x-change/pay-codes/Index.vue';

vi.mock('@inertiajs/vue3', () => ({
    Head: {
        template: '<div><slot /></div>',
    },
    router: {
        visit: vi.fn(),
    },
}));

vi.mock('@/layouts/x-change/XChangeLayout.vue', () => ({
    default: {
        template: '<div><slot /></div>',
    },
}));

vi.mock('@/components/ui/button', () => ({
    Button: {
        template: '<button type="button"><slot /></button>',
    },
}));

vi.mock('@/components/ui/card', () => ({
    Card: {
        template: '<div><slot /></div>',
    },
    CardContent: {
        template: '<div><slot /></div>',
    },
    CardHeader: {
        template: '<div><slot /></div>',
    },
    CardTitle: {
        template: '<div><slot /></div>',
    },
}));

vi.mock('@/components/x-change/pay-codes', () => ({
    PayCodeFilters: {
        template: '<div />',
    },
    PayCodeListTable: {
        template: '<div />',
    },
    PayCodeStatsCards: {
        template: '<div />',
    },
}), { virtual: true });

vi.mock('@/composables/useXChangeRoutes', () => ({
    useXChangeRoutes: () => ({
        payCodes: {
            index: () => '/x/pay-codes',
            create: () => '/x/pay-codes/create',
            show: (code: string) => `/x/pay-codes/${code}`,
            approval: (code: string) => `/x/pay-codes/${code}/approval`,
        },
        claim: {
            startWithCode: (code: string) => `/x/claim?code=${code}`,
        },
        api: {
            vouchers: '/api/x/v1/vouchers',
        },
    }),
}));

vi.mock('lucide-vue-next', () => ({
    PlusCircle: {
        template: '<span />',
    },
}));

describe('PayCodeIndexPage', () => {
    it('renders the registry heading in red', () => {
        const wrapper = mount(IndexPage, {
            props: {
                vouchers: [
                    {
                        code: 'NHL3',
                        amount: 50,
                        currency: 'PHP',
                        status: 'redeemed',
                    },
                ],
            },
        });

        expect(wrapper.text()).toContain('Pay Code Registry');
        expect(wrapper.find('.text-red-600').text()).toBe('Pay Code Registry');
    });
});
