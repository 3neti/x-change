import { describe, expect, it } from 'vitest';
import {
    classifyFeedbackDestination,
    normalizePhilippineFeedbackMobile,
    splitFeedbackDestinationInput,
} from '../../../resources/js/cockpit/feedbackDestinations';

describe('feedback destinations', () => {
    it('splits mixed separators without retaining empty values', () => {
        expect(
            splitFeedbackDestinationInput(
                'Issuer@Example.com, 09173011987;https://example.test/hook\n',
            ),
        ).toEqual([
            'Issuer@Example.com',
            '09173011987',
            'https://example.test/hook',
        ]);
    });

    it('classifies and normalizes every supported channel', () => {
        expect(classifyFeedbackDestination('Issuer@Example.com')).toEqual({
            channel: 'email',
            value: 'issuer@example.com',
        });
        expect(classifyFeedbackDestination('09173011987')).toEqual({
            channel: 'mobile',
            value: '+639173011987',
        });
        expect(
            classifyFeedbackDestination('https://example.test/feedback'),
        ).toEqual({
            channel: 'webhook',
            value: 'https://example.test/feedback',
        });
    });

    it('rejects unsupported or ambiguous destinations', () => {
        expect(classifyFeedbackDestination('not-a-destination')).toBeNull();
        expect(classifyFeedbackDestination('javascript:alert(1)')).toBeNull();
        expect(
            classifyFeedbackDestination('ftp://example.test/file'),
        ).toBeNull();
    });

    it('normalizes common Philippine mobile formats', () => {
        expect(normalizePhilippineFeedbackMobile('+63 917 301 1987')).toBe(
            '+639173011987',
        );
        expect(normalizePhilippineFeedbackMobile('639173011987')).toBe(
            '+639173011987',
        );
        expect(normalizePhilippineFeedbackMobile('9173011987')).toBe(
            '+639173011987',
        );
    });
});
