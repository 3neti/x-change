import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';

const { post } = vi.hoisted(() => ({
    post: vi.fn(),
}));

vi.mock('@inertiajs/vue3', () => ({
    Head: { template: '<div><slot /></div>' },
    router: { post },
}));

vi.mock('../../resources/js/components/x-change/ClaimWidget.vue', () => ({
    default: {
        props: [
            'initialCode',
            'claimExperience',
            'compiledFormSubmitted',
            'compiledFormSubmitError',
        ],
        emits: ['submit:compiled-form', 'update:compiled-form-values'],
        template: `
            <div>
                <button
                    data-testid="stub-submit-compiled-form"
                    @click="$emit('submit:compiled-form', {
                        code: 'TEST123',
                        values: { first_name: 'Lester' }
                    })"
                >
                    submit
                </button>

                <div data-testid="stub-submitted">
                    {{ compiledFormSubmitted ? 'submitted' : 'not-submitted' }}
                </div>

                <div data-testid="stub-error">
                    {{ compiledFormSubmitError ?? '' }}
                </div>

                <button
                    data-testid="stub-update-compiled-form-values"
                    @click="$emit('update:compiled-form-values',
                    { first_name: 'Updated Lester'}
                    )"
                >
                    update values
                </button>
            </div>
        `,
    },
}));

import Entry from '../../resources/js/pages/x-change/claim/Entry.vue';

beforeEach(() => {
    post.mockClear();
});

describe('claim entry compiled form submission', () => {
    it('posts compiled form payload through the entry owner', async () => {
        const wrapper = mount(Entry, {
            props: {
                initial_code: 'TEST123',
                claim_experience: {
                    phases: [],
                },
            },
        });

        await wrapper.find('[data-testid="stub-submit-compiled-form"]').trigger('click');

        expect(post).toHaveBeenCalledWith(
            '/x/claim',
            {
                code: 'TEST123',
                inputs: {
                    first_name: 'Lester',
                },
                mode: 'compiled_form',
            },
            expect.objectContaining({
                preserveScroll: true,
            })
        );
    });

    it('passes submitted state back to ClaimWidget after successful submit', async () => {
        const wrapper = mount(Entry, {
            props: {
                initial_code: 'TEST123',
                claim_experience: {
                    phases: [],
                },
            },
        });

        await wrapper.find('[data-testid="stub-submit-compiled-form"]').trigger('click');

        const options = post.mock.calls[0][2];

        options.onSuccess();

        await wrapper.vm.$nextTick();

        expect(wrapper.find('[data-testid="stub-submitted"]').text()).toBe('submitted');
    });

    it('passes submit error back to ClaimWidget after failed submit', async () => {
        const wrapper = mount(Entry, {
            props: {
                initial_code: 'TEST123',
                claim_experience: {
                    phases: [],
                },
            },
        });

        await wrapper.find('[data-testid="stub-submit-compiled-form"]').trigger('click');

        const options = post.mock.calls[0][2];

        options.onError({
            first_name: 'First name is required.',
        });

        await wrapper.vm.$nextTick();

        expect(wrapper.find('[data-testid="stub-error"]').text()).toBe('First name is required.');
    });

    it('resets compiled form submit error when compiled form values change', async () => {
        const wrapper = mount(Entry, {
            props: {
                initial_code: 'TEST123',
                claim_experience: {
                    phases: [],
                },
            },
        });

        await wrapper.find('[data-testid="stub-submit-compiled-form"]').trigger('click');

        const options = post.mock.calls[0][2];

        options.onError({
            first_name: 'First name is required.',
        });

        await wrapper.vm.$nextTick();

        expect(wrapper.find('[data-testid="stub-error"]').text()).toBe('First name is required.');

        await wrapper
            .find('[data-testid="stub-update-compiled-form-values"]')
            .trigger('click');

        await wrapper.vm.$nextTick();

        expect(wrapper.find('[data-testid="stub-error"]').text()).toBe('');
    });
});
