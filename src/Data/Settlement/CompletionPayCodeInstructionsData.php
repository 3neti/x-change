<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\Settlement;

use InvalidArgumentException;

final readonly class CompletionPayCodeInstructionsData
{
    /** @param array<int, string> $applicantFields */
    public function __construct(
        public array $applicantFields,
        public bool $requiresOtp = false,
        public string $prefix = 'COMP',
        public string $mask = '****',
        public ?string $message = null,
    ) {
        $fields = array_values(array_unique(array_map(
            static fn (string $field): string => trim($field),
            $this->applicantFields,
        )));

        if ($fields !== $this->applicantFields || in_array('', $fields, true)) {
            throw new InvalidArgumentException('Completion applicant fields must be unique, normalized strings.');
        }

        if ($this->requiresOtp && ! in_array('mobile', $fields, true)) {
            throw new InvalidArgumentException('OTP completion requires the mobile applicant field.');
        }

        if (trim($this->prefix) === '' || trim($this->mask) === '') {
            throw new InvalidArgumentException('Completion Pay Code prefix and mask are required.');
        }
    }

    /** @return array<string, mixed> */
    public function canonical(): array
    {
        return [
            'schema' => 'x-change.completion-pay-code-instructions.v1',
            'applicant_fields' => $this->applicantFields,
            'requires_otp' => $this->requiresOtp,
            'prefix' => $this->prefix,
            'mask' => $this->mask,
            'message' => $this->message,
        ];
    }
}
