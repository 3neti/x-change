import { mount } from '@vue/test-utils';
import { defineComponent, nextTick, ref } from 'vue';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import {
    submitCompiledClaimForm,
    toCompiledClaimFormSubmissionPayload,
    type CompiledClaimFormPayload,
} from '../../resources/js/components/x-change/compiledClaimFormSubmission';

vi.mock('../../resources/js/components/x-change/VoucherInstructionsDisplay.vue', () => ({
    default: { template: '<div />' },
}));

vi.mock('../../resources/js/components/x-change/VoucherMetadataDisplay.vue', () => ({
    default: { template: '<div />' },
}));

vi.mock('../../resources/js/components/x-change/VoucherStatusStamp.vue', () => ({
    default: { template: '<div />' },
}));

const { post } = vi.hoisted(() => ({
    post: vi.fn(),
}));

vi.mock('@inertiajs/vue3', () => ({
    usePage: () => ({
        props: {
            errors: {},
        },
    }),
    useForm: (initial: Record<string, unknown>) => ({
        ...initial,
        processing: false,
        get: vi.fn(),
    }),
    router: {
        post,
    },
}));

import ClaimWidget from '../../resources/js/components/x-change/ClaimWidget.vue';

function mountCompiledFormOwner() {
    const Owner = defineComponent({
        components: { ClaimWidget },
        setup() {
            const payload = ref<Record<string, unknown> | null>(null);
            const submissionPayload = ref<Record<string, unknown> | null>(null);
            const compiledFormSubmitted = ref(false);
            const compiledFormSubmitError = ref<string | null>(null);

            function capturePayload(value: CompiledClaimFormPayload) {
                payload.value = value;
                submissionPayload.value = toCompiledClaimFormSubmissionPayload(value);

                submitCompiledClaimForm(value, {
                    onSuccess: () => {
                        compiledFormSubmitted.value = true;
                    },
                    onError: () => {
                        compiledFormSubmitError.value = 'Submission failed.';
                    },
                });
            }

            return {
                payload,
                submissionPayload,
                compiledFormSubmitted,
                compiledFormSubmitError,
                capturePayload,
            };
        },
        template: `
            <ClaimWidget
                initial-code="TEST123"
                :claim-experience="{
                    phases: [
                        {
                            key: 'form_flow',
                            owner: 'form-flow',
                            source: 'claim_experience',
                            status: 'active',
                            fields: [
                                {
                                    key: 'first_name',
                                    type: 'text',
                                    label: 'First Name',
                                    required: true,
                                },
                            ],
                            values: {
                                first_name: 'Lester',
                            },
                            stages: [],
                        },
                    ],
                }"
                :compiled-form-submitted="compiledFormSubmitted"
                :compiled-form-submit-error="compiledFormSubmitError"
                @submit:compiled-form="capturePayload"
            />

            <pre data-testid="owner-captured-payload">{{ JSON.stringify(payload, null, 2) }}</pre>
            <pre data-testid="owner-submission-payload">{{ JSON.stringify(submissionPayload, null, 2) }}</pre>
            <pre data-testid="owner-submitted">{{ JSON.stringify(compiledFormSubmitted) }}</pre>
            <pre data-testid="owner-submit-error">{{ JSON.stringify(compiledFormSubmitError) }}</pre>
        `,
    });

    return mount(Owner);
}

beforeEach(() => {
    post.mockClear();
});

describe('ClaimWidget compiled form owner boundary', () => {
    it('allows an owner component to capture compiled form submit payload', async () => {
        const wrapper = mountCompiledFormOwner();

        await nextTick();
        await wrapper.find('form').trigger('submit');

        expect(JSON.parse(
            wrapper.find('[data-testid="owner-captured-payload"]').text()
        )).toEqual({
            code: 'TEST123',
            values: {
                first_name: 'Lester',
            },
        });

        expect(JSON.parse(
            wrapper.find('[data-testid="owner-submission-payload"]').text()
        )).toEqual({
            code: 'TEST123',
            inputs: {
                first_name: 'Lester',
            },
            mode: 'compiled_form',
        });

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

        const postOptions = post.mock.calls[0][2];

        postOptions.onSuccess();

        await nextTick();

        expect(JSON.parse(
            wrapper.find('[data-testid="owner-submitted"]').text()
        )).toBe(true);
    });

    it('drives failed compiled form submit state from adapter error callback', async () => {
        const wrapper = mountCompiledFormOwner();

        await nextTick();
        await wrapper.find('form').trigger('submit');

        expect(JSON.parse(
            wrapper.find('[data-testid="owner-captured-payload"]').text()
        )).toEqual({
            code: 'TEST123',
            values: {
                first_name: 'Lester',
            },
        });

        expect(JSON.parse(
            wrapper.find('[data-testid="owner-submission-payload"]').text()
        )).toEqual({
            code: 'TEST123',
            inputs: {
                first_name: 'Lester',
            },
            mode: 'compiled_form',
        });

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

        const postOptions = post.mock.calls[0][2];

        postOptions.onError({
            message: 'Submission failed.',
        });

        await nextTick();

        expect(JSON.parse(
            wrapper.find('[data-testid="owner-submit-error"]').text()
        )).toBe('Submission failed.');

        expect(wrapper.find('[data-testid="claim-widget-submit-error"]').text()).toBe('Submission failed.');
    });
});
