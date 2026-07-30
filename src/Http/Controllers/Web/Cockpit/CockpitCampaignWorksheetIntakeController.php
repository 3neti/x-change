<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Cockpit;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Controller;
use InvalidArgumentException;
use LBHurtado\XCampaign\Contracts\CampaignWorksheetIntakeRepository;
use LBHurtado\XCampaign\Data\CampaignWorksheetData;
use LBHurtado\XCampaign\Data\CampaignWorksheetIntakeData;
use LBHurtado\XChange\Http\Requests\Web\Cockpit\ConvertCampaignWorksheetIntakeRequest;
use LBHurtado\XChange\Http\Requests\Web\Cockpit\StageCampaignWorksheetIntakeRequest;
use LBHurtado\XChange\Http\Requests\Web\Cockpit\UpdateCampaignWorksheetIntakeRequest;
use LBHurtado\XChange\Services\Campaigns\CampaignImportScrutiny;
use LBHurtado\XChange\Services\Campaigns\CampaignWorksheetImportNormalizer;
use LBHurtado\XChange\Services\Campaigns\CampaignWorksheetTabularReader;
use Throwable;

class CockpitCampaignWorksheetIntakeController extends Controller
{
    public function __construct(
        private readonly CampaignWorksheetIntakeRepository $intakes,
        private readonly CampaignWorksheetTabularReader $reader,
        private readonly CampaignWorksheetImportNormalizer $normalizer,
        private readonly CampaignImportScrutiny $scrutiny,
    ) {}

    public function store(StageCampaignWorksheetIntakeRequest $request): RedirectResponse
    {
        $owner = $request->user();
        $file = $request->file('file');
        abort_unless($file instanceof UploadedFile && $file->isValid(), 422);

        try {
            $active = $this->intakes->activeForOwner($owner->getMorphClass(), (string) $owner->getAuthIdentifier());
            if ($active instanceof CampaignWorksheetIntakeData) {
                throw new InvalidArgumentException('Finish or discard the current import review first.');
            }

            $source = $this->reader->read($file);
            if ($source['rows'] === []) {
                throw new InvalidArgumentException('The file contains no beneficiary rows.');
            }

            $mapping = $this->normalizer->detectMapping($source['headers']);
            $suggestion = $this->scrutiny->suggest($file->getClientOriginalName(), $source['headers'], $mapping);
            $rows = $this->normalizer->normalizeRows(
                $source['rows'],
                $mapping,
                (string) $suggestion['fulfillment_mode'],
            );
            $intake = $this->intakes->stage(new CampaignWorksheetIntakeData(
                reference: null,
                ownerType: $owner->getMorphClass(),
                ownerId: (string) $owner->getAuthIdentifier(),
                status: 'staged',
                sourceName: $file->getClientOriginalName(),
                sourceFormat: mb_strtolower((string) $file->getClientOriginalExtension()) === 'xlsx' ? 'xlsx' : 'csv',
                contentHash: hash_file('sha256', $file->getRealPath()),
                rowCount: count($source['rows']),
                sourceHeaders: $source['headers'],
                sourceSheet: $source['sheet'],
                mapping: $mapping,
                suggestion: $suggestion,
                rows: $rows,
            ));
        } catch (Throwable $exception) {
            return to_route('x-change.cockpit.campaigns.index')->withErrors(['file' => $exception->getMessage()]);
        }

        if ($intake->status === 'converted' && is_string($intake->convertedWorksheetReference)) {
            return to_route('x-change.cockpit.campaigns.show', $intake->convertedWorksheetReference)
                ->with('campaign_notice', 'This file was already added to a Campaign.');
        }

        return to_route('x-change.cockpit.campaigns.index')
            ->with('campaign_notice', 'Review the suggested Campaign and beneficiary rows before adding them.');
    }

    public function update(UpdateCampaignWorksheetIntakeRequest $request, string $intake): RedirectResponse
    {
        $owner = $request->user();
        $staged = $this->owned($intake, $owner);
        $validated = $request->validated();
        $mapping = array_filter((array) $validated['mapping'], fn (mixed $value): bool => is_string($value) && $value !== '');
        if (count($mapping) !== count(array_unique($mapping))) {
            return to_route('x-change.cockpit.campaigns.index')
                ->withErrors(['mapping' => 'Each source column may be mapped only once.']);
        }
        if (array_diff(array_values($mapping), $staged->sourceHeaders) !== []) {
            return to_route('x-change.cockpit.campaigns.index')
                ->withErrors(['mapping' => 'A selected source column is unavailable.']);
        }

        $rows = $this->normalizer->normalizeRows(
            array_map(fn (array $row): array => (array) ($row['source'] ?? []), $staged->rows),
            $mapping,
            (string) $validated['fulfillment_mode'],
            (string) $validated['default_wallet'],
            (string) $validated['default_delivery_preference'],
        );
        $rows = array_map(function (array $row, int $index) use ($staged): array {
            $row['source_row'] = (int) ($staged->rows[$index]['source_row'] ?? $row['source_row']);

            return $row;
        }, $rows, array_keys($rows));
        $suggestion = [
            ...$staged->suggestion,
            'profile' => $validated['profile'],
            'fulfillment_mode' => $validated['fulfillment_mode'],
            'needs_fulfillment_choice' => false,
            'default_wallet' => $validated['default_wallet'],
            'default_delivery_preference' => $validated['default_delivery_preference'],
        ];
        $this->intakes->replaceReview(
            $intake,
            $owner->getMorphClass(),
            (string) $owner->getAuthIdentifier(),
            $mapping,
            $suggestion,
            $rows,
        );

        return to_route('x-change.cockpit.campaigns.index')
            ->with('campaign_notice', 'The import review was refreshed.');
    }

    public function convert(ConvertCampaignWorksheetIntakeRequest $request, string $intake): RedirectResponse
    {
        $owner = $request->user();
        $staged = $this->owned($intake, $owner);
        $validated = $request->validated();
        if ($validated['profile'] !== data_get($staged->suggestion, 'profile')
            || $validated['fulfillment_mode'] !== data_get($staged->suggestion, 'fulfillment_mode')) {
            return to_route('x-change.cockpit.campaigns.index')
                ->withErrors(['intake' => 'Recheck the rows after changing the purpose or recipient method.']);
        }
        $hasInvalidRows = collect($staged->rows)->contains('status', 'invalid');
        if ($hasInvalidRows && ! $validated['exclude_invalid_rows']) {
            return to_route('x-change.cockpit.campaigns.index')
                ->withErrors(['exclude_invalid_rows' => 'Confirm that invalid rows should stay out of this Campaign.']);
        }

        try {
            $worksheet = $this->intakes->convert(
                $intake,
                $owner->getMorphClass(),
                (string) $owner->getAuthIdentifier(),
                new CampaignWorksheetData(
                    reference: null,
                    ownerType: $owner->getMorphClass(),
                    ownerId: (string) $owner->getAuthIdentifier(),
                    profile: $validated['profile'],
                    name: $validated['name'],
                    fulfillmentMode: $validated['fulfillment_mode'],
                    deliveryPlan: ['csv'],
                ),
                array_map('intval', $validated['included_source_rows']),
            );
        } catch (InvalidArgumentException $exception) {
            return to_route('x-change.cockpit.campaigns.index')->withErrors(['intake' => $exception->getMessage()]);
        }

        return to_route('x-change.cockpit.campaigns.show', $worksheet->reference)
            ->with('campaign_notice', sprintf('%d beneficiaries were added to %s.', count($worksheet->rows), $worksheet->name));
    }

    public function destroy(Request $request, string $intake): RedirectResponse
    {
        $owner = $request->user();
        $this->intakes->discard($intake, $owner->getMorphClass(), (string) $owner->getAuthIdentifier());

        return to_route('x-change.cockpit.campaigns.index')
            ->with('campaign_notice', 'The import review was discarded. No Campaign was created.');
    }

    private function owned(string $reference, mixed $owner): CampaignWorksheetIntakeData
    {
        $intake = $this->intakes->findForOwner(
            $reference,
            $owner->getMorphClass(),
            (string) $owner->getAuthIdentifier(),
        );
        abort_unless($intake instanceof CampaignWorksheetIntakeData && $intake->status === 'staged', 404);

        return $intake;
    }
}
