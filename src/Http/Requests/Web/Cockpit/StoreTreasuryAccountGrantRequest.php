<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Requests\Web\Cockpit;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use LBHurtado\XChange\Enums\TreasuryOperatorCapability;
use LBHurtado\XChange\Services\Treasury\TreasuryOperatorAuthority;
use LBHurtado\XChange\Services\Treasury\TreasuryProviderConnectionCatalog;

final class StoreTreasuryAccountGrantRequest extends FormRequest
{
    public function authorize(TreasuryOperatorAuthority $authority): bool
    {
        return $this->user() instanceof Model
            && $authority->allows($this->user(), TreasuryOperatorCapability::RequestAccountGrants);
    }

    public function rules(TreasuryProviderConnectionCatalog $connections): array
    {
        $activeConnections = $connections->active();

        return [
            'recipient_id' => ['required', 'string', 'max:120'],
            'amount_minor' => ['required', 'integer', 'min:1'],
            'currency' => ['required', 'string', 'size:3', Rule::in(array_map(
                static fn ($connection): string => $connection->currency,
                $activeConnections,
            ))],
            'connection_reference' => ['required', 'string', 'max:120', Rule::in(array_map(
                static fn ($connection): string => $connection->reference,
                $activeConnections,
            ))],
            'purpose' => ['required', 'string', 'max:255'],
            'idempotency_reference' => ['required', 'string', 'max:160'],
            'test_allocation' => ['sometimes', 'boolean'],
        ];
    }
}
