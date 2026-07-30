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

describe('Cockpit campaign worksheets', () => {
    it('presents only aggregate draft facts until beneficiaries are added', () => {
        const wrapper = mount(Campaigns, {
            props: {
                worksheets: [worksheet],
            },
        });

        expect(wrapper.text()).toContain('Campaign Activity');
        expect(wrapper.text()).toContain('July Payroll');
        expect(wrapper.text()).toContain('0');
        expect(wrapper.text()).toContain('₱0.00');
        expect(wrapper.text()).toContain('Draft only');
        expect(wrapper.text()).not.toContain('Maria Santos');
        expect(wrapper.text()).toContain('Import Beneficiary List');
        expect(wrapper.text()).toContain('Drop A File Or Paste Rows');
        expect(wrapper.text()).toContain('Choose CSV Or Excel');
        expect(
            wrapper
                .find('[data-testid="campaign-import-drop-zone"]')
                .attributes('role'),
        ).toBe('group');
        expect(
            wrapper
                .get(
                    `[data-testid="campaign-activity-create-approval-${worksheet.reference}"]`,
                )
                .attributes('disabled'),
        ).toBeDefined();
        expect(wrapper.text()).toContain('Start Blank');
    });

    it('creates an Approval Pay Code from Campaign Activity through the existing authorization route', async () => {
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
        await action.trigger('click');

        expect(post).toHaveBeenCalledOnce();
        expect(post.mock.calls[0][0]).toMatchObject({
            method: 'post',
            url: `/x/cockpit/campaigns/${worksheet.reference}/authorizations`,
        });
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
        expect(intakeForm.file?.name).toBe('pasted-beneficiaries.csv');
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
        expect(intakeForm.file?.name).toBe('pasted-beneficiaries.csv');
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
        expect(component.intakeForm.file?.name).toBe(
            'pasted-beneficiaries.csv',
        );

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

        expect(wrapper.text()).toContain('Pay Code Experience');
        expect(wrapper.text()).toContain('Applies To All 2');
        expect(wrapper.text()).toContain(
            'Amount and recipient come from each worksheet row.',
        );
        expect(wrapper.text()).toContain('₱125.00');
        expect(
            wrapper
                .find('[data-testid="campaign-pay-code-experience"]')
                .exists(),
        ).toBe(true);

        await wrapper.get('input[maxlength="5000"]').setValue('July salary');
        const save = wrapper
            .findAll('button')
            .find((button) => button.text().includes('Save Experience'));
        expect(save).toBeDefined();
        await save?.trigger('click');

        expect(form.blueprint.rider.message).toBe('July salary');
        expect(put).toHaveBeenCalledOnce();
        expect(put.mock.calls[0][0]).toBe(
            `/x/cockpit/campaigns/${worksheet.reference}/voucher-blueprint`,
        );
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
        expect(wrapper.text()).toContain('Import Beneficiary List');
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
        expect(page).toContain('Authorized Beneficiaries');
        expect(page).toContain('<CockpitCampaignWorksheetBeneficiaries');
        expect(page).toContain("'Authorized Worksheet'");
        expect(page).not.toContain('Private Worksheet');
        expect(page).toContain(
            'data-testid="campaign-create-approval-pay-code"',
        );
        expect(page).toContain('Ready to lock for officer authorization.');
        expect(page.match(/Create Approval Pay Code/g)).toHaveLength(1);
        expect(
            page.indexOf('data-testid="campaign-create-approval-pay-code"'),
        ).toBeLessThan(page.indexOf('<CockpitCampaignImportWorkspace'));
        expect(
            page.indexOf('<CockpitCampaignWorksheetBeneficiaries'),
        ).toBeLessThan(page.indexOf('<CockpitCampaignImportWorkspace'));
        expect(page).toContain('v-if="isDraft()"');
        expect(page).toContain('Pay Codes Issued');
        expect(page).toContain('Direct Bank Transfer Is Not Enabled');
        expect(page).toContain('props.direct_bank_transfer_enabled');
        expect(page).toContain('Beneficiaries Ready To Issue');
        expect(page).toContain(
            'No Pay Codes, delivery, or bank transfers have started.',
        );
        expect(page).toContain('v-if="plannedCount() > 0"');
        expect(page).toContain('data-testid="campaign-delivery-controls"');
        expect(page).toContain('Issuance never sends messages.');
        expect(page).toContain('Download CSV');
        expect(page).toContain('SMS Disabled');
        expect(page).toContain('Email Disabled');
        expect(page).toContain('data-testid="campaign-approval-delivery"');
        expect(page).toContain('Send To Officer');
        expect(page).toContain('must sign in and review it.');
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
            'Two columns are enough: Mobile and Amount.',
        );
        expect(workspace).toContain('Update Mapping');
        expect(workspace).toContain('Needs Attention');
        expect(workspace).toContain(
            'Add {{ activeImport.unapplied_valid_count }} Valid Beneficiaries',
        );
        expect(workspace).toContain('Invalid rows stay staged for correction.');
        expect(workspace).toContain('router.delete(imports.destroy');
        expect(workspace).toContain('xl:grid-cols-[18rem_minmax(0,1fr)]');
        expect(workspace).toContain('dark:bg-slate-900');
    });
});
