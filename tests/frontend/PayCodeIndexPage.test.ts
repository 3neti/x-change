import { mount } from '@vue/test-utils';
import { nextTick } from 'vue';
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
        emits: ['update:search', 'update:status'],
        template: `
            <div>
                <button
                    data-testid="awaiting-approval-filter"
                    type="button"
                    @click="$emit('update:status', 'awaiting_approval')"
                >
                    Awaiting approval
                </button>
            </div>
        `,
    },
    PayCodeListTable: {
        props: ['vouchers'],
        template: `
            <div data-testid="pay-code-list">
                <span
                    v-for="voucher in vouchers"
                    :key="voucher.code"
                >
                    {{ voucher.code }}
                </span>
            </div>
        `,
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

    it('filters vouchers by awaiting approval display status', async () => {
        const wrapper = mount(IndexPage, {
            props: {
                vouchers: [
                    {
                        code: 'R6DD',
                        amount: 18,
                        currency: 'PHP',
                        status: 'redeemed',
                        display_status: 'awaiting_approval',
                    },
                    {
                        code: 'CNC7',
                        amount: 20,
                        currency: 'PHP',
                        status: 'redeemed',
                    },
                ],
            },
        });

        expect(wrapper.find('[data-testid="pay-code-list"]').text()).toContain('R6DD');
        expect(wrapper.find('[data-testid="pay-code-list"]').text()).toContain('CNC7');

        await wrapper.find('[data-testid="awaiting-approval-filter"]').trigger('click');
        await nextTick();

        expect(wrapper.find('[data-testid="pay-code-list"]').text()).toContain('R6DD');
        expect(wrapper.find('[data-testid="pay-code-list"]').text()).not.toContain('CNC7');
    });
});
