import { mount } from '@vue/test-utils';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { describe, expect, it, vi } from 'vitest';
import Campaigns from '../../../resources/js/cockpit/pages/Campaigns.vue';
import CockpitCampaignWorksheetBeneficiaries from '../../../resources/js/cockpit/components/CockpitCampaignWorksheetBeneficiaries.vue';
import CockpitCampaignPayCodeExperience from '../../../resources/js/cockpit/components/CockpitCampaignPayCodeExperience.vue';
import CampaignsRouteAdapter from '../../../resources/js/pages/x-change/cockpit/Campaigns.vue';

const worksheet = {
    reference: '01KYCAMPAIGNWORKSHEET000000',
    profile: 'payroll' as const,
    name: 'July Payroll',
    currency: 'PHP',
    status: 'draft',
    fulfillment_mode: 'pay_code_distribution',
    delivery_plan: ['csv'],
    beneficiary_count: 0,
    principal_minor: 0,
    updated_at: '2026-07-29T12:00:00+08:00',
};

const usageProfiles = [
    {
        key: 'lead',
        label: 'Lead',
        description:
            'Public QR or link that starts a reusable intake or offer.',
        entry_point: 'public_qr_link',
        person_type: 'prospect',
        pay_code_generation: 'on_scan',
        default_capabilities: ['public_endpoint', 'claim_intake'],
    },
    {
        key: 'collection',
        label: 'Collection',
        description: 'Public payment or stored-value endpoint for payers.',
        entry_point: 'public_qr_link',
        person_type: 'payer',
        pay_code_generation: 'on_invoice_or_scan',
        default_capabilities: ['public_endpoint', 'collection'],
    },
];

const endpointCapabilities = [
    { key: 'public_endpoint', label: 'Public QR/link endpoint' },
    { key: 'claim_intake', label: 'Collect claim/intake details' },
    { key: 'collection', label: 'Collection/payment request' },
];

const payCodeTemplate = {
    id: 12,
    reference: '01KYTEMPLATE0000000000000',
    name: 'Insurance application template',
    description: 'Applicant intake',
    amount_minor: 10000,
    currency: 'PHP',
    flow_type: 'disbursable',
    input_fields: ['name', 'mobile'],
};

const endpointCampaign = {
    reference: '01KYENDPOINT0000000000000',
    title: 'Insurance Application',
    description: 'Public acquisition link',
    status: 'active',
    merchant_display_name: 'AUI Insurance',
    merchant_slug: 'aui-insurance',
    endpoint_slug: 'application',
    created_at: '2026-07-29T10:00:00+08:00',
    updated_at: '2026-07-29T12:00:00+08:00',
    public_url: 'https://example.test/x/o/aui-insurance/application',
    qr_data_uri: 'data:image/png;base64,abc123',
    usage_count: 3,
    starts_limit: 25,
    last_started_at: '2026-07-29T12:00:00+08:00',
    expires_at: null,
    usage_key: 'lead',
    usage_label: 'Lead',
    capabilities: ['public_endpoint', 'claim_intake'],
    availability: {},
    availability_state: {
        key: 'open',
        label: 'Open',
        reason: 'Accepting new starts.',
    },
    limits: {},
    entry_mode: 'pay_code_on_open' as const,
    payment_qr: null,
    creator: {
        name: 'Lester Hurtado',
        type: 'user',
    },
    progress: {
        started: 3,
        completed: 1,
        in_progress: 2,
        source: 'display_sessions',
    },
    actions: {
        template_update_url:
            '/x/cockpit/campaigns/endpoints/01KYENDPOINT0000000000000/template',
        pause_url:
            '/x/cockpit/campaigns/endpoints/01KYENDPOINT0000000000000/pause',
        resume_url:
            '/x/cockpit/campaigns/endpoints/01KYENDPOINT0000000000000/resume',
        payment_qr_provision_url:
            '/x/cockpit/campaigns/endpoints/01KYENDPOINT0000000000000/payment-qr',
    },
    template: {
        id: 12,
        reference: '01KYTEMPLATE0000000000000',
        name: 'Insurance application template',
        version_id: 'pctv_original',
        amount_minor: 10000,
        currency: 'PHP',
    },
};

describe('Cockpit campaign worksheets', () => {
    it('shows payment first milestones instead of endpoint starts and links to campaign scoped activity', async () => {
        const campaign = {
            ...endpointCampaign,
            usage_count: 0,
            entry_mode: 'reusable_payment_qr' as const,
            payment_qr: {
                reference: 'QR1',
                amount_mode: 'fixed' as const,
                fixed_amount_minor: 5000,
                currency: 'PHP',
                qr_data_uri: 'data:image/png;base64,QRPH',
                generated_at: null,
            },
            payment_progress: {
                payments_received: 7,
                received_amounts: [{ currency: 'PHP', amount_minor: 35000 }],
                details_submitted: 6,
                demo_summaries_ready: 6,
                awaiting_claim: 1,
                awaiting_invitation: 0,
            },
        };
        const wrapper = mount(Campaigns, {
            props: { worksheets: [], endpoint_campaigns: [campaign] },
        });
        await wrapper
            .get('[data-testid="campaign-flavor-endpoints"]')
            .trigger('click');
        const row = wrapper.get(
            `[data-testid="campaign-endpoint-card-${campaign.reference}"]`,
        );
        expect(row.text()).toContain('7 payments received');
        expect(row.text()).toContain('₱350.00 received');
        expect(row.text()).toContain(
            '6 details submitted · 6 demo summaries ready',
        );
        expect(row.text()).toContain('1 awaiting claim');
        expect(row.text()).toContain('₱50.00 per payment');
        expect(row.text()).toContain('Show Payment QR Ph');
        expect(row.text()).not.toContain('No principal');
        expect(
            row.find('[data-testid="campaign-endpoint-progress"]').exists(),
        ).toBe(false);
        expect(
            row
                .get(
                    `[data-testid="campaign-payment-activity-${campaign.reference}"]`,
                )
                .attributes('href'),
        ).toContain(`campaign=${campaign.reference}`);
        await wrapper.setProps({
            endpoint_campaigns: [{ ...campaign, payment_progress: null }],
        });
        expect(row.text()).toContain('Payment activity unavailable');
    });

    it('shows an owner campaign QR Ph stamp with enlargement, download, and print actions', async () => {
        const paymentCampaign = {
            ...endpointCampaign,
            entry_mode: 'reusable_payment_qr' as const,
            payment_qr: {
                reference: '01KYPAYMENTQR0000000000',
                amount_mode: 'fixed' as const,
                fixed_amount_minor: 5000,
                currency: 'PHP',
                qr_data_uri: 'data:image/png;base64,QRPH',
                generated_at: '2026-09-24T08:00:00+08:00',
            },
        };
        const wrapper = mount(Campaigns, {
            props: {
                worksheets: [],
                endpoint_campaigns: [paymentCampaign],
            },
        });

        await wrapper
            .get('[data-testid="campaign-flavor-endpoints"]')
            .trigger('click');
        await wrapper
            .get(
                `[data-testid="campaign-payment-qr-show-${paymentCampaign.reference}"]`,
            )
            .trigger('click');

        expect(
            wrapper.get('[data-testid="campaign-payment-qr-stamp"]').text(),
        ).toContain('Reusable payment QR Ph');
        expect(
            wrapper.get('[data-testid="campaign-payment-qr-stamp"]').text(),
        ).toContain('₱50.00');
        expect(
            wrapper
                .get('[data-testid="campaign-payment-qr-image"]')
                .attributes('src'),
        ).toBe(paymentCampaign.payment_qr.qr_data_uri);
        expect(
            wrapper
                .get('[data-testid="campaign-payment-qr-download"]')
                .attributes('download'),
        ).toContain('insurance-application-qr-ph.png');
        expect(
            wrapper.find('[data-testid="campaign-payment-qr-print"]').exists(),
        ).toBe(true);

        await wrapper
            .get('[data-testid="campaign-payment-qr-enlarge"]')
            .trigger('click');
        expect(
            wrapper
                .get('[data-testid="campaign-payment-qr-enlarge"]')
                .attributes('aria-expanded'),
        ).toBe('true');
    });

    it('surfaces aggregate payment evidence attention without exposing provider evidence', async () => {
        const campaignNeedingAttention = {
            ...endpointCampaign,
            payment_attention: {
                count: 2,
                status: 'needs_attention',
                label: 'Needs attention',
                latest_reason: 'incompatible_evidence',
                latest_opened_at: '2026-09-23T01:00:00Z',
            },
        };
        const wrapper = mount(Campaigns, {
            props: {
                worksheets: [],
                endpoint_campaigns: [campaignNeedingAttention],
            },
        });

        await wrapper
            .get('[data-testid="campaign-flavor-endpoints"]')
            .trigger('click');
        expect(
            wrapper
                .get('[data-testid="campaign-policy-lifecycle-link"]')
                .attributes('href'),
        ).toBe('/x/cockpit/campaigns/policy-lifecycle');

        const attention = wrapper.get(
            '[data-testid="campaign-payment-evidence-attention"]',
        );
        expect(attention.text()).toBe('Needs attention · 2');
        expect(wrapper.text()).not.toContain('provider_transaction');
        wrapper.unmount();
    });

    it('keeps identical campaign titles distinguishable and shares the selected endpoint', async () => {
        const second = {
            ...endpointCampaign,
            reference: 'SECOND',
            endpoint_slug: 'application-demo',
            public_url:
                'https://example.test/x/o/aui-insurance/application-demo',
            qr_data_uri: 'data:image/png;base64,SECOND',
        };
        const wrapper = mount(Campaigns, {
            attachTo: document.body,
            props: {
                worksheets: [],
                endpoint_campaigns: [endpointCampaign, second],
            },
        });
        await wrapper
            .get('[data-testid="campaign-flavor-endpoints"]')
            .trigger('click');
        expect(
            wrapper
                .get('[data-testid="campaign-endpoint-list"]')
                .attributes('role'),
        ).toBe('list');
        expect(wrapper.text()).toContain('/x/o/aui-insurance/application-demo');
        expect(wrapper.text()).not.toContain(
            'https://example.test/x/o/aui-insurance/application-demo',
        );
        expect(
            wrapper
                .get('[data-testid="campaign-endpoint-identifier"]')
                .attributes('title'),
        ).toBe(endpointCampaign.public_url);
        expect(wrapper.text()).toContain('2 campaigns');
        const share = wrapper.get(
            '[data-testid="campaign-endpoint-share-stamp-SECOND"]',
        );
        (share.element as HTMLButtonElement).focus();
        await share.trigger('click');
        expect(
            wrapper.get('[data-testid="campaign-endpoint-stamp-url"]').text(),
        ).toBe(second.public_url);
        await wrapper
            .get('[data-testid="campaign-endpoint-enlarge-qr"]')
            .trigger('click');
        expect(
            wrapper
                .get('[data-testid="campaign-endpoint-expanded-qr-image"]')
                .attributes('src'),
        ).toBe(second.qr_data_uri);
        expect(document.activeElement?.getAttribute('data-testid')).toBe(
            'campaign-endpoint-expanded-qr-restore',
        );
        await wrapper
            .get('[data-testid="campaign-endpoint-expanded-qr-restore"]')
            .trigger('click');
        expect(document.activeElement?.getAttribute('data-testid')).toBe(
            'campaign-endpoint-enlarge-qr',
        );
        await wrapper
            .get('[data-testid="campaign-endpoint-stamp-close"]')
            .trigger('click');
        expect(document.activeElement).toBe(share.element);
        wrapper.unmount();
    });

    it('presents only aggregate draft facts until beneficiaries are added', () => {
        const wrapper = mount(Campaigns, {
            props: {
                worksheets: [worksheet],
            },
        });

        expect(wrapper.text()).toContain('Payroll Payouts');
        expect(wrapper.text()).toContain(
            'Prepare and approve net-pay payouts for your team.',
        );
        expect(wrapper.text()).toContain('Runs');
        expect(wrapper.text()).toContain('Employees');
        expect(wrapper.text()).toContain('July Payroll');
        expect(wrapper.text()).toContain('0');
        expect(wrapper.text()).toContain('₱0.00');
        expect(wrapper.text()).toContain('Draft only');
        expect(wrapper.text()).not.toContain('Maria Santos');
        expect(wrapper.text()).toContain('Payroll Runs');
        expect(wrapper.text()).toContain('Continue');
        expect(wrapper.text()).toContain('Upload Payroll List');
        expect(wrapper.text()).toContain('Choose A File Or Paste Rows');
        expect(wrapper.text()).toContain('Choose File');
        expect(
            wrapper
                .find('[data-testid="campaign-import-drop-zone"]')
                .attributes('role'),
        ).toBe('group');
        expect(
            wrapper
                .find(
                    `[data-testid="campaign-activity-create-approval-${worksheet.reference}"]`,
                )
                .exists(),
        ).toBe(false);
        expect(wrapper.text()).toContain('Start Empty');
    });

    it('switches between Payroll and Ayuda using existing worksheet profiles', async () => {
        const assistanceWorksheet = {
            ...worksheet,
            reference: '01KYCAMPAIGNASSISTANCE00000',
            profile: 'assistance' as const,
            name: 'August Ayuda',
            beneficiary_count: 3,
            principal_minor: 30000,
        };
        const wrapper = mount(Campaigns, {
            props: {
                worksheets: [worksheet, assistanceWorksheet],
            },
        });

        expect(
            wrapper
                .get('[data-testid="campaign-flavor-payroll"]')
                .attributes('aria-pressed'),
        ).toBe('true');
        expect(wrapper.text()).toContain('July Payroll');
        expect(wrapper.text()).not.toContain('August Ayuda');

        await wrapper
            .get('[data-testid="campaign-flavor-ayuda"]')
            .trigger('click');

        expect(wrapper.text()).toContain('Ayuda Distribution');
        expect(wrapper.text()).toContain('Distribution Batches');
        expect(wrapper.text()).toContain('Beneficiaries');
        expect(wrapper.text()).toContain('Assistance');
        expect(wrapper.text()).toContain('August Ayuda');
        expect(wrapper.text()).not.toContain('July Payroll');
        expect(
            (wrapper.vm as unknown as { form: { profile: string } }).form
                .profile,
        ).toBe('assistance');
    });

    it('creates and monitors endpoint campaigns from Pay Code templates', async () => {
        const wrapper = mount(Campaigns, {
            props: {
                worksheets: [worksheet],
                campaign_usage_profiles: usageProfiles,
                endpoint_capabilities: endpointCapabilities,
                pay_code_templates: [payCodeTemplate],
                endpoint_campaigns: [endpointCampaign],
                endpoint_campaign_form: {
                    action_url: '/x/cockpit/campaigns/endpoints',
                    default_timezone: 'Asia/Manila',
                },
            },
        });

        await wrapper
            .get('[data-testid="campaign-flavor-endpoints"]')
            .trigger('click');

        expect(wrapper.text()).toContain('Endpoint Campaigns');
        expect(
            wrapper
                .get('[data-testid="campaign-endpoint-scenario-runner-link"]')
                .attributes('href'),
        ).toBe('/x/cockpit/campaigns/lead-scenario-runner');
        expect(wrapper.text()).toContain('Insurance Application');
        expect(wrapper.text()).toContain('/x/o/aui-insurance/application');
        expect(wrapper.text()).not.toContain(
            'https://example.test/x/o/aui-insurance/application',
        );
        expect(wrapper.text()).toContain('Insurance application template');
        expect(wrapper.text()).toContain('₱2,500.00 cap');
        expect(wrapper.text()).toContain(
            '3 started · 1 completed · 2 in progress',
        );
        expect(wrapper.text()).toContain('Created');
        expect(wrapper.text()).toContain('Lester Hurtado');
        expect(wrapper.text()).toContain('Open');
        expect(wrapper.text()).toContain('Show QR & Share');
        expect(wrapper.text()).toContain('Future starts template');
        expect(
            wrapper.find('[data-testid="campaign-endpoint-qr"]').exists(),
        ).toBe(false);

        await wrapper
            .get(
                `[data-testid="campaign-endpoint-share-stamp-${endpointCampaign.reference}"]`,
            )
            .trigger('click');

        const stamp = wrapper.get(
            `[data-testid="campaign-endpoint-stamp-modal-${endpointCampaign.reference}"]`,
        );

        expect(stamp.text()).toContain('Endpoint Campaign Stamp');
        expect(stamp.text()).toContain('AUI Insurance');
        expect(stamp.text()).toContain('Insurance Application');
        expect(stamp.text()).toContain(endpointCampaign.public_url);
        expect(stamp.text()).not.toContain('/x/claim/');
        expect(
            stamp
                .get('[data-testid="campaign-endpoint-stamp-qr"]')
                .attributes('src'),
        ).toBe(endpointCampaign.qr_data_uri);
        expect(
            stamp
                .get('[data-testid="campaign-endpoint-stamp-open"]')
                .attributes('href'),
        ).toBe(endpointCampaign.public_url);

        await stamp
            .get('[data-testid="campaign-endpoint-enlarge-qr"]')
            .trigger('click');
        expect(
            stamp
                .get('[data-testid="campaign-endpoint-expanded-qr-image"]')
                .attributes('src'),
        ).toBe(endpointCampaign.qr_data_uri);
        expect(
            stamp.find('[data-testid="campaign-endpoint-stamp-qr"]').exists(),
        ).toBe(false);
        await stamp
            .get('[data-testid="campaign-endpoint-expanded-qr-button"]')
            .trigger('click');
        expect(
            stamp.find('[data-testid="campaign-endpoint-stamp-qr"]').exists(),
        ).toBe(true);
        await stamp
            .get('[data-testid="campaign-endpoint-enlarge-qr"]')
            .trigger('click');
        await stamp
            .get('[data-testid="campaign-endpoint-expanded-qr-restore"]')
            .trigger('keydown', { key: 'Escape' });
        expect(
            wrapper
                .find('[data-testid="campaign-endpoint-stamp-overlay"]')
                .exists(),
        ).toBe(true);
        expect(
            stamp.find('[data-testid="campaign-endpoint-stamp-qr"]').exists(),
        ).toBe(true);
        await stamp
            .get('[data-testid="campaign-endpoint-enlarge-qr"]')
            .trigger('click');
        await stamp
            .get('[data-testid="campaign-endpoint-stamp-close"]')
            .trigger('click');
        await wrapper
            .get(
                `[data-testid="campaign-endpoint-share-stamp-${endpointCampaign.reference}"]`,
            )
            .trigger('click');
        expect(
            wrapper
                .find('[data-testid="campaign-endpoint-expanded-qr"]')
                .exists(),
        ).toBe(false);

        const endpointForm = (
            wrapper.vm as unknown as {
                endpointForm: {
                    title: string;
                    post: (...args: unknown[]) => void;
                };
            }
        ).endpointForm;
        const post = vi
            .spyOn(endpointForm, 'post')
            .mockImplementation(() => undefined);

        await wrapper
            .get('[data-testid="campaign-endpoint-create-form"]')
            .trigger('submit');

        expect(post).toHaveBeenCalledOnce();
        expect(post.mock.calls[0][0]).toBe('/x/cockpit/campaigns/endpoints');
    });

    it('updates only future endpoint starts to a newly selected template', async () => {
        const updatedTemplate = {
            ...payCodeTemplate,
            id: 25,
            reference: '01KYUPDATEDTEMPLATE0000000',
            name: 'Updated insurance payment template',
            amount_minor: 25000,
        };
        const confirm = vi
            .spyOn(window, 'confirm')
            .mockImplementation(() => true);
        const wrapper = mount(Campaigns, {
            props: {
                worksheets: [],
                pay_code_templates: [payCodeTemplate, updatedTemplate],
                endpoint_campaigns: [endpointCampaign],
            },
        });
        await wrapper
            .get('[data-testid="campaign-flavor-endpoints"]')
            .trigger('click');

        const endpointTemplateForm = (
            wrapper.vm as unknown as {
                endpointTemplateForm: {
                    patch: (...args: unknown[]) => void;
                    pay_code_template_id: number | null;
                };
            }
        ).endpointTemplateForm;
        const patch = vi
            .spyOn(endpointTemplateForm, 'patch')
            .mockImplementation(() => undefined);

        expect(
            wrapper
                .get(
                    `[data-testid="campaign-endpoint-update-template-${endpointCampaign.reference}"]`,
                )
                .attributes('disabled'),
        ).toBeDefined();

        await wrapper
            .get(
                `[data-testid="campaign-endpoint-template-select-${endpointCampaign.reference}"]`,
            )
            .setValue(String(updatedTemplate.id));
        await wrapper
            .get(
                `[data-testid="campaign-endpoint-update-template-${endpointCampaign.reference}"]`,
            )
            .trigger('click');

        expect(confirm).toHaveBeenCalledWith(
            expect.stringContaining('future starts'),
        );
        expect(endpointTemplateForm.pay_code_template_id).toBe(
            updatedTemplate.id,
        );
        expect(patch).toHaveBeenCalledWith(
            endpointCampaign.actions.template_update_url,
            expect.objectContaining({ preserveScroll: true }),
        );

        confirm.mockRestore();
    });

    it('pauses and resumes endpoint campaigns from the management row', async () => {
        const wrapper = mount(Campaigns, {
            props: {
                worksheets: [],
                endpoint_campaigns: [endpointCampaign],
            },
        });
        await wrapper
            .get('[data-testid="campaign-flavor-endpoints"]')
            .trigger('click');
        const statusForm = (
            wrapper.vm as unknown as {
                endpointStatusForm: {
                    patch: (...args: unknown[]) => void;
                };
            }
        ).endpointStatusForm;
        const patch = vi
            .spyOn(statusForm, 'patch')
            .mockImplementation(() => undefined);

        await wrapper
            .get(
                `[data-testid="campaign-endpoint-pause-${endpointCampaign.reference}"]`,
            )
            .trigger('click');

        expect(patch).toHaveBeenCalledWith(endpointCampaign.actions.pause_url, {
            preserveScroll: true,
        });

        const paused = {
            ...endpointCampaign,
            status: 'paused',
            availability_state: {
                key: 'paused',
                label: 'Paused',
                reason: 'New starts are paused.',
            },
        };
        await wrapper.setProps({ endpoint_campaigns: [paused] });
        await wrapper
            .get(
                `[data-testid="campaign-endpoint-resume-${endpointCampaign.reference}"]`,
            )
            .trigger('click');

        expect(patch).toHaveBeenLastCalledWith(
            endpointCampaign.actions.resume_url,
            { preserveScroll: true },
        );
    });

    it('keeps empty-state creation vocabulary aligned with the selected flavor', async () => {
        const wrapper = mount(Campaigns, {
            props: { worksheets: [] },
        });

        expect(wrapper.text()).toContain('No payroll runs yet');
        expect(wrapper.text()).toContain('Start Empty Payroll');

        await wrapper
            .get('[data-testid="campaign-flavor-ayuda"]')
            .trigger('click');

        expect(wrapper.text()).toContain('No ayuda batches yet');
        expect(wrapper.text()).toContain('Upload Beneficiary List');
        expect(wrapper.text()).toContain('Start Empty Distribution');
        expect(wrapper.text()).toContain('Create Distribution Batch');
    });

    it('shows batch updates as relative time with the exact instant available', () => {
        vi.useFakeTimers();
        vi.setSystemTime(new Date('2026-07-30T12:00:00+08:00'));

        const wrapper = mount(Campaigns, {
            props: {
                worksheets: [worksheet],
            },
        });
        const updatedAt = wrapper.get('time');

        expect(updatedAt.text()).toBe('yesterday');
        expect(updatedAt.attributes('datetime')).toBe(worksheet.updated_at);
        expect(updatedAt.attributes('title')).not.toBe('Unavailable');

        wrapper.unmount();
        vi.useRealTimers();
    });

    it('requests approval from Payroll Runs through the existing authorization route', async () => {
        const readyWorksheet = {
            ...worksheet,
            beneficiary_count: 2,
            principal_minor: 150000,
        };
        const wrapper = mount(Campaigns, {
            props: {
                worksheets: [readyWorksheet],
            },
        });
        const authorizationForm = (
            wrapper.vm as unknown as {
                authorizationForm: {
                    post: (...args: unknown[]) => void;
                };
            }
        ).authorizationForm;
        const post = vi
            .spyOn(authorizationForm, 'post')
            .mockImplementation(() => undefined);
        const action = wrapper.get(
            `[data-testid="campaign-activity-create-approval-${worksheet.reference}"]`,
        );

        expect(action.attributes('disabled')).toBeUndefined();
        expect(action.text()).toContain('Request Approval');
        await action.trigger('click');

        expect(post).toHaveBeenCalledOnce();
        expect(post.mock.calls[0][0]).toBe(
            `/x/cockpit/campaigns/${worksheet.reference}/authorizations`,
        );
    });

    it('gives beneficiary imports a drag target and rejects unsupported files locally', async () => {
        const wrapper = mount(Campaigns, {
            props: {
                worksheets: [],
            },
        });
        const dropZone = wrapper.get(
            '[data-testid="campaign-import-drop-zone"]',
        );

        await dropZone.trigger('dragenter');
        expect(dropZone.classes()).toContain('border-sky-500');

        await dropZone.trigger('dragleave');
        expect(dropZone.classes()).not.toContain('border-sky-500');

        await dropZone.trigger('drop', {
            dataTransfer: {
                files: [
                    new File(['not a worksheet'], 'beneficiaries.pdf', {
                        type: 'application/pdf',
                    }),
                ],
            },
        });

        expect(wrapper.text()).toContain('Choose a CSV or XLSX file.');
    });

    it('turns pasted or dragged CSV rows into an intake upload', async () => {
        const wrapper = mount(Campaigns, {
            props: {
                worksheets: [],
            },
        });
        const intakeForm = (
            wrapper.vm as unknown as {
                intakeForm: {
                    file: File | null;
                    post: (...args: unknown[]) => void;
                };
            }
        ).intakeForm;
        const post = vi
            .spyOn(intakeForm, 'post')
            .mockImplementation(() => undefined);
        const csv = [
            'name,bank,account number,amount',
            'Maria Santos,BDO,001234567890,1000.00',
            'Jose Cruz,GCash,09171234567,500.00',
        ].join('\n');

        await wrapper
            .get('[data-testid="campaign-import-drop-zone"]')
            .trigger('paste', {
                clipboardData: {
                    getData: vi.fn().mockReturnValue(csv),
                },
            });

        expect(post).toHaveBeenCalledOnce();
        expect(intakeForm.file).toBeInstanceOf(File);
        expect(intakeForm.file?.name).toBe('pasted-recipients.csv');
        expect(intakeForm.file?.type).toBe('text/csv');

        await wrapper
            .get('[data-testid="campaign-import-drop-zone"]')
            .trigger('drop', {
                dataTransfer: {
                    files: [],
                    getData: vi.fn().mockReturnValue(csv),
                },
            });

        expect(post).toHaveBeenCalledTimes(2);
        expect(intakeForm.file?.name).toBe('pasted-recipients.csv');
    });

    it('accepts beneficiary CSV pasted anywhere except editable controls', () => {
        const wrapper = mount(Campaigns, {
            props: {
                worksheets: [],
            },
        });
        const component = wrapper.vm as unknown as {
            intakeForm: {
                file: File | null;
                post: (...args: unknown[]) => void;
            };
            pasteIntakeFromPage: (event: ClipboardEvent) => void;
        };
        const post = vi
            .spyOn(component.intakeForm, 'post')
            .mockImplementation(() => undefined);
        const csv = [
            'name,bank,account number,amount',
            'Maria Santos,BDO,001234567890,1000.00',
        ].join('\n');
        const preventDefault = vi.fn();

        component.pasteIntakeFromPage({
            target: document.body,
            defaultPrevented: false,
            clipboardData: {
                getData: vi.fn().mockReturnValue(csv),
            },
            preventDefault,
        } as unknown as ClipboardEvent);

        expect(preventDefault).toHaveBeenCalledOnce();
        expect(post).toHaveBeenCalledOnce();
        expect(component.intakeForm.file?.name).toBe('pasted-recipients.csv');

        component.pasteIntakeFromPage({
            target: document.createElement('input'),
            defaultPrevented: false,
            clipboardData: {
                getData: vi.fn().mockReturnValue(csv),
            },
            preventDefault,
        } as unknown as ClipboardEvent);

        expect(post).toHaveBeenCalledOnce();
        wrapper.unmount();
    });

    it('opens an explicit intake review with suggested choices and row controls', () => {
        const wrapper = mount(Campaigns, {
            props: {
                worksheets: [],
                active_intake: {
                    reference: '01KYINTAKE',
                    source_name: 'july-payroll.csv',
                    source_format: 'csv',
                    source_headers: [
                        'name',
                        'bank',
                        'account number',
                        'amount',
                    ],
                    source_sheet: null,
                    row_count: 2,
                    mapping: {
                        name: 'name',
                        institution: 'bank',
                        bank_account: 'account number',
                        amount: 'amount',
                    },
                    suggestion: {
                        name: 'July Payroll',
                        profile: 'payroll',
                        profile_reason: 'The file name looks like payroll.',
                        fulfillment_mode: 'pay_code_distribution',
                        fulfillment_reason: 'Mobile columns were found.',
                        needs_fulfillment_choice: false,
                    },
                    valid_count: 1,
                    invalid_count: 1,
                    valid_principal_minor: 10_000,
                    valid_source_rows: [2],
                    rows: [
                        {
                            source_row: 2,
                            status: 'valid',
                            source: {
                                name: 'Maria',
                                bank: 'GCash',
                                'account number': '09173011987',
                                amount: '100.00',
                            },
                            normalized: {
                                beneficiary: {
                                    name: 'Maria',
                                    mobile: '09173011987',
                                },
                                amount_minor: 10_000,
                            },
                            errors: [],
                        },
                        {
                            source_row: 3,
                            status: 'invalid',
                            source: {
                                name: 'Missing',
                                bank: 'NetBank',
                                'account number': '',
                                amount: '50.00',
                            },
                            normalized: null,
                            errors: [
                                'A mobile number or email address is required.',
                            ],
                        },
                    ],
                },
            },
        });

        expect(
            wrapper.find('[data-testid="campaign-intake-dialog"]').exists(),
        ).toBe(true);
        expect(wrapper.text()).toContain('Review Before Adding');
        expect(wrapper.text()).toContain('How Recipients Receive Funds');
        expect(wrapper.text()).toContain('Create Campaign With 1 Row');
        expect(wrapper.text()).toContain(
            'Scroll sideways to inspect every imported column.',
        );
        expect(wrapper.text()).toContain('bank');
        expect(wrapper.text()).toContain('account number');
        expect(wrapper.text()).toContain('GCash');
        expect(wrapper.text()).toContain('09173011987');
        expect(
            wrapper
                .find('[data-testid="campaign-intake-source-table"]')
                .classes(),
        ).toContain('overflow-auto');
    });

    it('labels authorized activity accurately instead of calling it draft only', () => {
        const wrapper = mount(Campaigns, {
            props: {
                worksheets: [{ ...worksheet, status: 'authorized' }],
            },
        });

        expect(wrapper.text()).toContain('Authorized');
        expect(wrapper.text()).not.toContain('Draft only');
    });

    it('directs batches awaiting authorization to their approval workspace', () => {
        const wrapper = mount(Campaigns, {
            props: {
                worksheets: [
                    {
                        ...worksheet,
                        status: 'awaiting_authorization',
                        beneficiary_count: 2,
                    },
                ],
            },
        });

        expect(wrapper.text()).toContain('Awaiting Authorization');
        expect(wrapper.text()).toContain('View Approval');
        expect(wrapper.text()).not.toContain('View Results');
    });

    it('shows the bank and account number in the owner private worksheet when available', () => {
        const wrapper = mount(CockpitCampaignWorksheetBeneficiaries, {
            props: {
                draft: true,
                rows: [
                    {
                        reference: 'beneficiary-bank-01',
                        ordinal: 1,
                        beneficiary: {
                            name: 'Maria Santos',
                            institution: 'BDO',
                            bank_code: 'BNORPHMMXXX',
                            bank_account: '001234567890',
                        },
                        amount_minor: 100_000,
                        delivery_preference: 'manual',
                        status: 'draft',
                    },
                    {
                        reference: 'beneficiary-mobile-02',
                        ordinal: 2,
                        beneficiary: {
                            name: 'Jose Cruz',
                            mobile: '09171234567',
                        },
                        amount_minor: 50_000,
                        delivery_preference: 'sms',
                        status: 'draft',
                    },
                ],
            },
        });

        const bankDestination = wrapper.get(
            '[data-testid="campaign-worksheet-bank-destination-beneficiary-bank-01"]',
        );

        expect(bankDestination.text()).toContain('BDO');
        expect(bankDestination.text()).toContain('001234567890');
        const amount = wrapper.get(
            '[data-testid="campaign-worksheet-amount-beneficiary-bank-01"]',
        );
        expect(amount.text()).toBe('₱1,000.00');
        expect(amount.classes()).toContain('whitespace-nowrap');
        expect(
            wrapper
                .find(
                    '[data-testid="campaign-worksheet-bank-destination-beneficiary-mobile-02"]',
                )
                .exists(),
        ).toBe(false);
        expect(wrapper.text()).toContain('09171234567');
    });

    it('edits one shared Pay Code experience without creating beneficiary vouchers', async () => {
        const wrapper = mount(CockpitCampaignPayCodeExperience, {
            props: {
                worksheetReference: worksheet.reference,
                worksheetName: worksheet.name,
                fulfillmentMode: worksheet.fulfillment_mode,
                status: 'draft',
                currency: 'PHP',
                beneficiaryCount: 2,
                representativeAmountMinor: 12_500,
                representativeRecipient: '09173011987',
                blueprint: {},
                revision: 0,
            },
        });
        const form = (
            wrapper.vm as unknown as {
                form: {
                    put: (...args: unknown[]) => void;
                    blueprint: {
                        rider: { message: string };
                    };
                };
            }
        ).form;
        const put = vi.spyOn(form, 'put').mockImplementation(() => undefined);

        expect(wrapper.text()).toContain('Recipient Experience');
        expect(wrapper.text()).toContain('All 2 Recipients');
        expect(wrapper.text()).toContain(
            'Each recipient receives this experience with their own details.',
        );
        expect(wrapper.text()).toContain('₱125.00');
        expect(
            wrapper
                .find('[data-testid="campaign-pay-code-experience"]')
                .exists(),
        ).toBe(true);
        const experienceWorkspace = wrapper.get(
            '[data-testid="campaign-pay-code-experience-workspace"]',
        );
        expect(experienceWorkspace.classes()).toContain(
            '2xl:grid-cols-[minmax(0,0.95fr)_minmax(24rem,1.05fr)]',
        );
        expect(experienceWorkspace.classes()).not.toContain(
            'xl:grid-cols-[minmax(0,0.95fr)_minmax(24rem,1.05fr)]',
        );
        expect(
            wrapper
                .get('[data-testid="cockpit-pay-code-canvas-stamp-footer"]')
                .classes(),
        ).toEqual(
            expect.arrayContaining([
                'flex-col',
                '@sm:flex-row',
                '@sm:justify-between',
            ]),
        );

        await wrapper.get('input[maxlength="5000"]').setValue('July salary');
        const save = wrapper
            .findAll('button')
            .find((button) => button.text().includes('Save Changes'));
        expect(save).toBeDefined();
        await save?.trigger('click');

        expect(form.blueprint.rider.message).toBe('July salary');
        expect(put).toHaveBeenCalledOnce();
        expect(put.mock.calls[0][0]).toBe(
            `/x/cockpit/campaigns/${worksheet.reference}/voucher-blueprint`,
        );
    });

    it('keeps an unavailable saved service removable but blocks campaign approval readiness', async () => {
        const wrapper = mount(CockpitCampaignPayCodeExperience, {
            props: {
                worksheetReference: worksheet.reference,
                worksheetName: worksheet.name,
                fulfillmentMode: worksheet.fulfillment_mode,
                status: 'draft',
                currency: 'PHP',
                beneficiaryCount: 2,
                representativeAmountMinor: 12_500,
                representativeRecipient: '09173011987',
                blueprint: { inputs: { fields: ['location'] } },
                revision: 1,
                instructionCapabilities: {
                    location: {
                        key: 'location',
                        label: 'Location',
                        status: 'unavailable',
                        issuance_allowed: false,
                        claim_retryable: true,
                        reason: 'Location services are not configured.',
                        missing_configuration: ['OPENCAGE_API_KEY'],
                        source: 'form-handler-location',
                    },
                },
            },
        });

        const claimTab = wrapper
            .findAll('button')
            .find((button) => button.text().trim() === 'Claim');
        await claimTab?.trigger('click');

        const location = wrapper.get('input[value="location"]');
        expect(location.element).toMatchObject({
            checked: true,
            disabled: false,
        });
        expect(
            wrapper
                .get('[data-testid="campaign-instruction-capability-warning"]')
                .text(),
        ).toContain('Location services are not configured.');
        expect(
            wrapper
                .findAll('button')
                .find((button) => button.text().includes('Save Changes'))
                ?.attributes('disabled'),
        ).toBeDefined();
    });

    it('propagates account onboarding as a locked campaign-wide instruction', async () => {
        const wrapper = mount(CockpitCampaignPayCodeExperience, {
            props: {
                worksheetReference: worksheet.reference,
                worksheetName: worksheet.name,
                fulfillmentMode: worksheet.fulfillment_mode,
                status: 'draft',
                currency: 'PHP',
                beneficiaryCount: 2,
                representativeAmountMinor: 12_500,
                representativeRecipient: '',
                blueprint: {},
                revision: 0,
                onboardingOtpRequired: true,
            },
        });

        const claimTab = wrapper
            .findAll('button')
            .find((button) => button.text().trim() === 'Claim');

        expect(claimTab).toBeDefined();
        await claimTab?.trigger('click');
        await wrapper
            .get('[data-testid="campaign-onboarding-toggle"] input')
            .setValue(true);
        await wrapper.vm.$nextTick();

        const form = (
            wrapper.vm as unknown as {
                form: {
                    blueprint: {
                        onboarding: boolean;
                        inputs: { fields: string[] };
                        validation: {
                            otp: {
                                required: boolean;
                                on_failure: string;
                            };
                        };
                    };
                };
            }
        ).form;

        expect(form.blueprint.onboarding).toBe(true);
        expect(form.blueprint.inputs.fields).toEqual([
            'mobile',
            'name',
            'email',
            'otp',
        ]);
        expect(form.blueprint.validation.otp).toEqual({
            required: true,
            on_failure: 'block',
        });

        expect(form.blueprint).not.toHaveProperty('claim');
    });

    it('offers destructive deletion only for drafts and requires confirmation', async () => {
        const confirm = vi.spyOn(window, 'confirm').mockReturnValue(false);
        const wrapper = mount(Campaigns, {
            props: {
                worksheets: [
                    worksheet,
                    {
                        ...worksheet,
                        reference: 'authorized',
                        status: 'authorized',
                    },
                ],
            },
        });

        const deleteButtons = wrapper.findAll(
            'button[aria-label^="Delete draft"]',
        );
        expect(deleteButtons).toHaveLength(1);
        await deleteButtons[0].trigger('click');
        expect(confirm).toHaveBeenCalledWith(
            'Delete “July Payroll”? Its draft beneficiaries and staged imports will be permanently removed.',
        );

        confirm.mockRestore();
    });

    it('keeps the host Inertia adapter aligned with the package page', () => {
        const wrapper = mount(CampaignsRouteAdapter, {
            props: {
                worksheets: [worksheet],
            },
        });

        expect(
            wrapper.find('[data-testid="cockpit-campaigns-page"]').exists(),
        ).toBe(true);
        expect(wrapper.text()).toContain('Upload Payroll List');
    });

    it('makes the planned post-approval state explicit before beneficiary issuance', () => {
        const page = readFileSync(
            resolve(
                import.meta.dirname,
                '../../../resources/js/cockpit/pages/CampaignWorksheet.vue',
            ),
            'utf8',
        );

        expect(page).toContain('data-testid="campaign-fulfillment-readiness"');
        expect(page).toContain('<CockpitCampaignPayCodeExperience');
        expect(page).toContain('const peopleLabel = computed');
        expect(page).toContain(
            "isPayroll.value ? 'Employees' : 'Beneficiaries'",
        );
        expect(page).toContain(
            "isPayroll.value ? 'Net Payroll' : 'Assistance Total'",
        );
        expect(page).toContain('<CockpitCampaignWorksheetBeneficiaries');
        expect(page).toContain('const worksheetDisplayStatus = computed');
        expect(page).toContain("? 'Completed'");
        expect(page).not.toContain('Private Worksheet');
        expect(page).toContain(
            'data-testid="campaign-create-approval-pay-code"',
        );
        expect(page).toContain('ready for approval.');
        expect(page.match(/Request Approval/g)).toHaveLength(1);
        expect(
            page.indexOf('data-testid="campaign-create-approval-pay-code"'),
        ).toBeLessThan(page.indexOf('<CockpitCampaignImportWorkspace'));
        expect(
            page.indexOf('<CockpitCampaignWorksheetBeneficiaries'),
        ).toBeLessThan(page.indexOf('<CockpitCampaignImportWorkspace'));
        expect(page).toContain('v-if="isDraft()"');
        expect(page).toContain('Pay Codes Issued');
        expect(page).toContain('Campaign Complete');
        expect(page).toContain('completed_count');
        expect(page).toContain('personLabel.value} payments are');
        expect(page).toContain('Bank Transfers Unavailable');
        expect(page).toContain('props.direct_bank_transfer_enabled');
        expect(page).toContain('`${peopleLabel} Ready`');
        expect(page).toContain('ready for Pay Code issuance.');
        expect(page).toContain('v-if="plannedCount() > 0"');
        expect(page).toContain('data-testid="campaign-delivery-controls"');
        expect(page).toContain('Download the list or send through an enabled');
        expect(page).toContain('attempt.can_resend');
        expect(page).toContain('Confirm Resend');
        expect(page).toContain('deliveries.resends.store({');
        expect(page).toContain('Download CSV');
        expect(page).toContain('SMS Disabled');
        expect(page).toContain('Email Disabled');
        expect(page).toContain('data-testid="campaign-approval-delivery"');
        expect(page).toContain('Send To Officer');
        expect(page).toContain('must sign in to approve this batch.');
        expect(page).toContain('data-testid="campaign-worksheet-stats"');
        expect(page).toContain(
            'data-testid="campaign-browser-scenario-runner"',
        );
        expect(page).toContain('Live Payroll Runner');
        expect(page).toContain('I APPROVE LIVE BANK TRANSFERS');
        expect(page).toContain('data-testid="campaign-browser-runner-execute"');
        expect(page).toContain(
            'data-testid="campaign-browser-runner-live-confirmation"',
        );
        expect(page).toContain('data-testid="campaign-row-monitor-label"');
        expect(page).toContain('data-testid="campaign-row-monitor-detail"');
        expect(page).toContain('item.monitor_label');
        expect(page).toContain('Recovery Ready');
        expect(page).toContain('claim_status');
        expect(page).toContain('delivery_status');
        expect(page).toContain('min-[360px]:grid-cols-3');
        expect(page).toContain('2xl:min-w-[21rem]');
        expect(page).not.toContain(
            'grid grid-cols-3 gap-x-5 gap-y-2 text-left sm:text-right',
        );
        expect(page).toContain('2xl:grid-cols-[minmax(0,1fr)_24rem]');
        expect(page).not.toMatch(
            /(?:class="[^"]*\s|class=")xl:grid-cols-\[minmax\(0,1fr\)_24rem\]/,
        );
        expect(page).toContain('data-testid="campaign-add-recipient-panel"');
        expect(page).toContain('whitespace-normal');
        expect(page).toMatch(
            /requestedRelativeTime\(\s*attempt\.requested_at,?\s*\)/,
        );
        expect(page).toMatch(
            /:title="\s*formatAbsoluteTime\(\s*attempt\.requested_at,?\s*\)\s*"/,
        );
    });

    it('keeps imported beneficiaries staged behind explicit review controls', () => {
        const page = readFileSync(
            resolve(
                import.meta.dirname,
                '../../../resources/js/cockpit/pages/CampaignWorksheet.vue',
            ),
            'utf8',
        );
        const workspace = readFileSync(
            resolve(
                import.meta.dirname,
                '../../../resources/js/cockpit/components/CockpitCampaignImportWorkspace.vue',
            ),
            'utf8',
        );

        expect(page).toContain('<CockpitCampaignImportWorkspace');
        expect(page).toContain('hasPendingImportRows');
        expect(workspace).toContain('data-testid="campaign-import-workspace"');
        expect(workspace).toContain(
            'CSV or XLSX · Mobile and Amount are enough.',
        );
        expect(workspace).toContain('Update Mapping');
        expect(workspace).toContain('Needs Attention');
        expect(workspace).toContain('Valid');
        expect(workspace).toContain('Recipients');
        expect(workspace).toContain('Invalid rows stay staged for correction.');
        expect(workspace).toContain('router.delete(');
        expect(workspace).toContain('imports.destroy({');
        expect(workspace).toContain('xl:grid-cols-[18rem_minmax(0,1fr)]');
        expect(workspace).toContain('dark:bg-slate-900');
    });
});
