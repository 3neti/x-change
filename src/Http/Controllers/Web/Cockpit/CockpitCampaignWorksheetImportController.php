<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Cockpit;

use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use LBHurtado\XCampaign\Contracts\CampaignWorksheetRepository;
use LBHurtado\XCampaign\Models\CampaignWorksheet;
use LBHurtado\XCampaign\Models\CampaignWorksheetImport;
use LBHurtado\XChange\Http\Requests\Web\Cockpit\StageCampaignWorksheetCsvRequest;

class CockpitCampaignWorksheetImportController extends Controller
{
    public function __invoke(StageCampaignWorksheetCsvRequest $request, string $worksheet): RedirectResponse
    {
        $owner = $request->user();
        $campaign = app(CampaignWorksheetRepository::class)->findForOwner($worksheet, $owner->getMorphClass(), (string) $owner->getAuthIdentifier());
        abort_unless($campaign !== null && $campaign->status === 'draft', 404);
        $record = CampaignWorksheet::query()->where('reference', $worksheet)->firstOrFail();
        $contents = (string) $request->file('file')->get();
        $rows = $this->rows($contents);
        CampaignWorksheetImport::query()->create(['campaign_worksheet_id' => $record->getKey(), 'status' => 'staged', 'source_format' => 'csv', 'content_hash' => hash('sha256', $contents), 'row_count' => count($rows), 'rows_ciphertext' => $rows, 'mapping' => ['mobile' => 'mobile', 'bank_account' => 'bank_account', 'amount_minor' => 'amount_minor', 'email' => 'email', 'remarks' => 'remarks']]);

        return to_route('x-change.cockpit.campaigns.show', $worksheet)->with('campaign_notice', sprintf('%d CSV rows staged privately. Review and apply is next.', count($rows)));
    }

    /** @return array<int, array<string, string>> */
    private function rows(string $contents): array
    {
        $lines = preg_split('/\R/', trim($contents)) ?: [];
        $headers = array_map(fn (string $value): string => trim(mb_strtolower($value)), str_getcsv((string) array_shift($lines)));
        foreach (['amount_minor'] as $required) {
            if (! in_array($required, $headers, true)) {
                abort(422, 'CSV must include amount_minor.');
            }
        }

        return array_values(array_filter(array_map(function (string $line) use ($headers): ?array {
            $values = str_getcsv($line);
            $row = array_combine($headers, array_pad($values, count($headers), ''));

            return is_array($row) && ((string) ($row['mobile'] ?? '') !== '' || (string) ($row['bank_account'] ?? '') !== '') && (int) ($row['amount_minor'] ?? 0) > 0 ? $row : null;
        }, $lines)));
    }
}
