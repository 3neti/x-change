<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Cockpit;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Controller;
use InvalidArgumentException;
use LBHurtado\XCampaign\Contracts\CampaignWorksheetImportRepository;
use LBHurtado\XCampaign\Contracts\CampaignWorksheetRepository;
use LBHurtado\XCampaign\Data\CampaignWorksheetImportData;
use LBHurtado\XChange\Http\Requests\Web\Cockpit\ApplyCampaignWorksheetImportRequest;
use LBHurtado\XChange\Http\Requests\Web\Cockpit\StageCampaignWorksheetCsvRequest;
use PhpOffice\PhpSpreadsheet\IOFactory;

class CockpitCampaignWorksheetImportController extends Controller
{
    public function __construct(
        private readonly CampaignWorksheetRepository $worksheets,
        private readonly CampaignWorksheetImportRepository $imports,
    ) {}

    public function stage(StageCampaignWorksheetCsvRequest $request, string $worksheet): RedirectResponse
    {
        $owner = $request->user();
        $this->assertDraftOwner($worksheet, $owner);
        $file = $request->file('file');
        abort_unless($file instanceof UploadedFile && $file->isValid(), 422, 'The worksheet file could not be uploaded.');

        $sourceFormat = strtolower((string) $file->getClientOriginalExtension());
        $sourceRows = $sourceFormat === 'xlsx' ? $this->xlsxRows($file) : $this->csvRows((string) $file->get());
        [$mapping, $validRows, $errors] = $this->normalize($sourceRows);
        $import = $this->imports->stage(new CampaignWorksheetImportData(
            reference: null,
            worksheetReference: $worksheet,
            status: 'staged',
            sourceFormat: $sourceFormat === 'xlsx' ? 'xlsx' : 'csv',
            contentHash: hash_file('sha256', $file->getRealPath()),
            rowCount: count($sourceRows),
            validRows: $validRows,
            validationErrors: $errors,
            mapping: $mapping,
        ), $this->ownerType($owner), (string) $owner->getAuthIdentifier());

        $summary = $errors === []
            ? sprintf('%d rows are ready to apply. Nothing has been added yet.', count($validRows))
            : sprintf('%d of %d rows need attention before this import can be applied.', count($errors), count($sourceRows));

        return to_route('x-change.cockpit.campaigns.show', $worksheet)
            ->with('campaign_notice', $summary)
            ->with('campaign_import_reference', $import->reference);
    }

    public function apply(ApplyCampaignWorksheetImportRequest $request, string $worksheet, string $import): RedirectResponse
    {
        $owner = $request->user();

        try {
            $applied = $this->imports->apply($worksheet, $import, $this->ownerType($owner), (string) $owner->getAuthIdentifier());
        } catch (InvalidArgumentException $exception) {
            return to_route('x-change.cockpit.campaigns.show', $worksheet)->with('campaign_notice', $exception->getMessage());
        }

        return to_route('x-change.cockpit.campaigns.show', $worksheet)
            ->with('campaign_notice', sprintf('%d imported beneficiaries were added to the draft.', count($applied->validRows)));
    }

    /** @return array<int, array<string, string>> */
    private function csvRows(string $contents): array
    {
        $lines = preg_split('/\R/', trim($contents)) ?: [];
        $headers = array_map(fn (string $header): string => trim($header), str_getcsv((string) array_shift($lines)));

        return array_values(array_filter(array_map(function (string $line) use ($headers): ?array {
            if (trim($line) === '') {
                return null;
            }

            $values = str_getcsv($line);
            $row = array_combine($headers, array_pad($values, count($headers), ''));

            return is_array($row) ? array_map(fn (mixed $value): string => trim((string) $value), $row) : null;
        }, $lines)));
    }

    /** @return array<int, array<string, string>> */
    private function xlsxRows(UploadedFile $file): array
    {
        $sheet = IOFactory::load($file->getRealPath())->getActiveSheet();
        $rows = $sheet->toArray('', true, true, false);
        $headers = array_map(fn (mixed $header): string => trim((string) $header), array_shift($rows) ?? []);

        return array_values(array_filter(array_map(function (array $values) use ($headers): ?array {
            $row = array_combine($headers, array_pad($values, count($headers), ''));
            $normalized = is_array($row) ? array_map(fn (mixed $value): string => trim((string) $value), $row) : null;

            return $normalized !== null && array_filter($normalized, fn (string $value): bool => $value !== '') !== [] ? $normalized : null;
        }, $rows)));
    }

    /**
     * @param  array<int, array<string, string>>  $rows
     * @return array{0: array<string, string>, 1: array<int, array<string, mixed>>, 2: array<int, array<string, mixed>>}
     */
    private function normalize(array $rows): array
    {
        $mapping = [];
        $validRows = [];
        $errors = [];

        foreach ($rows as $row) {
            foreach (array_keys($row) as $header) {
                $canonical = $this->canonicalHeader($header);
                if ($canonical !== null) {
                    $mapping[$canonical] ??= $header;
                }
            }
        }

        foreach ($rows as $index => $row) {
            $beneficiary = array_filter([
                'name' => $this->value($row, $mapping, 'name'),
                'mobile' => $this->value($row, $mapping, 'mobile'),
                'bank_account' => $this->value($row, $mapping, 'bank_account'),
                'email' => $this->value($row, $mapping, 'email'),
                'remarks' => $this->value($row, $mapping, 'remarks'),
                'external_reference' => $this->value($row, $mapping, 'external_reference'),
            ], fn (?string $value): bool => $value !== null);
            $amountMinor = $this->amountMinor($row, $mapping);
            $messages = [];
            if (! isset($beneficiary['mobile']) && ! isset($beneficiary['bank_account'])) {
                $messages[] = 'A mobile number or bank account is required.';
            }
            if ($amountMinor < 1) {
                $messages[] = 'A positive amount or amount_minor is required.';
            }

            if ($messages !== []) {
                $errors[] = ['row' => $index + 2, 'messages' => $messages];

                continue;
            }

            $validRows[] = [
                'beneficiary' => $beneficiary,
                'amount_minor' => $amountMinor,
                'currency' => 'PHP',
                'delivery_preference' => $this->value($row, $mapping, 'delivery_preference') ?? 'manual',
            ];
        }

        return [$mapping, $validRows, $errors];
    }

    private function canonicalHeader(string $header): ?string
    {
        return match (str_replace([' ', '-', '.'], '_', strtolower(trim($header)))) {
            'name', 'beneficiary', 'beneficiary_name', 'recipient_name' => 'name',
            'mobile', 'mobile_number', 'phone', 'phone_number' => 'mobile',
            'bank_account', 'account_number', 'account' => 'bank_account',
            'email', 'email_address' => 'email',
            'remarks', 'notes', 'note' => 'remarks',
            'reference', 'external_reference', 'employee_id' => 'external_reference',
            'amount_minor', 'centavos' => 'amount_minor',
            'amount', 'value', 'php' => 'amount',
            'delivery', 'delivery_preference' => 'delivery_preference',
            default => null,
        };
    }

    /** @param array<string, string> $row @param array<string, string> $mapping */
    private function value(array $row, array $mapping, string $key): ?string
    {
        $value = isset($mapping[$key]) ? trim((string) ($row[$mapping[$key]] ?? '')) : '';

        return $value === '' ? null : $value;
    }

    /** @param array<string, string> $row @param array<string, string> $mapping */
    private function amountMinor(array $row, array $mapping): int
    {
        if (isset($mapping['amount_minor'])) {
            return max(0, (int) preg_replace('/[^0-9]/', '', (string) ($row[$mapping['amount_minor']] ?? '')));
        }

        $amount = preg_replace('/[^0-9.]/', '', (string) ($row[$mapping['amount']] ?? ''));

        return is_numeric($amount) ? (int) round((float) $amount * 100) : 0;
    }

    private function assertDraftOwner(string $worksheet, mixed $owner): void
    {
        $campaign = $this->worksheets->findForOwner($worksheet, $this->ownerType($owner), (string) $owner->getAuthIdentifier());
        abort_unless($campaign !== null && $campaign->status === 'draft', 404);
    }

    private function ownerType(mixed $owner): string
    {
        return $owner->getMorphClass();
    }
}
