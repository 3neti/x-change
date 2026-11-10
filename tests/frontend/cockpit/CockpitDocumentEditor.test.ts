import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import CockpitDocumentEditor from '../../../resources/js/cockpit/components/CockpitDocumentEditor.vue';
import CockpitContributionInvitations from '../../../resources/js/cockpit/components/CockpitContributionInvitations.vue';

describe('document editor', () => {
    it('preserves document keys and other values when a label or format is edited', async () => {
        const wrapper = mount(CockpitDocumentEditor, { props: {
            title: 'Documents', mode: 'requirements',
            documents: [{ key: 'stable', label: 'Document', accept: ['application/pdf', 'image/png'], required: true }],
            formats: [{ label: 'PDF', types: ['application/pdf'] }, { label: 'Images', types: ['image/png'] }],
        } });
        await wrapper.get('[data-testid="claim-document-label-0"]').setValue('Invoice');
        expect(wrapper.emitted('update:documents')?.[0][0]).toEqual([{ key: 'stable', label: 'Invoice', accept: ['application/pdf', 'image/png'], required: true }]);
        await wrapper.get('fieldset input').setValue(false);
        expect(wrapper.emitted('update:documents')?.[1][0]).toEqual([{ key: 'stable', label: 'Document', accept: ['image/png'], required: true }]);
    });

    it('keeps invitation type selection and request payload scoped to allowed types', async () => {
        const fetchMock = vi.fn().mockResolvedValue({ ok: true, json: async () => ({ token: 'test-token', id: 1, expires_at: '2026-12-01', revoke_url: '/revoke' }) });
        vi.stubGlobal('fetch', fetchMock);
        try {
            const wrapper = mount(CockpitContributionInvitations, { props: { createUrl: '/invite', pageUrl: '/x/contribute', types: ['INVOICE', 'PROOF'], invitations: [] } });
            expect((wrapper.get('button[type="submit"]').element as HTMLButtonElement).disabled).toBe(true);
            await wrapper.get('input[type="checkbox"]').setValue(true);
            await wrapper.get('form').trigger('submit.prevent');
            expect(JSON.parse(fetchMock.mock.calls[0][1].body)).toEqual({ document_types: ['INVOICE'], expires_days: 7, password: null });
        } finally { vi.unstubAllGlobals(); }
    });
});
