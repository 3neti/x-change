<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Cockpit;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use LBHurtado\XCampaign\Models\CampaignWorksheet;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CockpitCampaignWorksheetResultsExportController extends Controller
{
    public function __invoke(Request $request, string $worksheet): StreamedResponse
    {
        $owner = $request->user();
        $campaign = CampaignWorksheet::query()
            ->where('reference', $worksheet)
            ->where('owner_type', $owner->getMorphClass())
            ->where('owner_id', (string) $owner->getAuthIdentifier())
            ->firstOrFail();

        return response()->streamDownload(function () use ($campaign): void {
            $handle = fopen('php://output', 'wb');
            fputcsv($handle, ['ordinal', 'name', 'mobile', 'email', 'bank_account', 'amount', 'mode', 'status', 'provider_transfer_reference', 'pay_code', 'claim_url']);

            $campaign->authorizations()
                ->with(['fulfillments.row'])
                ->latest('id')
                ->first()?->fulfillments()
                ->with('row')
                ->orderBy('id')
                ->chunkById(250, function ($fulfillments) use ($handle): void {
                    foreach ($fulfillments as $fulfillment) {
                        $beneficiary = $fulfillment->row?->beneficiary_ciphertext ?? [];
                        fputcsv($handle, [
                            $fulfillment->row?->ordinal ?? '',
                            $beneficiary['name'] ?? '',
                            $beneficiary['mobile'] ?? '',
                            $beneficiary['email'] ?? '',
                            $beneficiary['bank_account'] ?? '',
                            number_format(($fulfillment->row?->amount_minor ?? 0) / 100, 2, '.', ''),
                            $fulfillment->mode,
                            $fulfillment->status,
                            $fulfillment->provider_transfer_reference,
                            $fulfillment->pay_code,
                            $fulfillment->pay_code === null ? '' : route('x-change.claim.show', ['code' => $fulfillment->pay_code]),
                        ]);
                    }
                });

            fclose($handle);
        }, 'campaign-'.$campaign->reference.'-results.csv', ['Cache-Control' => 'no-store, private']);
    }
}
