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
        value: 'Use the Quick Generate form',
        helper: 'Pricing and funding preflights run after a successful form submit.',
    },
    {
        key: 'recipient',
        label: 'Recipient',
        value: 'Use the Quick Generate form',
        helper: 'Recipient reference is submitted through the existing issuance handoff.',
    },
    {
        key: 'purpose',
        label: 'Purpose',
        value: 'Optional form note',
        helper: 'Purpose/message is passed as operator-safe issuance context.',
    },
];

export const cockpitPricingFundingSummary: CockpitPricingFundingSummary[] = [
    {
        key: 'pricing',
        label: 'Pricing Estimate',
        value: 'Shown after submit',
        helper: 'The result panel displays the operator-safe pricing preflight returned by the existing runtime.',
    },
    {
        key: 'funding',
        label: 'Funding Impact',
        value: 'Shown after submit',
        helper: 'The result panel displays the operator-safe funding preflight; reservation and money movement remain behind existing issuance services.',
    },
    {
        key: 'execution',
        label: 'Execution Summary',
        value: 'Existing handoff',
        helper: 'Quick Generate compiles a draft and hands off to GeneratePayCode; execution semantics stay voucher-owned.',
    },
];
