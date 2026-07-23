<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Cockpit;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Number;
use LBHurtado\XChange\Actions\Funding\GenerateNetbankReusableFundingAddress;
use LBHurtado\XChange\Actions\Funding\InspectNetbankReusableFundingAddressHistory;
use LBHurtado\XChange\Http\Requests\Web\Cockpit\AccessCockpitStandingFundingAddressRequest;

final class CockpitNetbankStandingFundingAddressController extends Controller
{
    public function store(
        AccessCockpitStandingFundingAddressRequest $request,
        GenerateNetbankReusableFundingAddress $generate,
    ): JsonResponse {
        $address = $generate->handle($request->user());

        return response()->json([
            'schema' => 'x-change.cockpit.standing-funding-address.v1',
            'address' => [
                'reference' => $address->reference,
                'provider' => $address->provider,
                'funding_address' => $address->fundingAddress,
                'masked_funding_address' => $address->maskedFundingAddress,
                'purpose' => $address->purpose,
                'recognition_mode' => $address->recognitionMode,
                'status' => $address->status,
                'currency' => $address->currency,
                'institution' => $address->institution,
                'merchant_name' => $address->merchantName,
                'qr_code' => $address->qrCode,
                'qr_mode' => $address->qrMode,
                'transaction_type' => $address->transactionType,
                'embedded_amount' => $address->embeddedAmount,
                'provider_generated' => $address->providerGenerated,
                'temporary' => $address->temporary,
                'funding_intent_created' => $address->fundingIntentCreated,
                'automatic_credit_enabled' => $address->automaticCreditEnabled,
                'minimum_amount_minor' => $address->minimumAmountMinor,
                'maximum_amount_minor' => $address->maximumAmountMinor,
                'daily_limit_minor' => $address->dailyLimitMinor,
            ],
        ])->withHeaders($this->sensitiveHeaders());
    }

    public function history(
        AccessCockpitStandingFundingAddressRequest $request,
        InspectNetbankReusableFundingAddressHistory $inspect,
    ): JsonResponse {
        $history = $inspect->handle($request->user());
        $observations = array_map(
            fn ($observation): array => [
                'reference' => $observation->reference,
                'gross_amount_minor' => $observation->grossAmountMinor,
                'fee_amount_minor' => $observation->feeAmountMinor,
                'net_amount_minor' => $observation->netAmountMinor,
                'currency' => $observation->currency,
                'provider_status' => $observation->providerStatus,
                'occurred_at' => $observation->occurredAt,
                'settled_at' => $observation->settledAt,
                'can_approve' => $observation->canApprove,
                'gross_amount' => Number::currency(
                    $observation->grossAmountMinor / 100,
                    in: $observation->currency,
                ),
                'net_amount' => Number::currency(
                    $observation->netAmountMinor / 100,
                    in: $observation->currency,
                ),
            ],
            $history->observations,
        );

        return response()->json([
            'schema' => 'x-change.cockpit.standing-funding-history.v1',
            'observations' => $observations,
            'checked_at' => now()->toIso8601String(),
            'balance_changed' => $history->sync->settled > 0,
            'funding_intent_created' => false,
            'sync' => [
                'observed' => $history->sync->observed,
                'settled' => $history->sync->settled,
                'awaiting_approval' => $history->sync->awaitingApproval,
                'suspense' => $history->sync->suspense,
            ],
        ])->withHeaders($this->sensitiveHeaders());
    }

    /**
     * @return array<string, string>
     */
    private function sensitiveHeaders(): array
    {
        return [
            'Cache-Control' => 'no-store, no-cache, must-revalidate, private',
            'Pragma' => 'no-cache',
            'Expires' => '0',
            'X-Content-Type-Options' => 'nosniff',
        ];
    }
}
