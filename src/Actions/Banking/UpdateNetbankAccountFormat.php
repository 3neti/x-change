<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Banking;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LBHurtado\XChange\Contracts\AuditLoggerContract;
use LBHurtado\XChange\Models\BankAccountAssignment;

final readonly class UpdateNetbankAccountFormat
{
    public function __construct(private AuditLoggerContract $audit) {}

    public function handle(BankAccountAssignment $assignment, string $providerNumber, Model $actor): void
    {
        $providerNumber = trim($providerNumber);
        if (strlen($providerNumber) > 48 || ! preg_match('/\A[0-9 -]+\z/', $providerNumber)) {
            throw ValidationException::withMessages(['account_number' => 'Enter a valid provider account number.']);
        }

        DB::transaction(function () use ($assignment, $providerNumber, $actor): void {
            $locked = BankAccountAssignment::query()->lockForUpdate()->findOrFail($assignment->getKey());
            $details = $locked->details_ciphertext;
            if ($locked->provider !== 'netbank' || ! is_array($details)
                || preg_replace('/\D+/', '', $providerNumber) !== ($details['account_number'] ?? null)) {
                throw ValidationException::withMessages(['account_number' => 'The provider format must identify the same bank account.']);
            }
            if (($details['provider_account_number'] ?? null) === $providerNumber) {
                return;
            }
            $details['provider_account_number'] = $providerNumber;
            $locked->forceFill(['details_ciphertext' => $details])->save();
            $this->audit->log('bank.account.provider_format_updated', [
                'account_assignment_id' => $locked->getKey(),
                'actor_type' => $actor->getMorphClass(),
                'actor_id' => (string) $actor->getKey(),
            ]);
        }, attempts: 3);
    }
}
