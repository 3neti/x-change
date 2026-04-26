<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\ApprovalHandlers;

use LBHurtado\XChange\Contracts\ApprovalRequirementHandlerContract;

class OtpApprovalRequirementHandler implements ApprovalRequirementHandlerContract
{
    public function requirement(): string
    {
        return 'otp';
    }

    public function handle(array $meta = [], array $context = []): array
    {
        return [
            'type' => 'otp',
            'status' => 'challenge_required',
            'label' => 'OTP verification required',
            'message' => 'Enter the OTP sent to the registered mobile number.',
            'meta' => [
                'channel' => $meta['channel'] ?? 'sms',
                'masked_mobile' => $meta['masked_mobile'] ?? null,
            ],
        ];
    }
}
