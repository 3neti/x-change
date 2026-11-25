<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Banking;

use Illuminate\Database\Eloquent\Model;
use LBHurtado\EmiCore\Data\PayoutSourceAccountData;
use LBHurtado\XChange\Models\BankAccountAssignment;
use RuntimeException;

final class AuthorizedPayoutBankSource
{
    /** @return array<string, mixed> */
    public function resolve(mixed $owner, PayoutSourceAccountData $source): array
    {
        if (! $owner instanceof Model || $source->provider !== 'netbank') {
            throw new RuntimeException('The selected payout source is unavailable.');
        }

        $number = preg_replace('/\D+/', '', $source->account_number);
        foreach (BankAccountAssignment::query()->whereMorphedTo('owner', $owner)
            ->where('provider', 'netbank')->with('providerAccountLink')->cursor() as $assignment) {
            $link = $assignment->providerAccountLink;
            $details = $assignment->details_ciphertext;
            if (($details['account_number'] ?? null) !== $number || ($details['customer_id'] ?? null) !== $source->customer_id) {
                continue;
            }
            if ($link === null || ! $link->isReady() || $link->owner_type !== $owner->getMorphClass()
                || (string) $link->owner_id !== (string) $owner->getKey()
                || $link->provider !== 'netbank' || ! in_array('disbursement', $link->capabilities ?? [], true)) {
                break;
            }

            $providerNumber = $details['provider_account_number'] ?? $details['account_number'];
            if (! is_string($providerNumber) || preg_replace('/\D+/', '', $providerNumber) !== $number) {
                break;
            }

            return [
                'provider' => 'netbank', 'account_number' => $providerNumber,
                'customer_id' => $source->customer_id, 'mode' => 'dedicated',
                'account_link_id' => $link->getKey(),
            ];
        }

        throw new RuntimeException('The selected payout source is unavailable.');
    }
}
