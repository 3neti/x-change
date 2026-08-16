<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Commercial;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use JsonException;
use LBHurtado\XChange\Enums\CommercialBillableEventStatus;
use LBHurtado\XChange\Exceptions\CommercialSaleConflict;
use LBHurtado\XChange\Models\CommercialBillableEvent;
use LBHurtado\XChange\Models\CommercialSale;
use LBHurtado\XCommerce\Data\CommercialComponentEconomicsData;
use LBHurtado\XCommerce\Data\CommercialSaleSnapshotData;

final class CommercialBillableEventRecorder
{
    public function __construct(
        private readonly CommercialRecognitionPolicyRegistry $recognitionPolicies,
        private readonly PersistCommercialRecognitionPolicy $persistRecognitionPolicy,
    ) {}

    /**
     * @return Collection<int, CommercialBillableEvent>
     *
     * @throws JsonException
     */
    public function recordForSale(
        CommercialSale $sale,
        CommercialSaleSnapshotData $snapshot,
    ): Collection {
        $economics = $snapshot->quoteSnapshot->componentEconomicsSnapshot;

        if ($economics === null) {
            return collect();
        }

        $components = collect($economics->components)
            ->keyBy(static fn (CommercialComponentEconomicsData $component): string => $component->componentReference);
        $events = collect();

        foreach ($snapshot->quoteSnapshot->lines as $line) {
            if ($line->totalPriceMinor === 0) {
                continue;
            }

            $component = $components->get($line->catalogItemReference);

            if (! $component instanceof CommercialComponentEconomicsData
                || ! $component->isBillable()
                || blank($component->billableEventReference)
                || blank($component->recognitionPolicyReference)) {
                throw new CommercialSaleConflict(
                    "Priced component [{$line->catalogItemReference}] has no governed Billable Event policy.",
                );
            }

            $recognitionPolicy = $this->recognitionPolicies->resolve(
                $component->recognitionPolicyReference,
                $component->billableEventReference,
            );
            $recognitionPolicyHash = $recognitionPolicy->snapshotHash();

            if ($recognitionPolicy->trigger !== 'commercial_sale.accepted'
                || $recognitionPolicy->timing !== 'immediate') {
                throw new CommercialSaleConflict(
                    "Recognition policy [{$recognitionPolicy->reference}] is not supported by immediate Commercial Sale posting.",
                );
            }

            $persistedRecognitionPolicy = $this->persistRecognitionPolicy->execute($recognitionPolicy);

            $payload = [
                'commercial_sale_reference' => $sale->reference,
                'commercial_sale_snapshot_hash' => $sale->snapshot_hash,
                'source_event_reference' => $snapshot->quoteSnapshot->sourceCommercialEventReference,
                'component_economics_reference' => $economics->reference,
                'component_economics_version' => $economics->version,
                'component_reference' => $line->catalogItemReference,
                'event_type' => $component->billableEventReference,
                'recognition_policy_reference' => $component->recognitionPolicyReference,
                'recognition_policy_version' => $recognitionPolicy->version,
                'recognition_policy_hash' => $recognitionPolicyHash,
                'quantity' => $line->quantity,
                'unit_amount_minor' => $line->unitPriceMinor,
                'total_amount_minor' => $line->totalPriceMinor,
                'currency' => $line->currency,
            ];
            $payloadHash = hash('sha256', json_encode(
                $payload,
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
            ));
            $eventReference = 'commercial-billable-event:'.hash(
                'sha256',
                $sale->reference.'|'.$line->catalogItemReference,
            );
            $event = CommercialBillableEvent::query()
                ->where('event_reference', $eventReference)
                ->orWhere(function ($query) use ($sale, $line): void {
                    $query->where('commercial_sale_id', $sale->getKey())
                        ->where('component_reference', $line->catalogItemReference);
                })
                ->lockForUpdate()
                ->first();

            if ($event !== null) {
                if (! hash_equals($event->payload_hash, $payloadHash)
                    || $event->event_reference !== $eventReference) {
                    throw new CommercialSaleConflict(
                        'A Billable Event reference was replayed with different immutable economics.',
                    );
                }

                $events->push($event);

                continue;
            }

            $events->push(CommercialBillableEvent::query()->create([
                'commercial_sale_id' => $sale->getKey(),
                'commercial_recognition_policy_id' => $persistedRecognitionPolicy->getKey(),
                'event_reference' => $eventReference,
                'event_type' => $component->billableEventReference,
                'recognition_policy_reference' => $component->recognitionPolicyReference,
                'recognition_policy_version' => $recognitionPolicy->version,
                'recognition_policy_hash' => $recognitionPolicyHash,
                'recognition_policy_snapshot' => $recognitionPolicy->toArray(),
                'source_event_reference' => $snapshot->quoteSnapshot->sourceCommercialEventReference,
                'component_reference' => $line->catalogItemReference,
                'quantity' => $line->quantity,
                'unit_amount_minor' => $line->unitPriceMinor,
                'total_amount_minor' => $line->totalPriceMinor,
                'currency' => $line->currency,
                'payload_hash' => $payloadHash,
                'received_at' => $snapshot->acceptedAt,
            ]));
        }

        if ((int) $events->sum('total_amount_minor') !== $snapshot->quoteSnapshot->totalPriceMinor) {
            throw new CommercialSaleConflict('Billable Events do not conserve the Commercial Sale total.');
        }

        return $events;
    }

    public function markPostedForSale(CommercialSale $sale): void
    {
        $events = $sale->billableEvents()->lockForUpdate()->get();

        foreach ($events as $event) {
            if ($event->status === CommercialBillableEventStatus::Posted) {
                continue;
            }

            if ($event->status !== CommercialBillableEventStatus::Received) {
                throw new CommercialSaleConflict('Only a received Billable Event can be posted.');
            }

            DB::table((new CommercialBillableEvent)->getTable())
                ->where('id', $event->getKey())
                ->update([
                    'status' => CommercialBillableEventStatus::Posted->value,
                    'posted_at' => now(),
                    'updated_at' => now(),
                ]);
        }
    }

    public function markReversedForSale(CommercialSale $sale, string $reasonReference): void
    {
        $reason = trim($reasonReference);
        $events = $sale->billableEvents()->lockForUpdate()->get();

        foreach ($events as $event) {
            if ($event->status === CommercialBillableEventStatus::Reversed) {
                if ($event->reversal_reference !== $reason) {
                    throw new CommercialSaleConflict('Billable Events were already reversed for a different reason.');
                }

                continue;
            }

            if ($event->status !== CommercialBillableEventStatus::Posted) {
                throw new CommercialSaleConflict('Only a posted Billable Event can be reversed.');
            }

            DB::table((new CommercialBillableEvent)->getTable())
                ->where('id', $event->getKey())
                ->update([
                    'status' => CommercialBillableEventStatus::Reversed->value,
                    'reversal_reference' => $reason,
                    'reversed_at' => now(),
                    'updated_at' => now(),
                ]);
        }
    }

    public function assertReversalReplay(CommercialSale $sale, string $reasonReference): void
    {
        $events = $sale->billableEvents()->get();

        if ($events->isEmpty()) {
            return;
        }

        if ($events->contains(
            static fn (CommercialBillableEvent $event): bool => $event->status !== CommercialBillableEventStatus::Reversed
                || $event->reversal_reference !== trim($reasonReference),
        )) {
            throw new CommercialSaleConflict('The Commercial Sale was already reversed for different Billable Event evidence.');
        }
    }
}
