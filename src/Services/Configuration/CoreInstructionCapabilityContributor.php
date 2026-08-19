<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Configuration;

use LBHurtado\Voucher\Contracts\StoredValueExecutionGateway;
use LBHurtado\XChange\Contracts\Execution\DurableStoredValueExecutionGatewayContract;
use LBHurtado\XChange\Contracts\Execution\StoredValueDestinationAuthorityContract;
use LBHurtado\XChange\Contracts\InstructionCapabilityContributor;
use LBHurtado\XChange\Data\Configuration\InstructionCapabilityReadinessData;

final class CoreInstructionCapabilityContributor implements InstructionCapabilityContributor
{
    public function __construct(
        private readonly StoredValueExecutionGateway $storedValueGateway,
        private readonly StoredValueDestinationAuthorityContract $storedValueDestinationAuthority,
    ) {}

    public function instructionCapabilities(): iterable
    {
        yield $this->location();
        yield $this->kyc();
        yield $this->otp();
        yield $this->installedBrowserEvidence(
            key: 'selfie',
            label: 'Selfie',
            handler: 'LBHurtado\\FormHandlerSelfie\\SelfieHandler',
        );
        yield $this->installedBrowserEvidence(
            key: 'signature',
            label: 'Signature',
            handler: 'LBHurtado\\FormHandlerSignature\\SignatureHandler',
        );
        yield $this->smsFeedback();
        yield $this->emailFeedback();
        yield $this->webhookFeedback();
        yield $this->storedValue();
    }

    private function location(): InstructionCapabilityReadinessData
    {
        $missing = [];

        if (! $this->configured(config('location-handler.opencage_api_key'))) {
            $missing[] = 'OPENCAGE_API_KEY';
        }

        $provider = strtolower(trim((string) config('location-handler.map_provider', 'mapbox')));

        if ($provider === 'mapbox' && ! $this->configured(config('location-handler.mapbox_token'))) {
            $missing[] = 'MAPBOX_TOKEN';
        } elseif ($provider === 'google' && ! $this->configured(config('location-handler.google_maps_api_key'))) {
            $missing[] = 'GOOGLE_MAPS_API_KEY';
        } elseif (! in_array($provider, ['mapbox', 'google'], true)) {
            $missing[] = 'LOCATION_HANDLER_MAP_PROVIDER';
        }

        return $this->fromMissingConfiguration(
            key: 'location',
            label: 'Location',
            missing: $missing,
            unavailableReason: 'Location evidence is unavailable until reverse-geocoding and map services are configured.',
            source: 'form-handler-location',
        );
    }

    private function kyc(): InstructionCapabilityReadinessData
    {
        $fake = (bool) config('kyc-handler.use_fake', false);

        if ($fake && ! app()->environment('production')) {
            return new InstructionCapabilityReadinessData(
                key: 'kyc',
                label: 'KYC',
                status: 'simulation',
                issuanceAllowed: true,
                claimRetryable: true,
                reason: 'KYC is using non-production simulation evidence.',
                source: 'form-handler-kyc',
            );
        }

        $missing = [];

        foreach ([
            'HYPERVERGE_APP_ID' => config('kyc-handler.hyperverge.app_id'),
            'HYPERVERGE_APP_KEY' => config('kyc-handler.hyperverge.app_key'),
            'HYPERVERGE_URL_WORKFLOW' => config('kyc-handler.hyperverge.workflow'),
        ] as $key => $value) {
            if (! $this->configured($value)) {
                $missing[] = $key;
            }
        }

        return $this->fromMissingConfiguration(
            key: 'kyc',
            label: 'KYC',
            missing: $missing,
            unavailableReason: 'KYC is unavailable until the identity-verification service is configured.',
            source: 'form-handler-kyc',
        );
    }

    private function otp(): InstructionCapabilityReadinessData
    {
        $driver = strtolower(trim((string) config('otp-handler.driver', 'unavailable')));
        $missing = [];

        if ($driver !== 'txtcmdr') {
            $missing[] = 'OTP_HANDLER_DRIVER';
        }

        if (! $this->configured(config('otp-handler.txtcmdr.base_url'))) {
            $missing[] = 'TXTCMDR_API_URL';
        }

        if (! $this->configured(config('otp-handler.txtcmdr.api_token'))) {
            $missing[] = 'TXTCMDR_API_TOKEN';
        }

        return $this->fromMissingConfiguration(
            key: 'otp',
            label: 'OTP',
            missing: $missing,
            unavailableReason: 'OTP is unavailable until the identity challenge service is configured.',
            source: 'form-handler-otp',
        );
    }

    private function installedBrowserEvidence(
        string $key,
        string $label,
        string $handler,
    ): InstructionCapabilityReadinessData {
        $ready = class_exists($handler);

        return new InstructionCapabilityReadinessData(
            key: $key,
            label: $label,
            status: $ready ? 'ready' : 'unavailable',
            issuanceAllowed: $ready,
            claimRetryable: true,
            reason: $ready ? null : sprintf('%s evidence is unavailable because its handler is not installed.', $label),
            missingConfiguration: $ready ? [] : ['PACKAGE_NOT_INSTALLED'],
            source: sprintf('form-handler-%s', $key),
        );
    }

    private function smsFeedback(): InstructionCapabilityReadinessData
    {
        $missing = [];
        $driver = strtolower(trim((string) config('x-feedback.transports.sms.driver', '')));

        if (app()->environment('testing') && $driver !== '' && $driver !== 'null') {
            return new InstructionCapabilityReadinessData(
                key: 'feedback.sms',
                label: 'SMS Delivery',
                status: 'simulation',
                issuanceAllowed: true,
                claimRetryable: true,
                reason: 'SMS delivery is isolated by the test runtime.',
                source: 'x-feedback',
            );
        }

        if ($driver === 'log' && ! app()->environment('production')) {
            return new InstructionCapabilityReadinessData(
                key: 'feedback.sms',
                label: 'SMS Delivery',
                status: 'simulation',
                issuanceAllowed: true,
                claimRetryable: true,
                reason: 'SMS delivery is using the non-production log transport.',
                source: 'x-feedback',
            );
        }

        if ($driver === '' || $driver === 'null' || $driver === 'log') {
            $missing[] = 'X_FEEDBACK_SMS_DRIVER';
        }

        if ($driver === 'engagespark') {
            foreach ([
                'ENGAGESPARK_API_KEY' => config('engagespark.api_key'),
                'ENGAGESPARK_ORGANIZATION_ID' => config('engagespark.org_id'),
            ] as $key => $value) {
                if (! $this->configured($value)) {
                    $missing[] = $key;
                }
            }
        }

        return $this->fromMissingConfiguration(
            key: 'feedback.sms',
            label: 'SMS Delivery',
            missing: $missing,
            unavailableReason: 'SMS delivery is unavailable until its transport is configured.',
            source: 'x-feedback',
        );
    }

    private function emailFeedback(): InstructionCapabilityReadinessData
    {
        $mailer = strtolower(trim((string) config('mail.default', '')));
        $missing = [];

        if (in_array($mailer, ['array', 'log'], true) && ! app()->environment('production')) {
            return new InstructionCapabilityReadinessData(
                key: 'feedback.email',
                label: 'Email Delivery',
                status: 'simulation',
                issuanceAllowed: true,
                claimRetryable: true,
                reason: sprintf('Email delivery is using the non-production %s transport.', $mailer),
                source: 'x-feedback',
            );
        }

        if ($mailer === '' || in_array($mailer, ['array', 'log'], true)) {
            $missing[] = 'MAIL_MAILER';
        }

        if (! $this->configured(config('mail.from.address'))) {
            $missing[] = 'MAIL_FROM_ADDRESS';
        }

        if ($mailer === 'resend' && ! $this->configured(config('services.resend.key'))) {
            $missing[] = 'RESEND_KEY';
        }

        return $this->fromMissingConfiguration(
            key: 'feedback.email',
            label: 'Email Delivery',
            missing: $missing,
            unavailableReason: 'Email delivery is unavailable until its mail transport is configured.',
            source: 'x-feedback',
        );
    }

    private function webhookFeedback(): InstructionCapabilityReadinessData
    {
        return new InstructionCapabilityReadinessData(
            key: 'feedback.webhook',
            label: 'Webhook Delivery',
            status: 'ready',
            issuanceAllowed: true,
            claimRetryable: true,
            source: 'x-feedback',
        );
    }

    private function storedValue(): InstructionCapabilityReadinessData
    {
        $enabled = (bool) config(
            'x-change.execution.stored_value.issuance_enabled',
            false,
        );
        $durable = $this->storedValueGateway instanceof DurableStoredValueExecutionGatewayContract;
        $destinationAuthorityReady = $this->storedValueDestinationAuthority->isReady();
        $ready = $enabled && $durable && $destinationAuthorityReady;
        $missing = [];

        if (! $enabled) {
            $missing[] = 'XCHANGE_STORED_VALUE_ISSUANCE_ENABLED';
        }

        if (! $durable) {
            $missing[] = 'DURABLE_STORED_VALUE_GATEWAY';
        }

        if (! $destinationAuthorityReady) {
            $missing[] = 'STORED_VALUE_DESTINATION_AUTHORITY';
        }

        return new InstructionCapabilityReadinessData(
            key: 'stored_value',
            label: 'Reusable Balance',
            status: $ready ? 'ready' : 'unavailable',
            issuanceAllowed: $ready,
            claimRetryable: false,
            reason: $ready
                ? null
                : 'Reusable Balance is unavailable until its durable wallet engine and destination authority are commissioned.',
            missingConfiguration: $missing,
            source: 'wallet-stored-value',
        );
    }

    /**
     * @param  list<string>  $missing
     */
    private function fromMissingConfiguration(
        string $key,
        string $label,
        array $missing,
        string $unavailableReason,
        string $source,
    ): InstructionCapabilityReadinessData {
        $missing = array_values(array_unique($missing));
        $ready = $missing === [];

        return new InstructionCapabilityReadinessData(
            key: $key,
            label: $label,
            status: $ready ? 'ready' : 'unavailable',
            issuanceAllowed: $ready,
            claimRetryable: true,
            reason: $ready ? null : $unavailableReason,
            missingConfiguration: $missing,
            source: $source,
        );
    }

    private function configured(mixed $value): bool
    {
        if (! is_scalar($value)) {
            return false;
        }

        $configuredValue = (string) $value;

        return $configuredValue !== ''
            && trim($configuredValue) === $configuredValue
            && preg_match('/\s/u', $configuredValue) !== 1;
    }
}
