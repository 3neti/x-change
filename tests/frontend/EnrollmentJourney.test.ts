import { mount } from '@vue/test-utils';
import { reactive } from 'vue';
import { describe, expect, it, vi } from 'vitest';
import Enroll from '../../resources/js/pages/x-change/enrollment/Enroll.vue';
import EnrollmentOffers from '../../resources/js/pages/x-change/cockpit/EnrollmentOffers.vue';

const post = vi.fn();
vi.mock('@inertiajs/vue3', () => ({
    Head: { template: '<div />' },
    useForm: (initial: Record<string, unknown>) => reactive({ ...initial, errors: {}, processing: false, post }),
}));
vi.mock('@/components/x-change/ClaimStepShell.vue', () => ({
    default: { template: '<main><slot /></main>' },
}));
vi.mock('../../resources/js/cockpit/layouts/CockpitLayout.vue', () => ({
    default: { template: '<div><slot /></div>' },
}));

const props = {
    offer: {
        title: 'Starter Policy',
        consent_statement: 'I agree to receive information about the Starter Policy.',
        consent_version: '2026-10',
    },
    store_url: '/x/enroll/01ARZ3NDEKTSV4RRFFQ69G5FAV',
};

describe('product enrollment', () => {
    it('shows the enrollment link and recorded count in the owner cockpit', () => {
        const page = mount(EnrollmentOffers, {
            props: {
                offers: [{
                    reference: '01ARZ3NDEKTSV4RRFFQ69G5FAV',
                    title: 'Starter Policy',
                    consent_version: '2026-10',
                    active: true,
                    consents_count: 2,
                    public_url: props.store_url,
                }],
                store_url: '/x/cockpit/enrollments',
            },
        });

        expect(page.text()).toContain('Starter Policy · 2 recorded');
        expect(page.get('a').attributes('href')).toBe(props.store_url);
    });

    it('shows the frozen statement and requires a checked opt-in before submission', async () => {
        post.mockClear();
        const page = mount(Enroll, { props });

        expect(page.text()).toContain(props.offer.consent_statement);
        expect(page.text()).toContain('version 2026-10');
        expect(page.get('input[type="checkbox"]').attributes('required')).toBeDefined();
        await page.get('input[type="checkbox"]').setValue(true);
        await page.get('form').trigger('submit');
        expect(post).toHaveBeenCalledWith(props.store_url);
    });

    it('shows the recorded receipt without a second consent form', () => {
        const page = mount(Enroll, { props: { ...props, receipt: '01ARZ3NDEKTSV4RRFFQ69G5FAV' } });

        expect(page.text()).toContain('Your opt-in was recorded.');
        expect(page.text()).toContain('not product approval');
        expect(page.find('form').exists()).toBe(false);
    });
});
