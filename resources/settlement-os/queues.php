<?php

declare(strict_types=1);

use LBHurtado\XChange\Jobs\Campaigns\AdvanceCampaignPaymentLifecycleJob;
use LBHurtado\XChange\Jobs\Campaigns\CompleteAutomaticDemonstrationPolicyJob;
use LBHurtado\XChange\Jobs\Campaigns\ConvergeCampaignFeedbackDeliveryJob;
use LBHurtado\XChange\Jobs\Campaigns\DispatchCampaignFeedbackJob;
use LBHurtado\XChange\Jobs\Campaigns\SendDemonstrationPolicySummaryJob;
use LBHurtado\XChange\Jobs\Commercial\ReconcilePartnerCommissionPayoutBatchJob;
use LBHurtado\XChange\Jobs\Feedback\DeliverQueuedFeedbackSmsJob;
use LBHurtado\XChange\Jobs\Funding\PayApprovedFundingRequestJob;
use LBHurtado\XChange\Jobs\Funding\ResumeOnDemandPayCodeIssuanceJob;
use LBHurtado\XChange\Jobs\Funding\SyncStandingFundingAddressJob;
use LBHurtado\XChange\Jobs\Funding\VerifyFundingIntentJob;
use LBHurtado\XChange\Jobs\Funding\VerifyFundingWebhookReceiptJob;
use LBHurtado\XChange\Jobs\Payment\DeliverPartnerPaymentEvent;
use LBHurtado\XChange\Jobs\Payment\MonitorPaymentAttemptJob;
use LBHurtado\XChange\Jobs\Payment\VerifyPaymentAttemptJob;
use LBHurtado\XChange\Jobs\Provisioning\DeliverProvisioningOfferJob;
use LBHurtado\XChange\Jobs\Redemption\DispatchVoucherRedemptionFeedbackJob;

return [
    'schema' => 'settlement-os.queue-topology.v1',
    'package' => '3neti/x-change',
    'lanes' => [
        'funding' => [
            'queue' => 'x-change-funding',
            'criticality' => 'financial',
            'ordering' => 'subject-serialized',
            'idempotency_required' => true,
            'durable_recovery_required' => true,
            'explicit_commissioning' => true,
            'recommended' => [
                'timeout' => 60,
                'tries' => 1,
                'backoff' => [30, 120, 300, 900],
                'max_processes' => 1,
            ],
            'tags' => ['package:x-change', 'lane:funding'],
            'jobs' => [
                AdvanceCampaignPaymentLifecycleJob::class => [],
                ReconcilePartnerCommissionPayoutBatchJob::class => [
                    'configuration_key' => 'x-change.commercial.operations.queue',
                ],
                PayApprovedFundingRequestJob::class => [],
                SyncStandingFundingAddressJob::class => [],
                VerifyFundingIntentJob::class => [],
                VerifyFundingWebhookReceiptJob::class => [],
                MonitorPaymentAttemptJob::class => [],
                VerifyPaymentAttemptJob::class => [],
            ],
        ],
        'feedback' => [
            'queue' => 'x-change-feedback',
            'criticality' => 'communication',
            'ordering' => 'independent',
            'idempotency_required' => true,
            'durable_recovery_required' => true,
            'explicit_commissioning' => false,
            'recommended' => [
                'timeout' => 60,
                'tries' => 3,
                'backoff' => [10, 60, 300],
                'max_processes' => 2,
            ],
            'tags' => ['package:x-change', 'lane:feedback'],
            'jobs' => [
                CompleteAutomaticDemonstrationPolicyJob::class => [
                    'configuration_key' => 'x-change.redemption.feedback.queue',
                ],
                ConvergeCampaignFeedbackDeliveryJob::class => [],
                DispatchCampaignFeedbackJob::class => [],
                SendDemonstrationPolicySummaryJob::class => [
                    'configuration_key' => 'x-change.redemption.feedback.queue',
                ],
                DeliverQueuedFeedbackSmsJob::class => [
                    'configuration_key' => 'x-change.redemption.feedback.queue',
                ],
                DeliverProvisioningOfferJob::class => [],
                DispatchVoucherRedemptionFeedbackJob::class => [
                    'configuration_key' => 'x-change.redemption.feedback.queue',
                ],
            ],
        ],
        'partner-payments' => [
            'queue' => 'partner-payments',
            'criticality' => 'integration',
            'ordering' => 'subject-serialized',
            'idempotency_required' => true,
            'durable_recovery_required' => true,
            'explicit_commissioning' => true,
            'recommended' => [
                'timeout' => 60,
                'tries' => 5,
                'backoff' => [30, 120, 300, 900],
                'max_processes' => 1,
            ],
            'tags' => ['package:x-change', 'lane:partner-payments'],
            'jobs' => [
                DeliverPartnerPaymentEvent::class => [
                    'configuration_key' => 'x-change.partner_api.payment_events.queue',
                ],
            ],
        ],
        'legacy-default-issuance' => [
            'queue' => 'default',
            'criticality' => 'financial',
            'ordering' => 'subject-serialized',
            'idempotency_required' => true,
            'durable_recovery_required' => true,
            'explicit_commissioning' => true,
            'status' => 'legacy',
            'migration_target' => 'x-change-issuance',
            'recommended' => [
                'timeout' => 60,
                'tries' => 5,
                'backoff' => [5, 15, 45, 120],
                'max_processes' => 1,
            ],
            'tags' => ['package:x-change', 'lane:legacy-default-issuance'],
            'jobs' => [
                ResumeOnDemandPayCodeIssuanceJob::class => [],
            ],
        ],
    ],
];
