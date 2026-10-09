<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Claim;

use LBHurtado\Contact\Models\Contact;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Data\Redemption\SubmitPayCodeClaimResultData;
use LBHurtado\XChange\Support\Auth\MobileNumber;
use Propaganistas\LaravelPhone\PhoneNumber;

final class ContactClaimProfile
{
    /** @param array<string, mixed> $payload */
    public function persist(Voucher $voucher, SubmitPayCodeClaimResultData $result, array $payload): void
    {
        if (! $result->claimed || ! in_array($result->status, ['succeeded', 'success', 'completed', 'redeemed', 'withdrawn'], true)) {
            return;
        }

        $submittedMobile = data_get($payload, 'mobile');
        $mobile = MobileNumber::normalize(is_string($submittedMobile) ? $submittedMobile : null);

        if ($mobile === null || preg_match('/^639[0-9]{9}$/', $mobile) !== 1) {
            return;
        }

        $contact = $voucher->contact;

        if (! $contact instanceof Contact || MobileNumber::normalize($contact->mobile) !== $mobile) {
            $contact = Contact::fromPhoneNumber(new PhoneNumber($mobile, 'PH'));
        }

        $inputs = (array) data_get($payload, 'inputs', []);
        $fields = [
            'name' => 150,
            'email' => 254,
            'address' => 1000,
        ];

        foreach ($fields as $field => $maximumLength) {
            $value = $inputs[$field] ?? ($field === 'name' ? $inputs['full_name'] ?? null : null);

            if (is_string($value) && trim($value) !== '' && mb_strlen(trim($value)) <= $maximumLength
                && ($field !== 'email' || filter_var($value, FILTER_VALIDATE_EMAIL) !== false)
                && trim((string) $contact->{$field}) === '') {
                $contact->{$field} = trim($value);
            }
        }

        $birthDate = $inputs['birth_date'] ?? $inputs['date_of_birth'] ?? null;

        if (is_string($birthDate) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $birthDate) === 1
            && checkdate((int) substr($birthDate, 5, 2), (int) substr($birthDate, 8, 2), (int) substr($birthDate, 0, 4))
            && trim((string) $contact->birth_date) === '') {
            $contact->birth_date = $birthDate;
        }

        if ($contact->isDirty()) {
            $contact->save();
        }
    }

    public function find(string $mobile): ?Contact
    {
        $normalized = MobileNumber::normalize($mobile);

        if ($normalized === null || preg_match('/^639[0-9]{9}$/', $normalized) !== 1) {
            return null;
        }

        $local = '0'.substr($normalized, 2);

        return Contact::query()->where('mobile', $local)->where('country', 'PH')->first();
    }
}
