<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Cockpit;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Models\PosSaleReference;
use RuntimeException;

final class CockpitPosSaleReferenceService
{
    /**
     * Remove browser authority over the canonical reference and normalize the
     * two optional operator-authored display fields.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function sanitizeBrowserPayload(array $payload): array
    {
        data_forget($payload, 'metadata.custom.cockpit.sale_reference');

        foreach (['purpose', 'order_reference'] as $field) {
            $value = data_get($payload, "metadata.custom.cockpit.{$field}");
            $normalized = is_string($value) ? trim($value) : '';

            if ($normalized === '') {
                data_forget($payload, "metadata.custom.cockpit.{$field}");
            } else {
                data_set($payload, "metadata.custom.cockpit.{$field}", $normalized);
            }
        }

        return $payload;
    }

    /** @param array<string, mixed> $payload @return array<string, mixed> */
    public function attachCanonicalReference(array $payload): array
    {
        if (! $this->isPosIssuance($payload)) {
            return $payload;
        }

        data_set(
            $payload,
            'metadata.custom.cockpit.sale_reference',
            'POS-'.CarbonImmutable::now((string) config('app.timezone', 'UTC'))->format('Ymd').'-'.Str::ulid(),
        );

        return $payload;
    }

    /** @param array<string, mixed> $payload */
    public function record(Voucher $voucher, Authenticatable $issuer, array $payload): ?PosSaleReference
    {
        if (! $this->isPosIssuance($payload)) {
            return null;
        }

        if (! $issuer instanceof Model) {
            throw new RuntimeException('POS sale reference authority must be an Eloquent model.');
        }

        $saleReference = $this->string(data_get($payload, 'metadata.custom.cockpit.sale_reference'));

        if ($saleReference === null) {
            throw new RuntimeException('POS issuance requires a canonical sale reference.');
        }

        return PosSaleReference::query()->create([
            'voucher_id' => $voucher->getKey(),
            'sale_reference' => $saleReference,
            'order_reference' => $this->string(data_get($payload, 'metadata.custom.cockpit.order_reference')),
            'purpose' => $this->string(data_get($payload, 'metadata.custom.cockpit.purpose')),
            'operator_type' => $issuer->getMorphClass(),
            'operator_id' => $issuer->getKey(),
        ]);
    }

    /** @return array{schema:string,sale_reference:?string,order_reference:?string,purpose:?string,legacy_reference:?string,reference_kind:string} */
    public function forVoucher(Voucher $voucher): array
    {
        $reference = PosSaleReference::query()->where('voucher_id', $voucher->getKey())->first();

        return $this->presentation($reference, $voucher);
    }

    /**
     * @param  iterable<int, Voucher>  $vouchers
     * @return array<string, array{schema:string,sale_reference:?string,order_reference:?string,purpose:?string,legacy_reference:?string,reference_kind:string}>
     */
    public function forVouchers(iterable $vouchers): array
    {
        $models = collect($vouchers)->filter(fn (mixed $voucher): bool => $voucher instanceof Voucher);
        $references = PosSaleReference::query()
            ->whereIn('voucher_id', $models->pluck('id'))
            ->get()
            ->keyBy(fn (PosSaleReference $reference): string => (string) $reference->voucher_id);

        return $models->mapWithKeys(fn (Voucher $voucher): array => [
            (string) $voucher->getKey() => $this->presentation(
                $references->get((string) $voucher->getKey()),
                $voucher,
            ),
        ])->all();
    }

    public function saleReferenceForVoucherId(int|string $voucherId): ?string
    {
        $value = PosSaleReference::query()->where('voucher_id', $voucherId)->value('sale_reference');

        return $this->string($value);
    }

    /** @param array<string, mixed> $payload */
    private function isPosIssuance(array $payload): bool
    {
        return data_get($payload, 'voucher_type') === 'payable'
            && data_get($payload, 'metadata.custom.cockpit.builder') === 'pos';
    }

    /** @return array{schema:string,sale_reference:?string,order_reference:?string,purpose:?string,legacy_reference:?string,reference_kind:string} */
    private function presentation(?PosSaleReference $reference, Voucher $voucher): array
    {
        $isLegacyPos = data_get($voucher->metadata, 'instructions.metadata.custom.cockpit.builder') === 'pos';
        $legacy = $reference === null && $isLegacyPos
            ? $this->string(data_get($voucher->metadata, 'instructions.metadata.custom.external_reference'))
            : null;

        return [
            'schema' => 'x-change.cockpit.pos-sale-reference.v1',
            'sale_reference' => $reference?->sale_reference,
            'order_reference' => $reference?->order_reference,
            'purpose' => $reference?->purpose,
            'legacy_reference' => $legacy,
            'reference_kind' => $reference !== null ? 'canonical' : ($legacy !== null ? 'legacy' : 'none'),
        ];
    }

    private function string(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $normalized = trim((string) $value);

        return $normalized !== '' ? $normalized : null;
    }
}
