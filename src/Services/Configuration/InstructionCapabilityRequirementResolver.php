<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Configuration;

final class InstructionCapabilityRequirementResolver
{
    /**
     * @param  array<string, mixed>  $instructions
     * @return list<string>
     */
    public function forInstructions(array $instructions): array
    {
        $required = [];
        $fields = array_values(array_filter(
            (array) data_get($instructions, 'inputs.fields', []),
            'is_string',
        ));
        $requirements = array_values(array_filter(
            (array) data_get($instructions, 'inputs.requirements', []),
            'is_string',
        ));

        if ($this->enabled(data_get($instructions, 'validation.location'))
            || $this->enabled(data_get($instructions, 'cash.validation.location'))
            || in_array('location', $fields, true)) {
            $required[] = 'location';
        }

        foreach (['kyc', 'otp', 'selfie'] as $capability) {
            if (in_array($capability, $fields, true)
                || in_array($capability, $requirements, true)
                || $this->enabled(data_get($instructions, "validation.{$capability}"))) {
                $required[] = $capability;
            }
        }

        if (in_array('signature', $fields, true)
            || $this->enabled(data_get($instructions, 'validation.signature'))
            || $this->enabled(data_get($instructions, 'signature'))) {
            $required[] = 'signature';
        }

        foreach (['sms' => 'mobile', 'email' => 'email', 'webhook' => 'webhook'] as $channel => $field) {
            $blueprintChannels = (array) data_get($instructions, 'feedback.channels', []);

            if ($this->configured(data_get($instructions, "feedback.{$field}"))
                || in_array($field, $blueprintChannels, true)
                || in_array($channel, $blueprintChannels, true)) {
                $required[] = "feedback.{$channel}";
            }
        }

        $required = array_values(array_unique($required));
        sort($required);

        return $required;
    }

    private function enabled(mixed $value): bool
    {
        if (is_array($value)) {
            return $value !== [] && $value !== ['enabled' => false];
        }

        return filter_var($value, FILTER_VALIDATE_BOOL);
    }

    private function configured(mixed $value): bool
    {
        return is_scalar($value) && trim((string) $value) !== '';
    }
}
