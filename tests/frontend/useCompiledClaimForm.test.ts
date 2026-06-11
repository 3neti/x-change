import { computed, nextTick } from 'vue';
import { describe, expect, it, vi } from 'vitest';
import { useCompiledClaimForm } from '../../resources/js/components/x-change/useCompiledClaimForm';

describe('useCompiledClaimForm', () => {
    function makeSubject(overrides: {
        claimExperience?: Record<string, unknown> | null;
        submitted?: boolean | null;
        submitError?: string | null;
        initialCode?: string | null;
    } = {}) {
        const emitSubmit = vi.fn();
        const emitUpdateValues = vi.fn();

        const subject = useCompiledClaimForm({
            initialCode: overrides.initialCode ?? 'TEST123',
            claimExperience: computed(() => overrides.claimExperience ?? {
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
            }),
            submitted: computed(() => overrides.submitted ?? false),
            submitError: computed(() => overrides.submitError ?? null),
            emitSubmit,
            emitUpdateValues,
        });

        return {
            subject,
            emitSubmit,
            emitUpdateValues,
        };
    }

    it('resolves active compiled form flow', () => {
        const { subject } = makeSubject();

        expect(subject.normalizedFlow.value).not.toBeNull();
        expect(subject.usesLegacyFlow.value).toBe(false);
    });

    it('falls back to legacy flow when compiled form phase is absent', () => {
        const { subject } = makeSubject({
            claimExperience: {
                phases: [],
            },
        });

        expect(subject.normalizedFlow.value).toBeNull();
        expect(subject.usesLegacyFlow.value).toBe(true);
    });

    it('emits updated compiled form values', () => {
        const { subject, emitUpdateValues } = makeSubject();

        subject.updateValues({
            first_name: 'Updated Name',
        });

        expect(subject.values.value).toEqual({
            first_name: 'Updated Name',
        });

        expect(emitUpdateValues).toHaveBeenCalledWith({
            first_name: 'Updated Name',
        });
    });

    it('blocks submit when compiled form is invalid', () => {
        const { subject, emitSubmit } = makeSubject();

        subject.updateValues({
            first_name: '',
        });

        subject.submit();

        expect(emitSubmit).not.toHaveBeenCalled();
        expect(subject.submitting.value).toBe(false);
    });

    it('emits compiled form submit payload when valid', () => {
        const { subject, emitSubmit } = makeSubject();

        subject.updateValues({
            first_name: 'Lester',
        });

        subject.submit();

        expect(subject.submitting.value).toBe(true);

        expect(emitSubmit).toHaveBeenCalledWith({
            code: 'TEST123',
            values: {
                first_name: 'Lester',
            },
        });
    });

    it('is invalid when required compiled field is missing', () => {
        const { subject } = makeSubject();

        subject.updateValues({
            first_name: '',
        });

        expect(subject.isValid.value).toBe(false);
    });

    it('is valid when required compiled field is provided', () => {
        const { subject } = makeSubject();

        subject.updateValues({
            first_name: 'Lester',
        });

        expect(subject.isValid.value).toBe(true);
    });

    it('uses submitted and error inputs in the view model', async () => {
        const submitted = computed(() => true);
        const submitError = computed(() => 'Submission failed.');
        const emitSubmit = vi.fn();
        const emitUpdateValues = vi.fn();

        const subject = useCompiledClaimForm({
            initialCode: 'TEST123',
            claimExperience: computed(() => ({
                phases: [
                    {
                        key: 'form_flow',
                        status: 'active',
                        fields: [],
                        stages: [],
                    },
                ],
            })),
            submitted,
            submitError,
            emitSubmit,
            emitUpdateValues,
        });

        await nextTick();

        expect(subject.viewModel.value.submitState).toBe('failed');
    });
});

