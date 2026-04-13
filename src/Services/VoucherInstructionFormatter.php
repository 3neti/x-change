<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services;

use Brick\Money\Money;
use Illuminate\Support\Number;
use LBHurtado\Voucher\Data\VoucherInstructionsData;
use LBHurtado\Voucher\Enums\VoucherInputField;

/**
 * Format VoucherInstructionsData for display in notifications and UI summaries.
 *
 * Supports:
 * - pretty JSON
 * - human-readable text
 * - SMS-safe compact formatting
 * - email-safe full formatting
 */
class VoucherInstructionFormatter
{
    /**
     * SMS character limit for a reasonably short message.
     */
    private const SMS_REASONABLE_LENGTH = 200;

    /**
     * Format instructions as pretty-printed JSON.
     */
    public static function formatAsJson(VoucherInstructionsData $instructions): string
    {
        $data = [
            'cash' => [
                'amount' => $instructions->cash->amount,
                'currency' => $instructions->cash->currency,
            ],
        ];

        if ($instructions->cash->settlement_rail) {
            $rail = is_object($instructions->cash->settlement_rail)
                ? $instructions->cash->settlement_rail->value
                : $instructions->cash->settlement_rail;

            $data['cash']['settlement_rail'] = $rail;
            $data['cash']['fee_strategy'] = $instructions->cash->fee_strategy ?? 'absorb';
        }

        if ($instructions->inputs && $instructions->inputs->fields) {
            $data['inputs'] = array_map(
                fn ($field) => is_object($field) && isset($field->value) ? $field->value : (string) $field,
                $instructions->inputs->fields
            );
        }

        if ($instructions->feedback && ($instructions->feedback->email || $instructions->feedback->mobile || $instructions->feedback->webhook)) {
            if ($instructions->feedback->email) {
                $data['feedback']['email'] = $instructions->feedback->email;
            }

            if ($instructions->feedback->mobile) {
                $data['feedback']['mobile'] = $instructions->feedback->mobile;
            }

            if ($instructions->feedback->webhook) {
                $data['feedback']['webhook'] = $instructions->feedback->webhook;
            }
        }

        if ($instructions->validation) {
            if ($instructions->validation->location) {
                $data['validation']['location'] = [
                    'required' => $instructions->validation->location->required,
                    'target_lat' => $instructions->validation->location->target_lat,
                    'target_lng' => $instructions->validation->location->target_lng,
                    'radius_meters' => $instructions->validation->location->radius_meters,
                    'on_failure' => $instructions->validation->location->on_failure,
                ];
            }

            if ($instructions->validation->time) {
                $time = $instructions->validation->time;

                if ($time->window) {
                    $data['validation']['time']['window'] = [
                        'start_time' => $time->window->start_time,
                        'end_time' => $time->window->end_time,
                        'timezone' => $time->window->timezone,
                    ];
                }

                if ($time->limit_minutes) {
                    $data['validation']['time']['limit_minutes'] = $time->limit_minutes;
                }
            }
        }

        if ($instructions->rider && ($instructions->rider->message || $instructions->rider->url)) {
            if ($instructions->rider->message) {
                $data['rider']['message'] = $instructions->rider->message;
            }

            if ($instructions->rider->url) {
                $data['rider']['url'] = $instructions->rider->url;
            }
        }

        if ($instructions->ttl) {
            $data['ttl'] = $instructions->ttl->spec();
        }

        return (string) json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Format instructions as human-readable text.
     */
    public static function formatAsHuman(VoucherInstructionsData $instructions): string
    {
        $lines = [];

        $money = Money::of($instructions->cash->amount, $instructions->cash->currency);
        $lines[] = 'Amount: '.$money->formatTo(Number::defaultLocale());

        if ($instructions->cash->settlement_rail) {
            $rail = is_object($instructions->cash->settlement_rail)
                ? $instructions->cash->settlement_rail->value
                : $instructions->cash->settlement_rail;

            $feeStrategy = $instructions->cash->fee_strategy ?? 'absorb';

            $feeText = match ($feeStrategy) {
                'absorb' => 'fee absorbed by issuer',
                'include' => 'fee deducted from amount',
                'add' => 'fee added to disbursement',
                default => $feeStrategy,
            };

            $lines[] = "Rail: {$rail} ({$feeText})";
        }

        if ($instructions->inputs && $instructions->inputs->fields) {
            $fieldLabels = array_map(
                fn ($field) => $field instanceof VoucherInputField
                    ? static::getInputFieldLabel($field)
                    : ucfirst((string) $field),
                $instructions->inputs->fields
            );

            $lines[] = 'Inputs: '.implode(', ', $fieldLabels);
        }

        if ($instructions->feedback && ($instructions->feedback->email || $instructions->feedback->mobile || $instructions->feedback->webhook)) {
            $feedbackParts = [];

            if ($instructions->feedback->email) {
                $feedbackParts[] = $instructions->feedback->email;
            }

            if ($instructions->feedback->mobile) {
                $feedbackParts[] = $instructions->feedback->mobile;
            }

            if ($instructions->feedback->webhook) {
                $feedbackParts[] = $instructions->feedback->webhook;
            }

            $lines[] = 'Feedback: '.implode(', ', $feedbackParts);
        }

        if ($instructions->validation?->location) {
            $loc = $instructions->validation->location;
            $action = $loc->on_failure === 'block' ? 'Required' : 'Warning';

            $lines[] = "Location: Within {$loc->radius_meters}m of ({$loc->target_lat}, {$loc->target_lng}). {$action}.";
        }

        if ($instructions->validation?->time) {
            $time = $instructions->validation->time;

            if ($time->window) {
                $lines[] = "Time: {$time->window->start_time} - {$time->window->end_time} ({$time->window->timezone})";
            }

            if ($time->limit_minutes) {
                $lines[] = "Duration: Complete within {$time->limit_minutes} minutes";
            }
        }

        if ($instructions->rider?->message) {
            $lines[] = "Message: {$instructions->rider->message}";
        }

        if ($instructions->ttl) {
            $lines[] = "TTL: {$instructions->ttl->spec()}";
        }

        return implode("\n", $lines);
    }

    /**
     * Format instructions for SMS with truncation/compaction.
     */
    public static function formatForSms(VoucherInstructionsData $instructions, string $format = 'human'): ?string
    {
        if ($format === 'none') {
            return null;
        }

        $formatted = $format === 'json'
            ? static::formatAsJson($instructions)
            : static::formatAsHuman($instructions);

        if (mb_strlen($formatted) <= static::SMS_REASONABLE_LENGTH) {
            return $formatted;
        }

        $compact = static::formatCompact($instructions);

        if (mb_strlen($compact) > static::SMS_REASONABLE_LENGTH) {
            return mb_substr($compact, 0, static::SMS_REASONABLE_LENGTH - 3).'...';
        }

        return $compact;
    }

    /**
     * Format instructions for email without truncation.
     */
    public static function formatForEmail(VoucherInstructionsData $instructions, string $format = 'human'): ?string
    {
        if ($format === 'none') {
            return null;
        }

        return $format === 'json'
            ? static::formatAsJson($instructions)
            : static::formatAsHuman($instructions);
    }

    /**
     * Compact SMS-friendly summary.
     */
    protected static function formatCompact(VoucherInstructionsData $instructions): string
    {
        $parts = [];

        if ($instructions->inputs && $instructions->inputs->fields) {
            $fields = array_map(
                fn ($field) => $field instanceof VoucherInputField ? ucfirst($field->value) : ucfirst((string) $field),
                $instructions->inputs->fields
            );

            $parts[] = 'Inputs: '.implode(', ', $fields);
        }

        if ($instructions->cash->settlement_rail) {
            $rail = is_object($instructions->cash->settlement_rail)
                ? $instructions->cash->settlement_rail->value
                : $instructions->cash->settlement_rail;

            $parts[] = "Rail: {$rail}";
        }

        if ($instructions->validation?->location) {
            $loc = $instructions->validation->location;
            $parts[] = "Location: {$loc->radius_meters}m radius";
        }

        if ($instructions->ttl) {
            $parts[] = "TTL: {$instructions->ttl->spec()}";
        }

        return implode("\n", $parts);
    }

    /**
     * Friendly label for voucher input enum.
     */
    protected static function getInputFieldLabel(VoucherInputField $field): string
    {
        return match ($field) {
            VoucherInputField::NAME => 'Name',
            VoucherInputField::EMAIL => 'Email',
            VoucherInputField::MOBILE => 'Mobile',
            VoucherInputField::REFERENCE_CODE => 'Reference',
            VoucherInputField::SIGNATURE => 'Signature',
            VoucherInputField::ADDRESS => 'Address',
            VoucherInputField::BIRTH_DATE => 'Birth Date',
            VoucherInputField::GROSS_MONTHLY_INCOME => 'Income',
            VoucherInputField::LOCATION => 'Location',
            VoucherInputField::OTP => 'OTP',
            VoucherInputField::SELFIE => 'Selfie',
            VoucherInputField::KYC => 'KYC',
            default => ucfirst($field->value),
        };
    }

    public static function format(VoucherInstructionsData $instructions, string $format = 'human'): string
    {
        return $format === 'json'
            ? static::formatAsJson($instructions)
            : static::formatAsHuman($instructions);
    }
}
