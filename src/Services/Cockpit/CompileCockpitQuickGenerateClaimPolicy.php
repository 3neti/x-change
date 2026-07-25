<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Cockpit;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use LBHurtado\XChange\Services\Claim\VoucherClaimantReference;
use LBHurtado\XChange\Support\Auth\MobileNumber;

final readonly class CompileCockpitQuickGenerateClaimPolicy
{
    public function __construct(
        private VoucherClaimantReference $claimantReferences,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function handle(array $payload): array
    {
        $outcomes = collect((array) data_get($payload, 'claim.outcomes', []))
            ->pluck('key')
            ->map(static fn (mixed $key): string => mb_strtolower(trim((string) $key)))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if (! in_array('account_funding', $outcomes, true)) {
            return $payload;
        }

        if ($outcomes !== ['account_funding']) {
            throw ValidationException::withMessages([
                'claim.outcomes' => 'Cash payout and Account funds cannot be offered by the same Pay Code yet.',
            ]);
        }

        data_set($payload, 'claim.selection', 'server');
        data_set($payload, 'claim.consumption', 'one_of');
        data_set($payload, 'claim.default_outcome', 'account_funding');
        data_set(
            $payload,
            'claim.claimant',
            $this->claimantBinding(
                data_get(
                    $payload,
                    'metadata.custom.cockpit.recipient_reference',
                ),
            ),
        );
        Arr::forget(
            $payload,
            'metadata.custom.cockpit.recipient_reference',
        );

        return $payload;
    }

    /**
     * @return array{mode:string,reference?:string}
     */
    private function claimantBinding(mixed $recipientReference): array
    {
        $recipientReference = is_scalar($recipientReference)
            ? trim((string) $recipientReference)
            : '';

        if ($recipientReference === '' || mb_strtoupper($recipientReference) === 'CASH') {
            return ['mode' => 'unbound'];
        }

        $mobile = MobileNumber::normalize($recipientReference);

        if (
            ! is_string($mobile)
            || preg_match('/\A639\d{9}\z/', $mobile) !== 1
        ) {
            throw ValidationException::withMessages([
                'metadata.custom.cockpit.recipient_reference' => 'Account Funding recipients must be a verified Philippine mobile or CASH.',
            ]);
        }

        $modelClass = config('x-change.onboarding.issuer_model')
            ?: config('auth.providers.users.model');

        if (! is_string($modelClass) || ! class_exists($modelClass)) {
            throw ValidationException::withMessages([
                'metadata.custom.cockpit.recipient_reference' => 'The Account recipient directory is unavailable.',
            ]);
        }

        $candidates = array_values(array_unique([
            ...MobileNumber::candidates($mobile),
            '+'.$mobile,
        ]));
        $matches = $modelClass::query()
            ->whereIn('mobile', $candidates)
            ->limit(2)
            ->get();

        if ($matches->count() !== 1) {
            throw ValidationException::withMessages([
                'metadata.custom.cockpit.recipient_reference' => 'The verified Account recipient could not be resolved uniquely.',
            ]);
        }

        $recipient = $matches->sole();

        if (
            ! $recipient instanceof Model
            || ! $recipient instanceof Authenticatable
            || $recipient->getAttribute('mobile_verified_at') === null
        ) {
            throw ValidationException::withMessages([
                'metadata.custom.cockpit.recipient_reference' => 'The Account recipient mobile must be verified before issuance.',
            ]);
        }

        $reference = $this->claimantReferences->for($recipient);

        if (! is_string($reference) || $reference === '') {
            throw ValidationException::withMessages([
                'metadata.custom.cockpit.recipient_reference' => 'The Account recipient binding could not be created.',
            ]);
        }

        return [
            'mode' => 'recipient',
            'reference' => $reference,
        ];
    }
}
