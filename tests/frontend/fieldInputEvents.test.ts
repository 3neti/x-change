import { describe, expect, it } from 'vitest';
import {
    inputElementValue,
    selectElementValue,
    textareaElementValue,
} from '../../resources/js/components/x-change/renderers/fieldInputEvents';

describe('field input events', () => {
    it('extracts input element value', () => {
        const input = document.createElement('input');
        input.value = 'hello';

        expect(inputElementValue({
            target: input,
        } as unknown as Event)).toBe('hello');
    });

    it('extracts textarea element value', () => {
        const textarea = document.createElement('textarea');
        textarea.value = 'long text';

        expect(textareaElementValue({ target: textarea } as unknown as Event)).toBe('long text');
    });

    it('extracts select element value', () => {
        const select = document.createElement('select');
        const option = document.createElement('option');
        option.value = 'BANK_A';
        option.text = 'Bank A';
        select.appendChild(option);
        select.value = 'BANK_A';

        expect(selectElementValue({ target: select } as unknown as Event)).toBe('BANK_A');
    });
});
