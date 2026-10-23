import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import CockpitQuickGenerateSubmitPanel from '../../../resources/js/cockpit/components/CockpitQuickGenerateSubmitPanel.vue';
import { cockpitQuickGenerateTemplates } from '../../../resources/js/cockpit/quickGenerateDefaults';
import requirementContract from '../../Fixtures/claim-requirement-contract.json';

vi.mock('@inertiajs/vue3', () => ({
    Link: { props: ['href'], template: '<a :href="href?.url ?? href"><slot /></a>' },
    router: { reload: vi.fn() },
    usePage: vi.fn(() => ({ props: {} })),
}));

function editor() {
    return mount(CockpitQuickGenerateSubmitPanel, {
        props: { templates: cockpitQuickGenerateTemplates, showEngineeringPreview: true },
        global: { stubs: { Teleport: true, CockpitPayCodeCanvas: true } },
    });
}

function preview(wrapper: ReturnType<typeof editor>): Record<string, any> {
    return JSON.parse(wrapper.get('[data-testid="cockpit-quick-generate-engineering-preview-json"]').text());
}

describe('Claim Experience requirement contract', () => {
    it('accounts for every selectable requirement in the shared claim contract', () => {
        const wrapper = editor();
        const controls = wrapper.get('[data-testid="cockpit-quick-generate-input-fields"]')
            .findAll('input[type="checkbox"]')
            .map((input) => (input.element as HTMLInputElement).value);

        expect(controls.sort()).toEqual(requirementContract.map(({ requirement }) => requirement).sort());
        expect(new Set(controls).size).toBe(controls.length);
    });

    it.each(requirementContract)('writes $requirement to the Engineering Preview instructions', async ({ requirement }) => {
        const wrapper = editor();
        const input = wrapper.get(`[data-testid="cockpit-quick-generate-input-fields"] input[value="${requirement}"]`);

        if (!(input.element as HTMLInputElement).checked) {
            await input.setValue(true);
        }

        expect(preview(wrapper).inputs.fields).toContain(requirement);
    });

    it.each([
        { label: 'KYC', requirement: 'kyc' },
        { label: 'Selfie', requirement: 'selfie' },
        { label: 'Require Signature', requirement: 'signature' },
    ])('keeps the $label proof control and claim instruction in sync', async ({ label, requirement }) => {
        const wrapper = editor();
        const proof = wrapper.get('[data-testid="cockpit-quick-generate-evidence-required-group"]')
            .findAll('label')
            .find((candidate) => candidate.text().includes(label));

        expect(proof).toBeDefined();
        await proof!.get('input[type="checkbox"]').setValue(true);
        expect(preview(wrapper).inputs.fields).toContain(requirement);
        expect((wrapper.get(`[data-testid="cockpit-quick-generate-input-fields"] input[value="${requirement}"]`).element as HTMLInputElement).checked).toBe(true);
        if (requirement === 'signature') {
            expect(preview(wrapper).validation.signature.required).toBe(true);
        } else {
            expect(preview(wrapper).inputs.requirements).toContain(requirement);
        }

        await proof!.get('input[type="checkbox"]').setValue(false);
        expect(preview(wrapper).inputs.fields).not.toContain(requirement);
        if (requirement === 'signature') {
            expect(preview(wrapper).validation.signature).toBeUndefined();
        } else {
            expect(preview(wrapper).inputs.requirements).not.toContain(requirement);
        }
    });

    it('adds a KYC claim step when face match is required', async () => {
        const wrapper = editor();

        await wrapper.get('[data-testid="cockpit-quick-generate-face-match-required"]').setValue(true);

        expect(preview(wrapper).validation.face_match.required).toBe(true);
        expect(preview(wrapper).inputs.fields).toContain('kyc');
        expect(preview(wrapper).inputs.requirements).toContain('kyc');
    });
});
