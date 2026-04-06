<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services;

use Illuminate\Http\Request;
use LBHurtado\XChange\Contracts\IdempotencyStoreContract;
use LBHurtado\XChange\Exceptions\IdempotencyConflict;

class IdempotencyService
{
    public function __construct(
        protected IdempotencyStoreContract $store,
    ) {}

    public function isEnabled(): bool
    {
        return (bool) config('x-change.api.idempotency.enabled', true);
    }

    public function headerName(): string
    {
        return (string) config('x-change.api.idempotency.header', 'Idempotency-Key');
    }

    public function ttlSeconds(): int
    {
        return (int) config('x-change.api.idempotency.ttl', 3600);
    }

    public function extractKey(Request $request): ?string
    {
        if (! $this->isEnabled()) {
            return null;
        }

        $value = $request->header($this->headerName());

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function fingerprint(array $payload): string
    {
        return hash('sha256', json_encode($this->sortRecursive($payload), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>|null
     */
    public function recallOrValidate(string $key, array $payload): ?array
    {
        $existing = $this->store->find($key);

        if (! $existing) {
            return null;
        }

        $expected = $existing['fingerprint'] ?? null;
        $actual = $this->fingerprint($payload);

        if (! is_string($expected) || $expected !== $actual) {
            throw new IdempotencyConflict('The supplied idempotency key has already been used with a different payload.');
        }

        $response = $existing['response'] ?? null;

        return is_array($response) ? $response : null;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $response
     */
    public function remember(string $key, array $payload, array $response): void
    {
        $this->store->put($key, [
            'fingerprint' => $this->fingerprint($payload),
            'response' => $response,
        ], $this->ttlSeconds());
    }

    /**
     * @param  array<mixed>  $value
     * @return array<mixed>
     */
    protected function sortRecursive(array $value): array
    {
        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $value[$key] = $this->sortRecursive($item);
            }
        }

        if ($this->isAssoc($value)) {
            ksort($value);
        }

        return $value;
    }

    /**
     * @param  array<mixed>  $value
     */
    protected function isAssoc(array $value): bool
    {
        return array_keys($value) !== range(0, count($value) - 1);
    }
}
