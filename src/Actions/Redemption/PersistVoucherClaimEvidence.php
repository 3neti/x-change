<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Redemption;

use Illuminate\Database\Eloquent\Model;
use JsonException;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Models\VoucherClaim;

final class PersistVoucherClaimEvidence
{
    /**
     * @param  array<string, mixed>  $evidence
     * @return list<int>
     *
     * @throws JsonException
     */
    public function handle(Voucher $voucher, VoucherClaim $claim, array $evidence): array
    {
        $inputIds = [];

        foreach ($evidence as $name => $value) {
            $normalizedName = trim((string) $name);
            $normalizedValue = $this->encode($value);

            if ($normalizedName === '' || $normalizedValue === null) {
                continue;
            }

            /** @var Model $input */
            $input = $voucher->inputs()->create([
                'name' => $normalizedName,
                'value' => $normalizedValue,
            ]);

            $inputIds[] = (int) $input->getKey();
        }

        if ($inputIds !== []) {
            $meta = (array) $claim->meta;
            data_set($meta, 'evidence.input_ids', $inputIds);
            data_set($meta, 'evidence.persisted', true);
            $claim->forceFill(['meta' => $meta])->save();
        }

        return $inputIds;
    }

    /**
     * @throws JsonException
     */
    private function encode(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        if (! is_array($value)) {
            return null;
        }

        return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    }
}
