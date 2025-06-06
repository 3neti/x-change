<?php

namespace LBHurtado\PaymentGateway\Support;

class BankRegistry
{
    protected array $banks;

    public function __construct()
    {
        $path = documents_path('banks.json'); // Use the helper here

        if (!file_exists($path)) {
            throw new \RuntimeException("Bank directory file not found at: {$path}");
        }

        $data = json_decode(file_get_contents($path), true);

        if (!isset($data['banks']) || !is_array($data['banks'])) {
            throw new \UnexpectedValueException("Invalid format in banks.json. Expected 'banks' root key.");
        }

        $this->banks = $data['banks'];
    }


    public function all(): array
    {
        return $this->banks;
    }

    public function find(string $swiftBic): ?array
    {
        return $this->banks[$swiftBic] ?? null;
    }

    public function supportedSettlementRails(string $swiftBic): array
    {
        return $this->banks[$swiftBic]['settlement_rail'] ?? [];
    }

    public function toCollection(): \Illuminate\Support\Collection
    {
        return collect($this->banks);
    }
}
