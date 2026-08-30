<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Lifecycle\Scenarios;

final readonly class LifecycleScenarioRunOptions
{
    public function __construct(
        public ?string $issuer = null,
        public ?string $maker = null,
        public ?string $checker = null,
        public ?string $wallet = null,
        public ?float $amount = null,
        public ?string $claimMobile = null,
        public ?string $claimName = null,
        public ?string $claimEmail = null,
        public ?int $timeout = null,
        public ?int $poll = null,
        public ?int $maxPolls = null,
        public ?string $onlyAttempt = null,
        public ?string $provider = null,
        public bool $noClaim = false,
        public bool $json = false,
        public bool $acceptPending = false,
        public bool $approvalPipeline = false,
        public bool $liveProvider = false,
        public bool $liveFeedback = false,
        public ?string $feedbackEmail = null,
        public ?string $feedbackMobile = null,
        public bool $confirmLiveTransfer = false,
        public ?string $runReference = null,
        public ?string $input = null,
    ) {}

    public static function fromConsoleOptions(array $options): self
    {
        return new self(
            issuer: self::stringOrNull($options['issuer'] ?? null),
            maker: self::stringOrNull($options['maker'] ?? null),
            checker: self::stringOrNull($options['checker'] ?? null),
            wallet: self::stringOrNull($options['wallet'] ?? null),
            amount: self::floatOrNull($options['amount'] ?? null),
            claimMobile: self::stringOrNull($options['claim-mobile'] ?? null),
            claimName: self::stringOrNull($options['claim-name'] ?? null),
            claimEmail: self::stringOrNull($options['claim-email'] ?? null),
            timeout: self::intOrNull($options['timeout'] ?? null),
            poll: self::intOrNull($options['poll'] ?? null),
            maxPolls: self::intOrNull($options['max-polls'] ?? null),
            onlyAttempt: self::stringOrNull($options['only-attempt'] ?? null),
            provider: self::stringOrNull($options['provider'] ?? null),
            noClaim: (bool) ($options['no-claim'] ?? false),
            json: (bool) ($options['json'] ?? false),
            acceptPending: (bool) ($options['accept-pending'] ?? false),
            approvalPipeline: (bool) ($options['approval-pipeline'] ?? false),
            liveProvider: (bool) ($options['live-provider'] ?? false),
            liveFeedback: (bool) ($options['live-feedback'] ?? false),
            feedbackEmail: self::stringOrNull($options['feedback-email'] ?? null),
            feedbackMobile: self::stringOrNull($options['feedback-mobile'] ?? null),
            confirmLiveTransfer: (bool) ($options['confirm-live-transfer'] ?? false),
            runReference: self::stringOrNull($options['run-reference'] ?? null),
            input: self::stringOrNull($options['input'] ?? null),
        );
    }

    private static function stringOrNull(mixed $value): ?string
    {
        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    private static function intOrNull(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_string($value)) {
            $value = trim($value);
        }

        if ($value === '' || $value === null) {
            return null;
        }

        return is_numeric($value) ? (int) $value : null;
    }

    private static function floatOrNull(mixed $value): ?float
    {
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        if (is_string($value)) {
            $value = trim($value);
        }

        if ($value === '' || $value === null) {
            return null;
        }

        return is_numeric($value) ? (float) $value : null;
    }

    public static function fromApiPayload(array $payload): self
    {
        return new self(
            issuer: self::stringOrNull($payload['issuer'] ?? null),
            maker: self::stringOrNull($payload['maker'] ?? null),
            checker: self::stringOrNull($payload['checker'] ?? null),
            wallet: self::stringOrNull($payload['wallet'] ?? null),
            amount: self::intOrNull($payload['amount'] ?? null),
            claimMobile: self::stringOrNull($payload['claim_mobile'] ?? null),
            claimName: self::stringOrNull($payload['claim_name'] ?? null),
            claimEmail: self::stringOrNull($payload['claim_email'] ?? null),
            timeout: self::intOrNull($payload['timeout'] ?? null),
            poll: self::intOrNull($payload['poll'] ?? null),
            maxPolls: self::intOrNull($payload['max_polls'] ?? null),
            onlyAttempt: self::stringOrNull($payload['only_attempt'] ?? null),
            provider: self::stringOrNull($payload['provider'] ?? null),
            noClaim: (bool) ($payload['no_claim'] ?? false),
            acceptPending: (bool) ($payload['accept_pending'] ?? false),
            approvalPipeline: (bool) ($payload['approval_pipeline'] ?? false),
            liveProvider: (bool) ($payload['live_provider'] ?? false),
            liveFeedback: (bool) ($payload['live_feedback'] ?? false),
            feedbackEmail: self::stringOrNull($payload['feedback_email'] ?? null),
            feedbackMobile: self::stringOrNull($payload['feedback_mobile'] ?? null),
            confirmLiveTransfer: (bool) ($payload['confirm_live_transfer'] ?? false),
            runReference: self::stringOrNull($payload['run_reference'] ?? null),
        );
    }
}
