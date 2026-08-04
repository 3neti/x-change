<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Configuration;

use Illuminate\Validation\ValidationException;

final readonly class InstructionCapabilityIssuanceGuard
{
    public function __construct(
        private InstructionCapabilityReadinessRegistry $readiness,
        private InstructionCapabilityRequirementResolver $requirements,
    ) {}

    /**
     * @param  array<string, mixed>  $instructions
     */
    public function ensureAvailable(array $instructions): void
    {
        $errors = [];

        foreach ($this->requirements->forInstructions($instructions) as $key) {
            $capability = $this->readiness->find($key);

            if ($capability !== null && $capability->issuanceAllowed) {
                continue;
            }

            $errors[$this->validationField($key)][] = $capability?->reason
                ?? sprintf('The required instruction capability [%s] is unavailable.', $key);
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function validationField(string $key): string
    {
        return match ($key) {
            'location' => 'validation.location',
            'kyc', 'otp', 'selfie', 'signature' => "inputs.requirements.{$key}",
            'feedback.sms' => 'feedback.mobile',
            'feedback.email' => 'feedback.email',
            'feedback.webhook' => 'feedback.webhook',
            default => 'instructions',
        };
    }
}
