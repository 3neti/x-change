<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Banking;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use LBHurtado\XChange\Models\BankAccountAssignment;
use LBHurtado\XChange\Models\ProviderAccountLink;
use LBHurtado\XChange\Services\Banking\BankAccountIdentityRegistry;
use LBHurtado\XChange\Services\Banking\NetbankAccountAssignmentPreflight;
use LBHurtado\XChange\Services\Banking\SynchronizeNetbankAccountIdentities;

class AssignNetbankAccount
{
    public function __construct(
        private readonly NetbankAccountAssignmentPreflight $preflight,
        private readonly BankAccountIdentityRegistry $identities,
        private readonly SynchronizeNetbankAccountIdentities $synchronizeIdentities,
    ) {}

    /** @param array<string, mixed> $details */
    public function handle(Model $owner, array $details): BankAccountAssignment
    {
        $details['provider_account_number'] = trim((string) ($details['account_number'] ?? ''));
        $details['account_number'] = preg_replace('/\D+/', '', $details['provider_account_number']);
        $data = Validator::make($details, [
            'account_number' => ['required', 'regex:/^\d{8,32}$/'],
            'provider_account_number' => ['required', 'regex:/\A[0-9 -]+\z/', 'max:48'],
            'account_name' => ['required', 'string', 'max:191'],
            'customer_id' => ['required', 'regex:/^\d{1,32}$/'],
            'vca_alias' => ['required', 'regex:/^\d{5}$/'],
            'nickname' => ['nullable', 'string', 'max:191'],
        ])->validate();

        $shared = preg_replace('/\D+/', '', (string) config('payment-gateway.netbank.funding.corporate_account_number'));
        if ($shared !== '' && hash_equals($shared, $data['account_number'])) {
            throw ValidationException::withMessages(['account_number' => 'The platform shared account cannot be assigned.']);
        }

        $this->preflight->assertAssignable($owner, $data['account_number']);

        $fingerprint = hash_hmac('sha256', 'netbank|'.$data['account_number'], (string) config('app.key'));
        if (BankAccountAssignment::query()->where('account_fingerprint', $fingerprint)->exists()) {
            throw ValidationException::withMessages(['account_number' => 'This account is already assigned.']);
        }

        return DB::transaction(function () use ($owner, $data, $fingerprint): BankAccountAssignment {
            $this->synchronizeIdentities->synchronize();
            $this->identities->claim($owner, 'netbank', $data['account_number']);
            $this->preflight->assertAliasAssignable($data['vca_alias'], $data['account_number']);

            $link = ProviderAccountLink::query()->whereMorphedTo('owner', $owner)
                ->where('provider', 'netbank')->where('topology', 'dedicated')->get()
                ->first(fn (ProviderAccountLink $candidate): bool => preg_replace('/\D+/', '', (string) ($candidate->routing_profile_ciphertext['bank_account_number'] ?? '')) === $data['account_number']);

            if ($link instanceof ProviderAccountLink) {
                $routing = $link->routing_profile_ciphertext;
                $existingCustomerId = $routing['customer_id'] ?? null;
                if ($existingCustomerId !== null && $existingCustomerId !== '' && (string) $existingCustomerId !== $data['customer_id']) {
                    throw ValidationException::withMessages(['customer_id' => 'The customer ID does not match the existing account.']);
                }
                $routing['bank_account_name'] ??= $data['account_name'];
                $routing['customer_id'] = $data['customer_id'];
                $routing['vca_alias'] = $data['vca_alias'];
                $link->forceFill(['routing_profile_ciphertext' => $routing])->save();
            } else {
                $link = ProviderAccountLink::query()->create([
                    'owner_type' => $owner->getMorphClass(),
                    'owner_id' => $owner->getKey(),
                    'provider' => 'netbank',
                    'topology' => 'dedicated',
                    'purpose' => 'funding',
                    'mode' => 'bank_account_link',
                    'status' => 'pending',
                    'verification_status' => 'assigned',
                    'capabilities' => [],
                    'routing_profile_ciphertext' => [
                        'bank_account_number' => $data['account_number'],
                        'bank_account_name' => $data['account_name'],
                        'customer_id' => $data['customer_id'],
                        'vca_alias' => $data['vca_alias'],
                    ],
                ]);
            }

            return BankAccountAssignment::query()->create([
                'owner_type' => $owner->getMorphClass(),
                'owner_id' => $owner->getKey(),
                'provider' => 'netbank',
                'provider_account_link_id' => $link->getKey(),
                'account_fingerprint' => $fingerprint,
                'details_ciphertext' => $data,
            ]);
        });
    }
}
