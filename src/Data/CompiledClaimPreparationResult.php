<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data;

use LBHurtado\Voucher\Models\Voucher;

final readonly class CompiledClaimPreparationResult
{
    public function __construct(
        public ?CompiledClaimSubmissionData $submission,
        public ?Voucher $voucher,
        public ?string $errorMessage,
    ) {}

    public static function missingSubmission(): self
    {
        return new self(
            submission: null,
            voucher: null,
            errorMessage: 'Compiled claim submission is missing.',
        );
    }

    public static function invalid(
        CompiledClaimSubmissionData $submission,
        ?Voucher $voucher,
        string $errorMessage
    ): self {
        return new self(
            submission: $submission,
            voucher: $voucher,
            errorMessage: $errorMessage,
        );
    }

    public static function valid(
        CompiledClaimSubmissionData $submission,
        Voucher $voucher
    ): self {
        return new self(
            submission: $submission,
            voucher: $voucher,
            errorMessage: null,
        );
    }

    public function isValid(): bool
    {
        return $this->errorMessage === null
            && $this->submission !== null
            && $this->voucher !== null;
    }
}
