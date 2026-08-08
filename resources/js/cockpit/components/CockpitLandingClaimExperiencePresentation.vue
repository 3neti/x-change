<script setup lang="ts">
import type { CockpitClaimExperiencePreviewManifest } from '../types';
import CockpitClaimExperiencePreview from './CockpitClaimExperiencePreview.vue';

const claimWorkflow = {
    key: 'disbursement.v1',
    title: 'Disbursement Details',
    description: 'Provide the destination for this Pay Code.',
    requires_mobile: true,
    requires_destination: true,
    requires_amount: true,
    requires_authenticated_officer: false,
    authentication_mode: 'none',
    required_claim_fields: ['mobile'],
    confirmation_label: 'Confirm Redemption',
    skip_form_flow_splash: false,
    review: [],
};

const manifest: CockpitClaimExperiencePreviewManifest = {
    schema: 'x-change.claim-experience-preview.manifest.v1',
    status: 'ready',
    reference: 'landing-page-demo',
    fingerprint: 'landing-page-canonical-claim-v2',
    generated_at: '2026-08-08T00:00:00Z',
    cache_hit: true,
    safety: {
        preview_only: true,
        interactive: false,
        money_movement: false,
        provider_calls: false,
        claim_submission: false,
    },
    journey: {
        viewport: {
            profile: 'mobile_claim_v1',
            width: 360,
            height: 720,
        },
        step_count: 5,
        steps: [
            {
                sequence: 1,
                key: 'claim-entry',
                phase: 'entry',
                title: 'Claim entry',
                description: 'Recipient opens the Pay Code claim screen.',
                actor: 'redeemer',
                render_kind: 'live_screen',
                status: 'rendered',
                preview_url: '',
                frame: null,
                screen: {
                    kind: 'claim_entry',
                    code: 'DEMO-500',
                    amount: '₱500.00',
                    title: 'Claim Pay Code',
                    description: 'Enter the Pay Code shared with you.',
                    fields: [
                        {
                            key: 'code',
                            label: 'Pay Code',
                            value: 'DEMO-500',
                        },
                    ],
                    message: 'Your field allowance is ready.',
                },
            },
            {
                sequence: 2,
                key: 'form-flow-01-splash',
                phase: 'form_flow',
                title: 'Splash',
                description: 'Recipient sees the branded Pay Code handoff.',
                actor: 'redeemer',
                render_kind: 'actual_screen',
                status: 'rendered',
                preview_url: '',
                frame: null,
                screen: {
                    kind: 'form_flow_handler',
                    component: 'form-flow/core/Splash',
                    props: {
                        flow_id: 'landing-page-demo',
                        step_index: 0,
                        title: null,
                        timeout: 3,
                        button_label: 'Continue Now',
                        preview_mode: true,
                        is_default_splash: true,
                        content: '',
                        voucher_code: 'DEMO-500',
                        app_name: 'Pay Code',
                        app_logo:
                            '/vendor/x-change/images/pay-code/pay-code-logo.svg',
                        app_author: 'x-change',
                        copyright_text: 'A safe claim demonstration',
                    },
                },
            },
            {
                sequence: 3,
                key: 'form-flow-02-form',
                phase: 'form_flow',
                title: 'Disbursement Details',
                description: 'Provide the destination for this Pay Code.',
                actor: 'redeemer',
                render_kind: 'actual_screen',
                status: 'rendered',
                preview_url: '',
                frame: null,
                screen: {
                    kind: 'form_flow_handler',
                    component: 'form-flow/core/GenericForm',
                    props: {
                        flow_id: 'landing-page-demo',
                        step_index: 1,
                        step_name: 'wallet_info',
                        title: 'Disbursement Details',
                        description:
                            'Provide the destination for this Pay Code.',
                        fields: [
                            {
                                name: 'amount',
                                type: 'number',
                                label: 'Amount',
                                default: '500',
                                readonly: true,
                                required: true,
                                variant: 'readonly-badge',
                                available_balance: '500',
                            },
                            {
                                name: 'settlement_rail',
                                type: 'hidden',
                                default: 'INSTAPAY',
                            },
                            {
                                name: 'mobile',
                                type: 'tel',
                                label: 'Mobile Number',
                                default: '09173011987',
                                required: true,
                                group: 'redeemer',
                                persist: false,
                            },
                            {
                                name: 'bank_code',
                                type: 'bank_account',
                                label: 'Bank/Wallet',
                                default: 'GXCHPHM2XXX',
                                required: true,
                                group: 'endorsement',
                                persist: false,
                                help_text: 'INSTAPAY',
                            },
                            {
                                name: 'account_number',
                                type: 'text',
                                label: 'Account Number',
                                default: '09173011987',
                                required: true,
                                group: 'endorsement',
                                persist: false,
                            },
                        ],
                        auto_sync: {
                            enabled: false,
                        },
                        claim_workflow: claimWorkflow,
                        ui_variant: 'default',
                        preview_mode: true,
                    },
                },
            },
            {
                sequence: 4,
                key: 'confirmation',
                phase: 'review',
                title: 'Claim confirmation',
                description:
                    'Recipient reviews the payout details and confirms the claim.',
                actor: 'redeemer',
                render_kind: 'live_screen',
                status: 'rendered',
                preview_url: '',
                frame: null,
                screen: {
                    kind: 'confirmation',
                    code: 'DEMO-500',
                    amount: '₱500.00',
                    title: 'Confirm Claim',
                    description: 'Review and confirm your Pay Code claim.',
                    fields: [
                        {
                            key: 'mobile',
                            label: 'Mobile Number',
                            value: '0917 ••• •987',
                        },
                        {
                            key: 'destination',
                            label: 'Destination',
                            value: 'GCash · •1987',
                        },
                    ],
                    message: 'Your field allowance is ready.',
                },
            },
            {
                sequence: 5,
                key: 'claim-success-rider-message',
                phase: 'completion',
                title: 'Claim success',
                description:
                    'Recipient sees the completed claim state and issuer message.',
                actor: 'redeemer',
                render_kind: 'live_screen',
                status: 'rendered',
                preview_url: '',
                frame: null,
                screen: {
                    kind: 'success',
                    code: 'DEMO-500',
                    amount: '₱500.00',
                    title: 'Claim accepted',
                    description:
                        'The claim is recorded. Payout follows provider confirmation.',
                    fields: [],
                    message: 'Your field allowance is on its way.',
                },
            },
        ],
    },
    exports: {},
};
</script>

<template>
    <CockpitClaimExperiencePreview
        status="ready"
        :processing="false"
        message="Safe landing-page demonstration."
        :manifest="manifest"
        :stale="false"
        :can-generate="false"
        safe-presentation
        autoplay
        :autoplay-interval-ms="5200"
    />
</template>
