import type {
    CockpitPricingFundingSummary,
    CockpitQuickGenerateTemplate,
    CockpitRuntimeInput,
} from './types';

export const cockpitQuickGenerateTemplates: CockpitQuickGenerateTemplate[] = [
    {
        key: 'money-changer',
        name: 'Money Changer',
        description: 'Fast cash-out Pay Code for branch counter operations.',
        profile: 'branch',
        estimatedTime: 'Under 5 seconds',
    },
    {
        key: 'ofw-remittance',
        name: 'OFW Remittance',
        description: 'Template-first remittance issuance with recipient details.',
        profile: 'operations',
        estimatedTime: 'Pending runtime inputs',
    },
    {
        key: 'settlement-envelope',
        name: 'Settlement Envelope',
        description: 'Complex settlement issuance remains deferred to later slices.',
        profile: 'settlement',
        estimatedTime: 'Deferred',
        disabled: true,
    },
];

export const cockpitRuntimeInputs: CockpitRuntimeInput[] = [
    {
        key: 'amount',
        label: 'Amount',
        value: 'Pending operator input',
        helper: 'No pricing or funding calculation is executed in Slice 3.',
    },
    {
        key: 'recipient',
        label: 'Recipient',
        value: 'Pending recipient selection',
        helper: 'Contact/package integration remains deferred.',
    },
    {
        key: 'purpose',
        label: 'Purpose',
        value: 'Pending purpose note',
        helper: 'Purpose is presentation context only in this baseline.',
    },
];

export const cockpitPricingFundingSummary: CockpitPricingFundingSummary[] = [
    {
        key: 'pricing',
        label: 'Pricing Estimate',
        value: 'Not calculated',
        helper: 'Will use existing pricing services only when explicitly wired.',
    },
    {
        key: 'funding',
        label: 'Funding Impact',
        value: 'Not reserved',
        helper: 'No wallet lookup, reservation, debit, or provider call occurs here.',
    },
    {
        key: 'execution',
        label: 'Execution Summary',
        value: 'Template pending',
        helper: 'Execution semantics stay voucher-owned and are not inferred in Vue.',
    },
];

