<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Cockpit;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use LBHurtado\XCampaign\Models\CampaignWorksheet;
use LBHurtado\XCampaign\Models\CampaignWorksheetAuthorization;
use LBHurtado\XChange\Actions\Campaigns\RecordCampaignDeliveryAttempt;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class CockpitCampaignWorksheetExportController extends Controller
{
    public function __construct(private readonly RecordCampaignDeliveryAttempt $deliveryAttempts) {}

    public function __invoke(Request $request, string $worksheet): StreamedResponse
    {
        $owner = $request->user();
        $campaign = CampaignWorksheet::query()
            ->where('reference', $worksheet)
            ->where('owner_type', $owner->getMorphClass())
            ->where('owner_id', (string) $owner->getAuthIdentifier())
            ->firstOrFail();
        $authorization = $campaign->authorizations()
            ->where('status', 'authorized')
            ->latest('id')
            ->firstOrFail();
        abort_unless($authorization instanceof CampaignWorksheetAuthorization, 404);

        $issuedCount = $authorization->fulfillments()->where('status', 'issued')->count();
        abort_if($issuedCount === 0, 409, 'No issued beneficiary Pay Codes are available to export.');

        $attempt = $this->deliveryAttempts->start(
            authorization: $authorization,
            channel: 'export',
            actor: $owner,
            idempotencyKey: sprintf('campaign-export:%s:%s', $authorization->reference, (string) Str::ulid()),
            metadata: [
                'format' => 'csv',
                'export_type' => 'pay_codes',
                'record_count' => $issuedCount,
            ],
        );

        return response()->streamDownload(function () use ($authorization, $attempt, $issuedCount): void {
            try {
                $handle = fopen('php://output', 'wb');
                fputcsv($handle, ['name', 'mobile', 'email', 'external_reference', 'amount', 'pay_code', 'claim_url']);
                $authorization->fulfillments()
                    ->with('row')->where('status', 'issued')->orderBy('id')->chunkById(250, function ($fulfillments) use ($handle): void {
                        foreach ($fulfillments as $fulfillment) {
                            $beneficiary = $fulfillment->row?->beneficiary_ciphertext ?? [];
                            fputcsv($handle, [$beneficiary['name'] ?? '', $beneficiary['mobile'] ?? '', $beneficiary['email'] ?? '', $beneficiary['external_reference'] ?? '', number_format(($fulfillment->row?->amount_minor ?? 0) / 100, 2, '.', ''), $fulfillment->pay_code, route('x-change.claim.show', ['code' => $fulfillment->pay_code])]);
                        }
                    });
                fclose($handle);

                $this->deliveryAttempts->append($attempt, 'completed', metadata: [
                    'format' => 'csv',
                    'record_count' => $issuedCount,
                ]);
            } catch (Throwable $exception) {
                $this->deliveryAttempts->append($attempt, 'failed', safeErrorCode: 'export_stream_failed');

                throw $exception;
            }
        }, 'campaign-'.$campaign->reference.'-pay-codes.csv', ['Cache-Control' => 'no-store, private']);
    }
}
