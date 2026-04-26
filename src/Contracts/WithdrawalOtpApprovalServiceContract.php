<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Contracts;

interface WithdrawalOtpApprovalServiceContract
{
    public function request(string $mobile, string $reference, array $context = []): array;

    public function verify(string $mobile, string $reference, string $code, array $context = []): bool;
}
