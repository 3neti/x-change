<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Cockpit;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Controller;
use InvalidArgumentException;
use LBHurtado\XCampaign\Contracts\CampaignWorksheetImportRepository;
use LBHurtado\XCampaign\Contracts\CampaignWorksheetRepository;
use LBHurtado\XCampaign\Data\CampaignWorksheetData;
use LBHurtado\XCampaign\Data\CampaignWorksheetImportData;
use LBHurtado\XCampaign\Models\CampaignWorksheet;
use LBHurtado\XChange\Http\Requests\Web\Cockpit\ApplyCampaignWorksheetImportRequest;
use LBHurtado\XChange\Http\Requests\Web\Cockpit\StageCampaignWorksheetCsvRequest;
use LBHurtado\XChange\Http\Requests\Web\Cockpit\UpdateCampaignWorksheetImportMappingRequest;
use LBHurtado\XChange\Services\Campaigns\CampaignLifecycleJournal;
use LBHurtado\XChange\Services\Campaigns\CampaignWorksheetImportNormalizer;
use LBHurtado\XChange\Services\Campaigns\CampaignWorksheetTabularReader;
use Throwable;

class CockpitCampaignWorksheetImportController extends Controller
{
    public function __construct(
        private readonly CampaignWorksheetRepository $worksheets,
        private readonly CampaignWorksheetImportRepository $imports,
        private readonly CampaignWorksheetTabularReader $reader,
        private readonly CampaignWorksheetImportNormalizer $normalizer,
        private readonly CampaignLifecycleJournal $journal,
    ) {}

    public function stage(StageCampaignWorksheetCsvRequest $request, string $worksheet): RedirectResponse
    {
        $owner = $request->user();
        $campaign = $this->draftForOwner($worksheet, $owner);
        $file = $request->file('file');
        abort_unless($file instanceof UploadedFile && $file->isValid(), 422, 'The worksheet file could not be uploaded.');

        try {
            $activeImport = collect($this->imports->forOwner(
                $worksheet,
                $this->ownerType($owner),
                (string) $owner->getAuthIdentifier(),
            ))->first(fn (CampaignWorksheetImportData $import): bool => in_array(
                $import->status,
                ['staged', 'applied_with_errors'],
                true,
            ));

            if ($activeImport instanceof CampaignWorksheetImportData) {
                throw new InvalidArgumentException('Add or discard the current preview before uploading another file.');
            }

            $source = $this->reader->read($file);
            if ($source['rows'] === []) {
                throw new InvalidArgumentException('The file contains no beneficiary rows.');
            }

            $mapping = $this->normalizer->detectMapping($source['headers']);
            $stagedRows = $this->normalizer->normalizeRows(
                $source['rows'],
                $mapping,
                $campaign->fulfillmentMode,
            );
        } catch (Throwable $exception) {
            return to_route('x-change.cockpit.campaigns.show', $worksheet)
                ->withErrors(['file' => $exception->getMessage()]);
        }

        $sourceFormat = mb_strtolower((string) $file->getClientOriginalExtension()) === 'xlsx' ? 'xlsx' : 'csv';
        $import = $this->imports->stage(new CampaignWorksheetImportData(
            reference: null,
            worksheetReference: $worksheet,
            status: 'staged',
            sourceFormat: $sourceFormat,
            contentHash: hash_file('sha256', $file->getRealPath()),
            rowCount: count($source['rows']),
            validRows: [],
            validationErrors: [],
            mapping: $mapping,
            stagedRows: $stagedRows,
            sourceHeaders: $source['headers'],
            sourceSheet: $source['sheet'],
        ), $this->ownerType($owner), (string) $owner->getAuthIdentifier());

        $validCount = count(array_filter($stagedRows, fn (array $row): bool => $row['status'] === 'valid'));
        $invalidCount = count($stagedRows) - $validCount;
        $storedWorksheet = CampaignWorksheet::query()->where('reference', $worksheet)->first();
        if ($storedWorksheet instanceof CampaignWorksheet) {
            $this->journal->recordWorksheet('campaign.csv.staged', $storedWorksheet, $owner, [
                'import_reference' => $import->reference,
                'source_format' => $sourceFormat,
                'row_count' => count($source['rows']),
                'valid_count' => $validCount,
                'invalid_count' => $invalidCount,
            ]);
        }

        $summary = $invalidCount === 0
            ? sprintf('%d rows are ready to add. Nothing has joined the draft yet.', $validCount)
            : sprintf('%d rows are ready and %d need attention. Valid rows may be added independently.', $validCount, $invalidCount);

        return to_route('x-change.cockpit.campaigns.show', $worksheet)
            ->with('campaign_notice', $summary)
            ->with('campaign_import_reference', $import->reference);
    }

    public function updateMapping(
        UpdateCampaignWorksheetImportMappingRequest $request,
        string $worksheet,
        string $import,
    ): RedirectResponse {
        $owner = $request->user();
        $campaign = $this->draftForOwner($worksheet, $owner);
        $staged = $this->imports->findForOwner(
            $worksheet,
            $import,
            $this->ownerType($owner),
            (string) $owner->getAuthIdentifier(),
        );
        abort_unless($staged instanceof CampaignWorksheetImportData && $staged->status !== 'discarded', 404);

        $validated = $request->validated();
        $mapping = (array) $validated['mapping'];
        if (count($mapping) !== count(array_unique($mapping))) {
            return to_route('x-change.cockpit.campaigns.show', $worksheet)
                ->withErrors(['mapping' => 'Each source column may be mapped to only one beneficiary field.']);
        }

        $unknownHeaders = array_diff(array_values($mapping), $staged->sourceHeaders);
        if ($unknownHeaders !== []) {
            return to_route('x-change.cockpit.campaigns.show', $worksheet)
                ->withErrors(['mapping' => 'A selected source column is no longer available.']);
        }

        $unappliedRows = array_values(array_filter(
            $staged->stagedRows,
            fn (array $row): bool => ($row['applied_at'] ?? null) === null,
        ));
        $sourceRows = array_values(array_map(
            fn (array $row): array => (array) ($row['source'] ?? []),
            $unappliedRows,
        ));
        $normalizedRows = $this->normalizer->normalizeRows(
            $sourceRows,
            $mapping,
            $campaign->fulfillmentMode,
            (string) $validated['default_wallet'],
            (string) $validated['default_delivery_preference'],
        );
        $normalizedRows = array_map(function (array $row, int $index) use ($unappliedRows): array {
            $row['source_row'] = (int) ($unappliedRows[$index]['source_row'] ?? $row['source_row']);

            return $row;
        }, $normalizedRows, array_keys($normalizedRows));

        $this->imports->replaceUnappliedRows(
            $worksheet,
            $import,
            $this->ownerType($owner),
            (string) $owner->getAuthIdentifier(),
            [
                ...$mapping,
                '__default_wallet' => (string) $validated['default_wallet'],
                '__default_delivery_preference' => (string) $validated['default_delivery_preference'],
            ],
            $normalizedRows,
        );

        return to_route('x-change.cockpit.campaigns.show', $worksheet)
            ->with('campaign_notice', 'Import mapping and validation were refreshed.');
    }

    public function apply(ApplyCampaignWorksheetImportRequest $request, string $worksheet, string $import): RedirectResponse
    {
        $owner = $request->user();

        try {
            $before = $this->imports->findForOwner(
                $worksheet,
                $import,
                $this->ownerType($owner),
                (string) $owner->getAuthIdentifier(),
            );
            $validUnappliedCount = collect($before?->stagedRows ?? [])
                ->where('status', 'valid')
                ->whereNull('applied_at')
                ->count();
            $this->imports->apply($worksheet, $import, $this->ownerType($owner), (string) $owner->getAuthIdentifier());
        } catch (InvalidArgumentException $exception) {
            return to_route('x-change.cockpit.campaigns.show', $worksheet)->with('campaign_notice', $exception->getMessage());
        }

        $storedWorksheet = CampaignWorksheet::query()->where('reference', $worksheet)->first();
        if ($storedWorksheet instanceof CampaignWorksheet) {
            $this->journal->recordWorksheet('campaign.csv.applied', $storedWorksheet, $owner, [
                'import_reference' => $import,
                'applied_valid_count' => $validUnappliedCount,
            ]);
        }

        return to_route('x-change.cockpit.campaigns.show', $worksheet)
            ->with('campaign_notice', sprintf('%d imported beneficiaries were added to the draft.', $validUnappliedCount));
    }

    public function discard(ApplyCampaignWorksheetImportRequest $request, string $worksheet, string $import): RedirectResponse
    {
        $owner = $request->user();

        try {
            $this->imports->discard(
                $worksheet,
                $import,
                $this->ownerType($owner),
                (string) $owner->getAuthIdentifier(),
            );
        } catch (InvalidArgumentException $exception) {
            return to_route('x-change.cockpit.campaigns.show', $worksheet)->with('campaign_notice', $exception->getMessage());
        }

        return to_route('x-change.cockpit.campaigns.show', $worksheet)
            ->with('campaign_notice', 'The staged import was discarded. No beneficiaries were removed.');
    }

    private function draftForOwner(string $worksheet, mixed $owner): CampaignWorksheetData
    {
        $campaign = $this->worksheets->findForOwner(
            $worksheet,
            $this->ownerType($owner),
            (string) $owner->getAuthIdentifier(),
        );
        abort_unless($campaign instanceof CampaignWorksheetData && $campaign->status === 'draft', 404);

        return $campaign;
    }

    private function ownerType(mixed $owner): string
    {
        return $owner->getMorphClass();
    }
}
