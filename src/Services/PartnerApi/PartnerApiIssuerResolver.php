<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\PartnerApi;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use LBHurtado\XChange\Contracts\CommercialPrincipalResolverContract;
use Throwable;

final readonly class PartnerApiIssuerResolver
{
    public const Account = 'account';

    public const CommercialPrincipal = 'commercial_principal';

    public function __construct(private CommercialPrincipalResolverContract $commercialPrincipal) {}

    public function resolve(string $type, string $identifier): Model
    {
        $issuer = match ($type) {
            self::Account => $this->resolveAccount($identifier),
            self::CommercialPrincipal => $this->resolveCommercialPrincipal($identifier),
            default => null,
        };

        if (! $issuer instanceof Model) {
            throw ValidationException::withMessages([
                'issuer_id' => ['The selected issuer Account is unavailable.'],
            ]);
        }

        return $issuer;
    }

    /** @return list<array{type: string, id: string, model: Model}> */
    public function options(): array
    {
        $modelClass = (string) config('auth.providers.users.model');
        $options = [];

        if (is_subclass_of($modelClass, Model::class)) {
            foreach ($modelClass::query()->latest()->limit(100)->get() as $account) {
                $options[] = [
                    'type' => self::Account,
                    'id' => (string) $account->getKey(),
                    'model' => $account,
                ];
            }
        }

        try {
            $principal = $this->commercialPrincipal->resolve();
            $options[] = [
                'type' => self::CommercialPrincipal,
                'id' => (string) $principal->reference,
                'model' => $principal,
            ];
        } catch (Throwable) {
            // A host without a commissioned Commercial Principal keeps account issuers available.
        }

        return $options;
    }

    private function resolveAccount(string $identifier): ?Model
    {
        $modelClass = (string) config('auth.providers.users.model');

        return is_subclass_of($modelClass, Model::class)
            ? $modelClass::query()->find($identifier)
            : null;
    }

    private function resolveCommercialPrincipal(string $identifier): ?Model
    {
        try {
            $principal = $this->commercialPrincipal->resolve();
        } catch (Throwable) {
            return null;
        }

        return hash_equals((string) $principal->reference, $identifier) ? $principal : null;
    }
}
