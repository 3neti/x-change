import { describe, expect, it } from 'vitest';
import { resolveCompiledFormViewModel } from '../../resources/js/components/x-change/compiledFormViewModel';

describe('compiled form view model', () => {
    it('resolves compiled form flow state', () => {
        const viewModel = resolveCompiledFormViewModel({
            boundary: {
                mode: 'compiled',
                phase: {
                    key: 'form_flow',
                    fields: [
                        { key: 'first_name', type: 'text', required: true },
                    ],
                },
            },
            values: {
                first_name: 'Lester',
            },
            submitting: false,
        });

        expect(viewModel.usesCompiledFormFlow).toBe(true);
        expect(viewModel.usesLegacyFormFlow).toBe(false);
        expect(viewModel.normalizedCompiledFormFlow).toMatchObject({
            fields: [
                { key: 'first_name', type: 'text', required: true },
            ],
        });
        expect(viewModel.missingRequiredFields).toEqual([]);
        expect(viewModel.isValid).toBe(true);
        expect(viewModel.submitState).toBe('idle');
    });

    it('resolves legacy form flow state', () => {
        const viewModel = resolveCompiledFormViewModel({
            boundary: {
                mode: 'legacy',
                phase: null,
            },
            values: {},
        });

        expect(viewModel.usesCompiledFormFlow).toBe(false);
        expect(viewModel.usesLegacyFormFlow).toBe(true);
        expect(viewModel.normalizedCompiledFormFlow).toBeNull();
        expect(viewModel.isValid).toBe(true);
    });

    it('resolves missing required fields', () => {
        const viewModel = resolveCompiledFormViewModel({
            boundary: {
                mode: 'compiled',
                phase: {
                    key: 'form_flow',
                    fields: [
                        { key: 'first_name', type: 'text', required: true },
                    ],
                },
            },
            values: {
                first_name: '',
            },
        });

        expect(viewModel.missingRequiredFields.map((field) => field.key)).toEqual([
            'first_name',
        ]);
        expect(viewModel.isValid).toBe(false);
    });

    it('resolves submit state', () => {
        expect(resolveCompiledFormViewModel({
            boundary: {
                mode: 'compiled',
                phase: {
                    key: 'form_flow',
                    fields: [],
                },
            },
            values: {},
            submitting: true,
        }).submitState).toBe('submitting');

        expect(resolveCompiledFormViewModel({
            boundary: {
                mode: 'compiled',
                phase: {
                    key: 'form_flow',
                    fields: [],
                },
            },
            values: {},
            submitError: 'Failed.',
        }).submitState).toBe('failed');
    });
});
