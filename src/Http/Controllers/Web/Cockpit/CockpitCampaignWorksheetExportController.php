<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Cockpit;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use LBHurtado\XCampaign\Models\CampaignWorksheet;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CockpitCampaignWorksheetExportController extends Controller
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
            fputcsv($handle, ['name', 'mobile', 'email', 'external_reference', 'amount', 'pay_code', 'claim_url']);
            $campaign->authorizations()->with(['fulfillments.row'])->where('status', 'authorized')->latest('id')->first()?->fulfillments()
                ->with('row')->where('status', 'issued')->orderBy('id')->chunkById(250, function ($fulfillments) use ($handle): void {
                    foreach ($fulfillments as $fulfillment) {
                        $beneficiary = $fulfillment->row?->beneficiary_ciphertext ?? [];
                        fputcsv($handle, [$beneficiary['name'] ?? '', $beneficiary['mobile'] ?? '', $beneficiary['email'] ?? '', $beneficiary['external_reference'] ?? '', number_format(($fulfillment->row?->amount_minor ?? 0) / 100, 2, '.', ''), $fulfillment->pay_code, route('x-change.claim.start', $fulfillment->pay_code)]);
                    }
                });
            fclose($handle);
        }, 'campaign-'.$campaign->reference.'-pay-codes.csv', ['Cache-Control' => 'no-store, private']);
    }
}
