<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Commercial;

use Illuminate\Support\Facades\DB;
use JsonException;
use LBHurtado\XChange\Exceptions\CommercialSaleConflict;
use LBHurtado\XChange\Models\CommercialAllocation;
use LBHurtado\XChange\Models\CommercialSale;
use LBHurtado\XChange\Models\PartnerCommissionPayout;

final readonly class RequestPartnerCommissionPayout
{
    /**
     * @param  array<string, scalar|null>  $metadata
     *
     * @throws JsonException
     */
    public function execute(
        string $commercialSaleReference,
        string $makerReference,
        string $idempotencyKey,
        array $metadata = [],
    ): PartnerCommissionPayout {
        $request = [
            'commercial_sale_reference' => trim($commercialSaleReference),
            'maker_reference' => trim($makerReference),
            'idempotency_key' => trim($idempotencyKey),
            'metadata' => $metadata,
        ];

        if ($request['commercial_sale_reference'] === ''
            || $request['maker_reference'] === ''
            || $request['idempotency_key'] === '') {
            throw new CommercialSaleConflict(
                'Partner commission payout request is incomplete.',
            );
        }

        $requestHash = hash(
            'sha256',
            json_encode($request, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
        );

        return DB::transaction(function () use ($request, $requestHash): PartnerCommissionPayout {
            $existing = PartnerCommissionPayout::query()
                ->where('request_idempotency_key', $request['idempotency_key'])
                ->lockForUpdate()
                ->first();

            if ($existing instanceof PartnerCommissionPayout) {
                if (! hash_equals($existing->request_hash, $requestHash)) {
                    throw new CommercialSaleConflict(
                        'Partner commission request key was reused with different input.',
                    );
                }

                return $existing;
            }

            $sale = CommercialSale::query()
                ->where('reference', $request['commercial_sale_reference'])
                ->lockForUpdate()
                ->firstOrFail();
            $allocation = CommercialAllocation::query()
                ->where('commercial_sale_id', $sale->getKey())
                ->where('category', 'partner_commission')
                ->lockForUpdate()
                ->sole();
            $existingAllocationPayout = PartnerCommissionPayout::query()
                ->where('commercial_allocation_id', $allocation->getKey())
                ->lockForUpdate()
                ->first();

            if ($existingAllocationPayout instanceof PartnerCommissionPayout) {
                throw new CommercialSaleConflict(
                    'Partner commission allocation already has a payout request.',
                );
            }

            $context = (array) data_get($sale->snapshot, 'accounting_context', []);
            $partnerReference = trim((string) data_get(
                $context,
                'partner_reference',
            ));

            if ($partnerReference === '') {
                throw new CommercialSaleConflict(
                    'Partner commission payout requires an immutable partner reference.',
                );
            }

            $provider = mb_strtolower(trim((string) data_get($context, 'provider')));
            $connectionReference = trim((string) data_get($context, 'connection_reference'));

            if ($provider === '' || $connectionReference === '') {
                throw new CommercialSaleConflict(
                    'Partner commission payout requires an immutable provider connection.',
                );
            }

            return PartnerCommissionPayout::query()->create([
                'commercial_sale_id' => $sale->getKey(),
                'commercial_allocation_id' => $allocation->getKey(),
                'partner_reference' => $partnerReference,
                'provider' => $provider,
                'connection_reference' => $connectionReference,
                'position_reference' => $allocation->destination_position_reference,
                'amount_minor' => $allocation->amount_minor,
                'currency' => $allocation->currency,
                'status' => 'awaiting_approval',
                'request_idempotency_key' => $request['idempotency_key'],
                'request_hash' => $requestHash,
                'maker_reference' => $request['maker_reference'],
                'metadata' => $request['metadata'],
                'requested_at' => now(),
            ]);
        }, attempts: 5);
    }
}
