import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import ShowPage from '../../resources/js/pages/x-change/pay-codes/Show.vue';

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

vi.mock('@/components/x-change/pay-codes', () => ({
    PayCodeClaimHistory: {
        template: '<div />',
    },
    PayCodeInstructionSummary: {
        template: '<div />',
    },
    PayCodeQrSharePanel: {
        template: '<div />',
    },
    PayCodeStatusBadge: {
        template: '<span />',
    },
}));

vi.mock('@/composables/useXChangeRoutes', () => ({
    useXChangeRoutes: () => ({
        payCodes: {
            index: () => '/x/pay-codes',
        },
        claim: {
            startWithCode: (code: string) => `/x/claim?code=${code}`,
        },
    }),
}));

vi.mock('lucide-vue-next', () => ({
    ArrowLeft: {
        template: '<span />',
    },
    ExternalLink: {
        template: '<span />',
    },
    Copy: {
        template: '<span />',
    },
    CheckCircle2: {
        template: '<span />',
    },
    Clock: {
        template: '<span />',
    },
    ReceiptText: {
        template: '<span />',
    },
}));

describe('PayCodeShowPage', () => {
    it('uses the padded pay code page shell', () => {
        const wrapper = mount(ShowPage, {
            props: {
                voucher: {
                    code: 'TEST',
                    amount: 100,
                    currency: 'PHP',
                    status: 'active',
                },
            },
        });

        const shell = wrapper
            .findAll('div')
            .find((element) => element.classes().includes('lg:p-8'));

        expect(shell?.classes()).toEqual(
            expect.arrayContaining(['p-4', 'sm:p-6', 'lg:p-8']),
        );
    });
});
