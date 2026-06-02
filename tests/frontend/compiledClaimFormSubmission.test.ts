import { beforeEach, describe, expect, it, vi } from 'vitest';

const { post } = vi.hoisted(() => ({
    post: vi.fn(),
}));

vi.mock('@inertiajs/vue3', () => ({
    router: {
        post,
    },
}));

import {
    submitCompiledClaimForm,
    toCompiledClaimFormSubmissionPayload,
} from '../../resources/js/components/x-change/compiledClaimFormSubmission';

beforeEach(() => {
    post.mockClear();
});

describe('compiled claim form submission', () => {
    it('maps claim widget form payload to backend submission payload', () => {
        expect(toCompiledClaimFormSubmissionPayload({
            code: 'TEST123',
            values: {
                first_name: 'Lester',
                email: 'lester@example.com',
            },
        })).toEqual({
            code: 'TEST123',
            inputs: {
                first_name: 'Lester',
                email: 'lester@example.com',
            },
        });
    });

    it('posts compiled claim form submission payload through inertia', () => {
        const onSuccess = vi.fn();
        const onError = vi.fn();
        const onFinish = vi.fn();

        submitCompiledClaimForm(
            {
                code: 'TEST123',
                values: {
                    first_name: 'Lester',
                },
            },
            {
                onSuccess,
                onError,
                onFinish,
            }
        );

        expect(post).toHaveBeenCalledWith(
            '/x/claim',
            {
                code: 'TEST123',
                inputs: {
                    first_name: 'Lester',
                },
            },
            {
                preserveScroll: true,
                onSuccess,
                onError,
                onFinish,
            }
        );
    });
});
