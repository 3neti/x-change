<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Configuration;

use LBHurtado\EmiCore\Contracts\DeploymentEnvironmentContributor;
use LBHurtado\EmiCore\Data\Configuration\EnvironmentVariableData;

final class CoreDeploymentEnvironmentContributor implements DeploymentEnvironmentContributor
{
    public function environmentVariables(): array
    {
        return [
            new EnvironmentVariableData(
                key: 'XCHANGE_DEPLOYMENT_PROFILE',
                description: 'Explicit provider deployment profile.',
                category: 'X-Change',
                configPath: 'x-change.deployment.profile',
                safeExample: 'development',
                required: true,
            ),
            new EnvironmentVariableData(
                key: 'XCHANGE_SYSTEM_USER_ID',
                description: 'Stable email of the non-interactive system principal.',
                category: 'Identity',
                configPath: 'x-change.payout.system_user_id',
                requiredForProfiles: ['netbank', 'paynamics', 'hybrid', 'custom'],
            ),
            new EnvironmentVariableData(
                key: 'XCHANGE_SYSTEM_USER_COLUMN',
                description: 'Stable lookup column used for the system principal.',
                category: 'Identity',
                configPath: 'x-change.payout.system_user_column',
                safeExample: 'email',
                requiredForProfiles: ['netbank', 'paynamics', 'hybrid', 'custom'],
            ),
            new EnvironmentVariableData(
                key: 'XCHANGE_TREASURY_LEGAL_ENTITY_REFERENCE',
                description: 'Stable legal-entity reference used by Treasury positions.',
                category: 'Treasury',
                configPath: 'x-change.treasury.legal_entity_reference',
                safeExample: 'legal-entity:example',
                requiredForProfiles: ['netbank', 'paynamics', 'hybrid', 'custom'],
            ),
            new EnvironmentVariableData(
                key: 'XCHANGE_TREASURY_LEGAL_PROFILE_VERSION',
                description: 'Approved legal vocabulary profile version.',
                category: 'Treasury',
                configPath: 'x-change.treasury.legal_profile_version',
                safeExample: '2026-01',
                requiredForProfiles: ['netbank', 'paynamics', 'hybrid', 'custom'],
            ),
            new EnvironmentVariableData(
                key: 'XCHANGE_REDEMPTION_FEEDBACK_QUEUE',
                description: 'Dedicated queue for settlement feedback delivery.',
                category: 'Delivery',
                configPath: 'x-change.redemption.feedback.queue',
                safeExample: 'x-change-feedback',
            ),
            new EnvironmentVariableData(
                key: 'QUEUE_CONNECTION',
                description: 'Laravel queue connection used by asynchronous workers.',
                category: 'Runtime',
                configPath: 'queue.default',
                safeExample: 'database',
            ),
            new EnvironmentVariableData(
                key: 'BROADCAST_CONNECTION',
                description: 'Laravel broadcast connection used for live Cockpit projections.',
                category: 'Runtime',
                configPath: 'broadcasting.default',
                safeExample: 'log',
            ),
            new EnvironmentVariableData(
                key: 'XCHANGE_FUNDING_BROADCAST_ENABLED',
                description: 'Broadcast confirmed funding changes to authenticated Cockpit clients.',
                category: 'Runtime',
                configPath: 'x-change.funding.broadcast_enabled',
                safeExample: 'false',
            ),
            new EnvironmentVariableData(
                key: 'XCHANGE_REDEMPTION_FEEDBACK_ENABLED',
                description: 'Enable explicit redemption feedback orchestration.',
                category: 'Delivery',
                configPath: 'x-change.redemption.feedback.enabled',
                safeExample: 'true',
            ),
            new EnvironmentVariableData(
                key: 'XCHANGE_REDEMPTION_FEEDBACK_WEBHOOK_SECRET',
                description: 'Shared secret for configured redemption feedback webhooks.',
                category: 'Delivery',
                configPath: 'x-change.redemption.feedback.webhook.secret',
                secret: true,
            ),
            new EnvironmentVariableData(
                key: 'X_FEEDBACK_SMS_DRIVER',
                description: 'x-feedback SMS transport driver.',
                category: 'Delivery',
                configPath: 'x-feedback.transports.sms.driver',
                safeExample: 'log',
            ),
            new EnvironmentVariableData(
                key: 'X_FEEDBACK_SMS_SENDER',
                description: 'Public SMS sender label assigned by the configured transport.',
                category: 'Delivery',
                configPath: 'x-feedback.transports.sms.sender',
                safeExample: 'XCHANGE',
            ),
            new EnvironmentVariableData(
                key: 'XCHANGE_CAMPAIGNS_SMS_DELIVERY_ENABLED',
                description: 'Permit explicit campaign SMS delivery actions.',
                category: 'Delivery',
                configPath: 'x-change.campaigns.delivery.sms.enabled',
                safeExample: 'false',
            ),
            new EnvironmentVariableData(
                key: 'XCHANGE_CAMPAIGNS_EMAIL_DELIVERY_ENABLED',
                description: 'Permit explicit campaign email delivery actions.',
                category: 'Delivery',
                configPath: 'x-change.campaigns.delivery.email.enabled',
                safeExample: 'false',
            ),
        ];
    }
}
