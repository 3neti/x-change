<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\VoucherInstruction;

use Closure;
use LBHurtado\XChange\Contracts\VoucherInstructionInterpreter;
use LBHurtado\XChange\Services\VoucherInstruction\Commands\TipCommand;

final class DeterministicVoucherInstructionInterpreter implements VoucherInstructionInterpreter
{
    /**
     * @param  Closure(string): array<string, mixed>|null  $contactResolver
     * @param  Closure(int, int): int|null  $tipAmountPicker
     */
    public function __construct(
        private readonly ?Closure $contactResolver = null,
        private readonly ?Closure $tipAmountPicker = null,
        private readonly array $templates = [],
        private readonly ?TipCommand $tipCommand = null,
    ) {}

    public function interpret(string $prompt): array
    {
        $prompt = trim($prompt);
        $normalizedPrompt = preg_replace('/\s+/', ' ', $prompt) ?: $prompt;
        $fields = $this->fields($normalizedPrompt);
        $recognized = [];
        $unresolved = [];
        $command = null;
        $templateKey = $this->templateKey($normalizedPrompt);
        $template = $templateKey === null ? null : $this->template($templateKey);

        if ($templateKey !== null) {
            $recognized[] = 'template';
            if ($template === null) {
                $unresolved[] = 'template';
            }
        }

        $tip = ($this->tipCommand ?? new TipCommand(amountPicker: $this->tipAmountPicker))->interpret($normalizedPrompt);
        if ($tip !== null) {
            $command = 'tip';
            $recognized[] = 'tip';
            $amount = $tip['amount'];
        } else {
            $amount = $this->amount($normalizedPrompt, $templateKey !== null);
        }

        if ($amount === null && is_array($template)) {
            $amount = isset($template['amount']) ? (float) $template['amount'] : null;
        }

        foreach ($fields as $field) {
            $recognized[] = $field;
        }

        $ttl = $this->ttl($normalizedPrompt);
        if ($ttl !== null) {
            $recognized[] = 'expiration';
        }

        $timeWindow = $this->timeWindow($normalizedPrompt);
        if ($timeWindow !== null) {
            $recognized[] = 'claim_window';
        }

        $recipient = $this->recipient($normalizedPrompt);
        if ($recipient !== null) {
            $recognized[] = 'recipient';
            if ($recipient['status'] !== 'ready') {
                $unresolved[] = $recipient['unresolved'];
            }
        }

        if ($amount !== null) {
            $recognized[] = 'amount';
        } else {
            $unresolved[] = 'amount';
        }

        $unresolved = array_values(array_unique(array_filter($unresolved)));
        $instructions = null;

        if ($unresolved === []) {
            $validation = [
                'secret' => null,
                'mobile' => $recipient['mobile'] ?? null,
                'payable' => null,
                'country' => null,
                'location' => null,
                'radius' => null,
                'mobile_verification' => null,
            ];
            $instructions = [
                'cash' => [
                    'amount' => $amount,
                    'currency' => 'PHP',
                    'fee_strategy' => 'absorb',
                    'validation' => $validation,
                ],
                'inputs' => ['fields' => $fields],
                'feedback' => [],
                'rider' => [],
                'count' => 1,
                'prefix' => null,
                'mask' => null,
                'ttl' => $ttl,
                'metadata' => [
                    'custom' => [
                        'cockpit' => [
                            'prompt' => $prompt,
                            'parser_version' => 'deterministic-v1',
                            ...($command === null ? [] : ['command' => $command]),
                            ...($templateKey === null ? [] : ['template_key' => $templateKey]),
                            ...(! empty($recipient['name']) ? ['recipient_name' => $recipient['name']] : []),
                            ...($fields !== [] && in_array('age', $this->requestedLabels($normalizedPrompt), true)
                                ? ['requested_fields' => $this->requestedLabels($normalizedPrompt)]
                                : []),
                        ],
                    ],
                ],
            ];

            if ($timeWindow !== null) {
                $instructions['validation']['time'] = [
                    'window' => $timeWindow,
                    'track_duration' => true,
                ];
            }

            foreach ($fields as $field) {
                $instructions['validation'][$field] = ['required' => true, 'on_failure' => 'block'];
            }
        }

        return [
            'schema' => 'x-change.voucher-instruction-interpretation.v1',
            'parser_version' => 'deterministic-v1',
            'status' => $unresolved === [] ? 'ready' : 'needs_clarification',
            'prompt' => $prompt,
            'instructions' => $instructions,
            'command' => $command,
            'template_key' => $templateKey,
            'recognized' => $recognized,
            'unresolved' => $unresolved,
            'clarifications' => $unresolved === [] ? [] : $this->clarifications($unresolved),
        ];
    }

    /** @return list<string> */
    private function fields(string $prompt): array
    {
        $fields = [];
        foreach (['signature', 'selfie', 'location', 'otp', 'kyc', 'name', 'address'] as $field) {
            if (preg_match('/\\b'.preg_quote($field, '/').'\\b/i', $prompt) === 1) {
                $fields[] = $field;
            }
        }

        if (preg_match('/\\bage\\b/i', $prompt) === 1) {
            $fields[] = 'birth_date';
        }

        return array_values(array_unique($fields));
    }

    private function amount(string $prompt, bool $hasTemplate): ?float
    {
        $withoutMobile = preg_replace('/(?:\\+63|0)9\\d{9}/', '', $prompt) ?: $prompt;
        if (preg_match('/(?:₱|php\\s*)?([0-9][0-9,]*(?:\\.[0-9]{1,2})?)(?:\\s*(?:php|peso|pesos))?/i', $withoutMobile, $matches) !== 1) {
            return null;
        }

        $value = (float) str_replace(',', '', $matches[1]);

        return $hasTemplate && preg_match('/the\\s+usual/i', $prompt) === 1 && preg_match('/^\\s*[0-9][0-9,]*(?:\\.[0-9]{1,2})?\\s+the\\s+usual/i', $prompt) !== 1
            ? null
            : $value;
    }

    private function templateKey(string $prompt): ?string
    {
        return preg_match('/\\bthe\\s+usual\\b/i', $prompt) === 1 ? 'the_usual' : null;
    }

    /** @return array<string, mixed>|null */
    private function template(string $key): ?array
    {
        return $this->templates[$key] ?? ($key === 'the_usual' ? ['amount' => 100.0] : null);
    }

    /** @return array{status: string, mobile?: string, name?: string, unresolved?: string} */
    private function recipient(string $prompt): ?array
    {
        if (preg_match('/(?:^|\\s)(0?9\\d{9}|\\+639\\d{9})(?:\\s|$)/', $prompt, $matches) === 1) {
            $mobile = $matches[1];
            if (str_starts_with($mobile, '+63')) {
                $mobile = '0'.substr($mobile, 3);
            }

            return ['status' => 'ready', 'mobile' => $mobile];
        }

        if (preg_match('/\\bpay\\s+([A-Za-z][A-Za-z ]*?)\\s+(?:₱\\s*)?[0-9]/i', $prompt, $matches) !== 1) {
            return null;
        }

        $name = trim($matches[1]);
        $resolved = $this->contactResolver?->__invoke($name);
        if (! is_array($resolved) || ! isset($resolved['mobile'])) {
            return ['status' => 'needs_clarification', 'unresolved' => 'contact', 'name' => $name];
        }

        return ['status' => 'ready', 'mobile' => (string) $resolved['mobile'], 'name' => $name];
    }

    /** @return array{start_time: string, end_time: string, timezone: string}|null */
    private function timeWindow(string $prompt): ?array
    {
        if (preg_match('/\\b(?:within|between)\\s+(\\d{1,2})(?::(\\d{2}))?\\s*(am|pm)?\\s*(?:and|to)\\s*(\\d{1,2})(?::(\\d{2}))?\\s*(am|pm)?/i', $prompt, $matches) !== 1) {
            return null;
        }

        return [
            'start_time' => $this->clock($matches[1], $matches[2] ?? '', $matches[3] ?? ''),
            'end_time' => $this->clock($matches[4], $matches[5] ?? '', $matches[6] ?? ''),
            'timezone' => 'Asia/Manila',
        ];
    }

    private function clock(string $hour, string $minute, string $meridiem): string
    {
        $hour = (int) $hour;
        if (strtolower($meridiem) === 'pm' && $hour < 12) {
            $hour += 12;
        }
        if (strtolower($meridiem) === 'am' && $hour === 12) {
            $hour = 0;
        }

        return sprintf('%02d:%02d', $hour, $minute === '' ? 0 : (int) $minute);
    }

    /** @return list<string> */
    private function requestedLabels(string $prompt): array
    {
        return preg_match('/\\bage\\b/i', $prompt) === 1 ? ['name', 'age', 'address'] : [];
    }

    private function clarifications(array $unresolved): array
    {
        return array_map(fn (string $key): string => match ($key) {
            'amount' => 'Tell us the amount to issue, for example “500 pesos”.',
            'template' => 'That template is not configured.',
            'contact' => 'We could not find one unique contact for that name.',
            default => 'Please clarify the requested instructions.',
        }, $unresolved);
    }

    private function ttl(string $prompt): ?string
    {
        if (preg_match('/\\b(\\d+)\\s*(minute|minutes|hour|hours|day|days)\\b/i', $prompt, $matches) !== 1) {
            return null;
        }

        $value = (int) $matches[1];
        $unit = strtolower($matches[2]);

        return match (true) {
            str_starts_with($unit, 'minute') => 'PT'.$value.'M',
            str_starts_with($unit, 'hour') => 'PT'.$value.'H',
            default => 'P'.$value.'D',
        };
    }
}
