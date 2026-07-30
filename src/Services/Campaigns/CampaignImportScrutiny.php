<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Campaigns;

use Illuminate\Support\Str;

final class CampaignImportScrutiny
{
    /**
     * @param  array<int, string>  $headers
     * @param  array<string, string>  $mapping
     * @return array<string, mixed>
     */
    public function suggest(string $sourceName, array $headers, array $mapping): array
    {
        $searchable = Str::of($sourceName.' '.implode(' ', $headers))
            ->lower()
            ->replace(['_', '-', '.'], ' ')
            ->squish()
            ->value();
        $profile = $this->containsAny($searchable, ['ayuda', 'aid', 'assistance', 'relief', 'benefit'])
            ? 'assistance'
            : 'payroll';
        $profileReason = $profile === 'assistance'
            ? 'The file name or columns look like an assistance list.'
            : 'The file name or columns look like a payroll or general beneficiary list.';

        $hasBankDestination = isset($mapping['institution']) || isset($mapping['bank_account']);
        $hasMessageDestination = isset($mapping['mobile']) || isset($mapping['email']);
        $fulfillmentMode = $hasBankDestination ? 'direct_bank_transfer' : 'pay_code_distribution';

        return [
            'name' => $this->suggestedName($sourceName, $profile),
            'profile' => $profile,
            'profile_reason' => $profileReason,
            'fulfillment_mode' => $fulfillmentMode,
            'fulfillment_reason' => $hasBankDestination
                ? 'Bank or account columns were found.'
                : 'Mobile or email columns were found.',
            'needs_fulfillment_choice' => $hasBankDestination && $hasMessageDestination,
            'delivery_plan' => ['csv'],
        ];
    }

    /** @param array<int, string> $needles */
    private function containsAny(string $value, array $needles): bool
    {
        return array_any($needles, fn (string $needle): bool => str_contains($value, $needle));
    }

    private function suggestedName(string $sourceName, string $profile): string
    {
        $name = Str::of(pathinfo($sourceName, PATHINFO_FILENAME))
            ->replace(['_', '-'], ' ')
            ->squish()
            ->title()
            ->value();

        return mb_strlen($name) >= 3 ? mb_substr($name, 0, 160) : Str::title($profile).' Campaign';
    }
}
