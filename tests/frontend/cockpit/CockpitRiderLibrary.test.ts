import { mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { nextTick } from 'vue';
import CockpitRiderLibrary from '../../../resources/js/cockpit/components/CockpitRiderLibrary.vue';

const { destroy, patch, post } = vi.hoisted(() => ({
    destroy: vi.fn(),
    patch: vi.fn(),
    post: vi.fn(),
}));

vi.mock('@inertiajs/vue3', () => ({
    router: {
        delete: destroy,
        patch,
        post,
    },
}));

const entries = [
    {
        reference: '01SAVED',
        kind: 'url' as const,
        label: 'Friday playlist',
        payload: { url: 'https://open.spotify.com/track/saved' },
        saved: true,
        pinned: true,
        use_count: 4,
    },
    {
        reference: '01RECENT',
        kind: 'url' as const,
        label: 'Documentation',
        payload: { url: 'https://example.test/recent' },
        saved: false,
        pinned: false,
        use_count: 2,
    },
    {
        reference: '01SPLASH',
        kind: 'splash' as const,
        label: 'Welcome screen',
        payload: {
            splash: '<strong>Welcome</strong><script>unsafe()</script>',
            format: 'html',
        },
        saved: true,
        pinned: false,
        use_count: 1,
    },
];

describe('Cockpit Rider Library', () => {
    beforeEach(() => {
        destroy.mockReset();
        patch.mockReset();
        post.mockReset();
    });

    afterEach(() => {
        document.body.innerHTML = '';
    });

    it('separates saved and recently used entries and applies one choice', async () => {
        const wrapper = mount(CockpitRiderLibrary, {
            attachTo: document.body,
            props: {
                kind: 'url',
                entries,
                currentPayload: { url: '' },
            },
        });

        await wrapper
            .get('[data-testid="cockpit-rider-library-open"]')
            .trigger('click');

        const dialog = document.querySelector<HTMLElement>(
            '[data-testid="cockpit-rider-library-dialog"]',
        );

        expect(dialog).not.toBeNull();
        expect(dialog?.textContent).toContain('Saved');
        expect(dialog?.textContent).toContain('Recently Used');
        expect(dialog?.textContent).toContain('Friday playlist');
        expect(dialog?.textContent).toContain('Documentation');
        expect(dialog?.textContent).not.toContain('Welcome screen');

        dialog
            ?.querySelector<HTMLButtonElement>(
                '[data-testid="cockpit-rider-library-use"]',
            )
            ?.click();
        await nextTick();

        expect(wrapper.emitted('apply')?.[0]).toEqual([
            { url: 'https://open.spotify.com/track/saved' },
        ]);
        expect(
            document.querySelector(
                '[data-testid="cockpit-rider-library-dialog"]',
            ),
        ).toBeNull();
    });

    it('searches the bounded owner library without rendering HTML', async () => {
        const wrapper = mount(CockpitRiderLibrary, {
            attachTo: document.body,
            props: {
                kind: 'splash',
                entries,
                currentPayload: { splash: '', format: 'plain' },
            },
        });

        await wrapper
            .get('[data-testid="cockpit-rider-library-open"]')
            .trigger('click');

        const search = document.querySelector<HTMLInputElement>(
            '[data-testid="cockpit-rider-library-search"]',
        );
        expect(search).not.toBeNull();

        search!.value = 'welcome';
        search!.dispatchEvent(new Event('input'));
        await nextTick();

        const dialog = document.querySelector<HTMLElement>(
            '[data-testid="cockpit-rider-library-dialog"]',
        );

        expect(dialog?.textContent).toContain('Welcome screen');
        expect(dialog?.textContent).toContain('Welcome unsafe()');
        expect(dialog?.querySelector('script')).toBeNull();
    });

    it('saves the current Rider through its Wayfinder action', async () => {
        const wrapper = mount(CockpitRiderLibrary, {
            attachTo: document.body,
            props: {
                kind: 'url',
                entries: [],
                currentPayload: { url: 'https://example.test/new' },
            },
        });

        await wrapper
            .get('[data-testid="cockpit-rider-library-save-open"]')
            .trigger('click');

        const label = document.querySelector<HTMLInputElement>(
            '[data-testid="cockpit-rider-library-label"]',
        );
        label!.value = 'My useful link';
        label!.dispatchEvent(new Event('input'));

        document
            .querySelector<HTMLButtonElement>(
                '[data-testid="cockpit-rider-library-save"]',
            )
            ?.click();
        await nextTick();

        expect(post).toHaveBeenCalledOnce();
        expect(post.mock.calls[0]?.[0]).toEqual({
            url: '/x/cockpit/rider-library',
            method: 'post',
        });
        expect(post.mock.calls[0]?.[1]).toEqual({
            kind: 'url',
            label: 'My useful link',
            payload: { url: 'https://example.test/new' },
        });
    });

    it('pins and forgets only the selected library entry', async () => {
        const wrapper = mount(CockpitRiderLibrary, {
            attachTo: document.body,
            props: {
                kind: 'url',
                entries,
                currentPayload: { url: '' },
            },
        });

        await wrapper
            .get('[data-testid="cockpit-rider-library-open"]')
            .trigger('click');

        const recentArticle = Array.from(
            document.querySelectorAll<HTMLElement>(
                '[data-testid="cockpit-rider-library-entry"]',
            ),
        ).find((article) => article.textContent?.includes('Documentation'));
        const buttons = recentArticle?.querySelectorAll('button');

        buttons?.[0]?.click();
        buttons?.[1]?.click();

        expect(patch).toHaveBeenCalledWith(
            {
                url: '/x/cockpit/rider-library/01RECENT/pin',
                method: 'patch',
            },
            { pinned: true },
            expect.objectContaining({ only: ['rider_library'] }),
        );
        expect(destroy).toHaveBeenCalledWith(
            {
                url: '/x/cockpit/rider-library/01RECENT',
                method: 'delete',
            },
            expect.objectContaining({ only: ['rider_library'] }),
        );
    });

    it('keeps Save disabled until the editor has reusable content', () => {
        const wrapper = mount(CockpitRiderLibrary, {
            props: {
                kind: 'splash',
                entries: [],
                currentPayload: { splash: '', format: 'plain' },
            },
        });

        expect(
            wrapper.get<HTMLButtonElement>(
                '[data-testid="cockpit-rider-library-save-open"]',
            ).element.disabled,
        ).toBe(true);
    });
});
