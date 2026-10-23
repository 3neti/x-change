import { describe, expect, it } from 'vitest';
import { parsePayCodeInput, parsePayCodeOcr } from '../../resources/js/pages/x-change/pos/storedValueScan';

describe('stored value Pay Code scanning', () => {
    it('accepts only a complete marked code from camera OCR', () => {
        expect(parsePayCodeOcr('Fare card\n|| ABCD-1234 ||\nCollect')).toBe('ABCD-1234');
        expect(parsePayCodeOcr('Pay Code || AA317 ||')).toBe('AA317');
        expect(parsePayCodeOcr('ABCD-1234')).toBeNull();
        expect(parsePayCodeOcr('| ABCD-1234 |')).toBeNull();
        expect(parsePayCodeOcr('||| ABCD-1234 |||')).toBeNull();
        expect(parsePayCodeOcr('|| ABCD-1234 || and || WXYZ ||')).toBeNull();
        expect(parsePayCodeOcr('|| ABCD-I234 ||')).toBe('ABCD-I234');
    });

    it('accepts raw manual and keyboard scanner input without guessing characters', () => {
        expect(parsePayCodeInput(' abcd-1234\n')).toBe('ABCD-1234');
        expect(parsePayCodeInput('|| ABCD-1234 ||')).toBe('ABCD-1234');
        expect(parsePayCodeInput('| ABCD-1234 |')).toBeNull();
        expect(parsePayCodeInput('ABCD 1234')).toBeNull();
    });
});
