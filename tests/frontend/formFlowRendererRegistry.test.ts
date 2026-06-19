import { describe, expect, it } from 'vitest';
import {
    resolveFormFlowRenderer,
} from '../../resources/js/components/x-change/formFlowRendererRegistry';

describe('form flow renderer registry', () => {
    it('resolves supported renderer names', () => {
        expect(resolveFormFlowRenderer('text'))
            .toBe('TextFieldRenderer');

        expect(resolveFormFlowRenderer('email'))
            .toBe('EmailFieldRenderer');

        expect(resolveFormFlowRenderer('date'))
            .toBe('DateFieldRenderer');

        expect(resolveFormFlowRenderer('slice_selector'))
            .toBe('SliceSelectorFieldRenderer');
    });

    it('returns unsupported renderer for unknown types', () => {
        expect(resolveFormFlowRenderer('camera'))
            .toBe('UnsupportedFieldRenderer');
    });
});
