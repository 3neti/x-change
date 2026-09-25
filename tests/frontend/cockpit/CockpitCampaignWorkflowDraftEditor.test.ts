import { mount } from '@vue/test-utils';
import { reactive } from 'vue';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import Editor, {
    type CampaignWorkflowDraftCatalog,
} from '../../../resources/js/cockpit/components/CockpitCampaignWorkflowDraftEditor.vue';

const { post, forms } = vi.hoisted(() => ({
    post: vi.fn(),
    forms: [] as Array<
        Record<string, unknown> & {
            errors: Record<string, string>;
            processing: boolean;
        }
    >,
}));
vi.mock('@inertiajs/vue3', () => ({
    useForm: (data: Record<string, unknown>) => {
        const form = reactive({
            ...data,
            errors: {},
            processing: false,
            clearErrors() {
                this.errors = {};
            },
            reset: vi.fn(),
            post(url: string, options: unknown) {
                post(url, { ...this }, options);
            },
        });
        forms.push(form);
        return form;
    },
}));

const catalog: CampaignWorkflowDraftCatalog = {
    action_url: '/drafts',
    workflows: [
        {
            id: 'aui.personal-accident',
            version: '1.0.0',
            title: 'Personal Accident',
            service: 'AUI',
            entry_methods: ['payment_qr'],
            configured: true,
            requires_review: false,
            plans: [
                {
                    code: 'PA5000_DAY',
                    version: '1',
                    title: 'Personal Accident Day',
                    currency: 'PHP',
                    premium_minor: 5000,
                    benefit_minor: 500000,
                    coverage: { basis: 'payment', duration_days: 1 },
                },
            ],
            notifications: [
                {
                    event: 'payment_received',
                    sms: 'You purchased {plan_title}. {claim_url}',
                    placeholders: ['plan_title', 'claim_url'],
                },
            ],
            documents: [
                {
                    type: 'registration',
                    title: 'Vehicle registration',
                    max_size_mb: 10,
                },
            ],
            checklist: [
                {
                    key: 'registration',
                    label: 'Registration scan',
                    kind: 'document',
                    required: true,
                    review: 'required',
                },
                {
                    key: 'notes',
                    label: 'Additional notes',
                    kind: 'payload_field',
                    required: false,
                    review: 'none',
                },
            ],
            gates: ['intake'],
        },
        {
            id: 'philhealth.bst',
            version: '1.0.0',
            title: 'Benefit Settlement',
            service: 'PhilHealth',
            entry_methods: ['public_endpoint'],
            configured: false,
            requires_review: true,
            plans: [],
            notifications: [],
            documents: [],
            gates: ['review'],
        },
    ],
    drafts: [
        {
            reference: 'DRAFT1',
            name: 'My draft',
            updated_at: '2026-09-25T00:00:00Z',
            workflow_title: 'Personal Accident',
            plan_title: 'Personal Accident Day',
            entry_method: 'payment_qr',
        },
    ],
};

function render(value = catalog) {
    return mount(Editor, {
        props: {
            catalog: value,
            templates: [{ id: 1, name: 'Claim template' }],
        },
    });
}

it('matches the persisted template name limit', async () => {
    const wrapper = render();
    await wrapper.get('[data-testid="campaign-workflow-draft-open"]').trigger('click');
    expect(wrapper.get('[data-testid="workflow-draft-name"]').attributes('maxlength')).toBe('80');
});

beforeEach(() => {
    post.mockReset();
    forms.length = 0;
    HTMLDialogElement.prototype.showModal = vi.fn(function (
        this: HTMLDialogElement,
    ) {
        this.open = true;
    });
    HTMLDialogElement.prototype.close = vi.fn(function (
        this: HTMLDialogElement,
    ) {
        this.open = false;
    });
});

describe('campaign workflow draft editor', () => {
    function publishableCatalog(): CampaignWorkflowDraftCatalog {
        return {
            ...catalog,
            drafts: [
                {
                    ...catalog.drafts[0],
                    publish_url: '/drafts/DRAFT1/publish',
                    expected_snapshot_hash: 'exact-server-snapshot',
                },
            ],
        };
    }

    it('requires explicit confirmation and posts only the saved snapshot token to the server-provided route', async () => {
        const wrapper = render(publishableCatalog());
        await wrapper
            .get('[data-testid="workflow-draft-publish-open"]')
            .trigger('click');
        expect(post).not.toHaveBeenCalled();
        const dialog = wrapper.get(
            '[data-testid="workflow-draft-publication-dialog"]',
        );
        expect(dialog.attributes('aria-labelledby')).toBe(
            'workflow-publication-title',
        );
        expect(dialog.text()).toContain(
            'does not create a QR Ph, take a payment, or send SMS',
        );
        expect(dialog.text()).toContain("host's existing runtime gates");
        await wrapper
            .get('[data-testid="workflow-draft-publication-form"]')
            .trigger('submit');
        expect(post).toHaveBeenCalledOnce();
        expect(post.mock.calls[0][0]).toBe('/drafts/DRAFT1/publish');
        expect(post.mock.calls[0][1].expected_snapshot_hash).toBe(
            'exact-server-snapshot',
        );
        expect(post.mock.calls[0][1]).not.toHaveProperty('workflow_id');
        expect(post.mock.calls[0][1]).not.toHaveProperty('plan_code');
        expect(post.mock.calls[0][2]).toMatchObject({ preserveScroll: true });
        post.mock.calls[0][2].onSuccess();
        expect(HTMLDialogElement.prototype.close).toHaveBeenCalledOnce();
    });

    it('shows publication readiness errors and blocks repeated submission while processing', async () => {
        const wrapper = render(publishableCatalog());
        await wrapper
            .get('[data-testid="workflow-draft-publish-open"]')
            .trigger('click');
        const publication = forms.find(
            (form) => 'expected_snapshot_hash' in form,
        )!;
        publication.errors = { readiness: 'Private connection is not ready.' };
        publication.processing = true;
        await wrapper.vm.$nextTick();
        expect(
            wrapper
                .get(
                    '[data-testid="workflow-draft-publication-dialog"] [role="alert"]',
                )
                .text(),
        ).toContain('Private connection is not ready.');
        expect(
            wrapper
                .get('[data-testid="workflow-draft-publish-confirm"]')
                .attributes('disabled'),
        ).toBeDefined();
        await wrapper
            .get('[data-testid="workflow-draft-publication-form"]')
            .trigger('submit');
        expect(post).not.toHaveBeenCalled();
    });

    it('does not offer publication for unsupported or unauthorized drafts', () => {
        const wrapper = render({
            ...catalog,
            drafts: [
                {
                    ...catalog.drafts[0],
                    workflow_title: 'PhilHealth BST',
                    publish_url: null,
                    expected_snapshot_hash: 'bst-snapshot',
                },
            ],
        });
        expect(
            wrapper
                .find('[data-testid="workflow-draft-publish-open"]')
                .exists(),
        ).toBe(false);
        expect(
            wrapper
                .get('[data-testid="workflow-draft-publish-confirm"]')
                .attributes('disabled'),
        ).toBeDefined();
    });

    it('keeps draft creation separate from public links and opens an accessible dialog', async () => {
        const wrapper = render();
        expect(wrapper.text()).toContain('My draft · Draft');
        expect(wrapper.text()).toContain(
            'Drafts do not create a public link or take payments.',
        );
        await wrapper
            .get('[data-testid="campaign-workflow-draft-open"]')
            .trigger('click');
        expect(HTMLDialogElement.prototype.showModal).toHaveBeenCalledOnce();
        expect(wrapper.get('dialog').attributes('aria-labelledby')).toBe(
            'workflow-draft-title',
        );
        expect(wrapper.findAll('img')).toHaveLength(0);
        expect(
            wrapper
                .get('[data-testid="workflow-draft-save"]')
                .attributes('disabled'),
        ).toBeDefined();
    });

    it('selects only supported entries and submits exact version references with readonly terms', async () => {
        const wrapper = render();
        await wrapper
            .get('[data-testid="workflow-draft-name"]')
            .setValue('AUI draft');
        await wrapper
            .get('[data-testid="workflow-draft-template"]')
            .setValue('1');
        await wrapper
            .get('[data-testid="workflow-draft-service"]')
            .setValue('AUI');
        await wrapper
            .get('[data-testid="workflow-draft-workflow"]')
            .setValue('aui.personal-accident@1.0.0');
        expect(
            wrapper.get('[data-testid="workflow-draft-plan"]').text(),
        ).toContain('Personal Accident Day · 1');
        expect(
            wrapper.get('[data-testid="workflow-draft-entry"]').text(),
        ).not.toContain('public endpoint');
        await wrapper
            .get('[data-testid="workflow-draft-plan"]')
            .setValue('PA5000_DAY@1');
        await wrapper
            .get('[data-testid="workflow-draft-entry"]')
            .setValue('payment_qr');
        expect(wrapper.text()).toContain('₱50.00');
        expect(wrapper.text()).toContain('₱5,000.00');
        expect(wrapper.text()).toContain('Vehicle registration · Document');
        expect(
            wrapper.get('[data-testid="workflow-draft-checklist"]').text(),
        ).toContain(
            'Registration scan · Required · document · Review required',
        );
        expect(
            wrapper.get('[data-testid="workflow-draft-checklist"]').text(),
        ).toContain('Additional notes · Optional · payload field');
        expect(wrapper.text()).toContain(
            'You purchased {plan_title}. {claim_url}',
        );
        expect(
            wrapper
                .get('[data-testid="workflow-draft-preview"]')
                .findAll('input, textarea'),
        ).toHaveLength(0);
        await wrapper.get('form').trigger('submit');
        expect(post).toHaveBeenCalledOnce();
        expect(post.mock.calls[0][0]).toBe('/drafts');
        expect(post.mock.calls[0][1]).toMatchObject({
            name: 'AUI draft',
            pay_code_template_id: 1,
            workflow_id: 'aui.personal-accident',
            workflow_version: '1.0.0',
            plan_code: 'PA5000_DAY',
            plan_version: '1',
            entry_method: 'payment_qr',
        });
    });

    it('clears dependent selections when switching services and supports a workflow without a plan', async () => {
        const wrapper = render();
        await wrapper
            .get('[data-testid="workflow-draft-name"]')
            .setValue('BST draft');
        await wrapper
            .get('[data-testid="workflow-draft-template"]')
            .setValue('1');
        await wrapper
            .get('[data-testid="workflow-draft-service"]')
            .setValue('AUI');
        await wrapper
            .get('[data-testid="workflow-draft-workflow"]')
            .setValue('aui.personal-accident@1.0.0');
        await wrapper
            .get('[data-testid="workflow-draft-plan"]')
            .setValue('PA5000_DAY@1');
        await wrapper
            .get('[data-testid="workflow-draft-entry"]')
            .setValue('payment_qr');
        await wrapper
            .get('[data-testid="workflow-draft-service"]')
            .setValue('PhilHealth');
        expect(
            wrapper
                .get('[data-testid="workflow-draft-save"]')
                .attributes('disabled'),
        ).toBeDefined();
        expect(
            wrapper.find('[data-testid="workflow-draft-plan"]').exists(),
        ).toBe(false);
        await wrapper
            .get('[data-testid="workflow-draft-workflow"]')
            .setValue('philhealth.bst@1.0.0');
        await wrapper
            .get('[data-testid="workflow-draft-entry"]')
            .setValue('public_endpoint');
        expect(wrapper.text()).toContain('Reviewer approval required');
        expect(wrapper.text()).toContain('Private connection is not ready');
        await wrapper.get('form').trigger('submit');
        expect(post.mock.calls[0][1]).toMatchObject({
            workflow_id: 'philhealth.bst',
            plan_code: null,
            plan_version: null,
            entry_method: 'public_endpoint',
        });
    });

    it('fails closed when no workflows are authorized and never invents a service', async () => {
        const wrapper = render({ ...catalog, workflows: [] });
        expect(wrapper.text()).toContain(
            'No workflows are authorized for this account',
        );
        expect(
            wrapper
                .get('[data-testid="workflow-draft-service"]')
                .findAll('option'),
        ).toHaveLength(1);
        await wrapper.get('form').trigger('submit');
        expect(post).not.toHaveBeenCalled();
    });
});
