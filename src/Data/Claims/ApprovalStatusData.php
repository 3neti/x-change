<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\Claims;

final readonly class ApprovalStatusData
{
    /**
     * @param  array<int, string>  $messages
     */
    public function __construct(
        public string $status,
        public string $voucher_code,
        public array $messages = [],
        public ?string $provider = null,
        public ?string $authorization_type = null,
        public ?string $reference_id = null,
        public bool $otp_required = false,
        public ?string $expires_at = null,
        public bool $polling_required = false,
        public bool $manual_review = false,
        public ?string $message = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toCompiledClaimResult(): array
    {
        return [
            'status' => $this->status,
            'voucher_code' => $this->voucher_code,
            'messages' => $this->messages,
            'approval_metadata' => [
                'provider' => $this->provider,
                'authorization_type' => $this->authorization_type,
                'reference_id' => $this->reference_id,
                'otp_required' => $this->otp_required,
                'expires_at' => $this->expires_at,
                'polling_required' => $this->polling_required,
                'manual_review' => $this->manual_review,
                'message' => $this->message,
            ],
        ];
    }
}
