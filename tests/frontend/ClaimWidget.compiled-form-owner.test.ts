import { mount } from '@vue/test-utils';
import { defineComponent, nextTick, ref } from 'vue';
import { describe, expect, it, vi } from 'vitest';
import {
    toCompiledClaimFormSubmissionPayload,
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
}));

import ClaimWidget from '../../resources/js/components/x-change/ClaimWidget.vue';

describe('ClaimWidget compiled form owner boundary', () => {
    it('allows an owner component to capture compiled form submit payload', async () => {
        const Owner = defineComponent({
            components: { ClaimWidget },
            setup() {
                const payload = ref<Record<string, unknown> | null>(null);

                const submissionPayload = ref<Record<string, unknown> | null>(null);

                function capturePayload(value: {
                    code: string;
                    values: Record<string, unknown>;
                }) {
                    payload.value = value;
                    submissionPayload.value = toCompiledClaimFormSubmissionPayload(value);
                }

                return {
                    payload,
                    submissionPayload,
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
                    @submit:compiled-form="capturePayload"
                />

                <pre data-testid="owner-captured-payload">{{ JSON.stringify(payload, null, 2) }}</pre>
                <pre data-testid="owner-submission-payload">{{ JSON.stringify(submissionPayload, null, 2) }}</pre>
            `,
        });

        const wrapper = mount(Owner);

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
        });
    });
});
