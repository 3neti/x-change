<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Campaigns;

use Illuminate\Http\UploadedFile;
use InvalidArgumentException;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use SplFileObject;

final class CampaignWorksheetTabularReader
{
    /**
     * @return array{headers: array<int, string>, rows: array<int, array<string, string>>, sheet: ?string}
     */
    public function read(UploadedFile $file): array
    {
        $extension = mb_strtolower((string) $file->getClientOriginalExtension());

        return $extension === 'xlsx'
            ? $this->readXlsx($file)
            : $this->readCsv($file);
    }

    /**
     * @return array{headers: array<int, string>, rows: array<int, array<string, string>>, sheet: null}
     */
    private function readCsv(UploadedFile $file): array
    {
        $csv = new SplFileObject($file->getRealPath(), 'r');
        $csv->setFlags(SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY);
        $csv->setCsvControl(',');
        $headers = $this->headers($csv->fgetcsv() ?: []);
        $rows = [];

        while (! $csv->eof()) {
            $values = $csv->fgetcsv();
            if ($values === false || $values === [null]) {
                continue;
            }

            $this->guardColumnCount($values);
            $row = $this->associate($headers, $values);
            if ($this->hasValues($row)) {
                $rows[] = $row;
                $this->guardRowCount($rows);
            }
        }

        return ['headers' => $headers, 'rows' => $rows, 'sheet' => null];
    }

    /**
     * @return array{headers: array<int, string>, rows: array<int, array<string, string>>, sheet: string}
     */
    private function readXlsx(UploadedFile $file): array
    {
        $reader = IOFactory::createReaderForFile($file->getRealPath());
        $reader->setReadDataOnly(false);
        $spreadsheet = $reader->load($file->getRealPath());
        $sheet = $spreadsheet->getActiveSheet();
        $highestRow = $sheet->getHighestDataRow();
        $highestColumn = $sheet->getHighestDataColumn();
        $columnCount = Coordinate::columnIndexFromString($highestColumn);

        $this->guardColumnCount(array_fill(0, $columnCount, ''));
        $headers = [];
        $rows = [];

        for ($rowNumber = 1; $rowNumber <= $highestRow; $rowNumber++) {
            $values = [];
            for ($column = 1; $column <= $columnCount; $column++) {
                $cell = $sheet->getCell([$column, $rowNumber]);
                if ($cell->getDataType() === DataType::TYPE_FORMULA) {
                    throw new InvalidArgumentException(
                        sprintf('Spreadsheet formulas are not accepted. Replace the formula in row %d with its displayed value.', $rowNumber),
                    );
                }

                $values[] = $this->cellValue($cell->getValue());
            }

            if ($rowNumber === 1) {
                $headers = $this->headers($values);

                continue;
            }

            $row = $this->associate($headers, $values);
            if ($this->hasValues($row)) {
                $rows[] = $row;
                $this->guardRowCount($rows);
            }
        }

        $sheetTitle = $sheet->getTitle();
        $spreadsheet->disconnectWorksheets();

        return ['headers' => $headers, 'rows' => $rows, 'sheet' => $sheetTitle];
    }

    /**
     * @param  array<int, mixed>  $headers
     * @return array<int, string>
     */
    private function headers(array $headers): array
    {
        $normalized = array_map(function (mixed $header, int $index): string {
            $value = trim($this->cellValue($header));
            if ($index === 0) {
                $value = preg_replace('/^\xEF\xBB\xBF/', '', $value) ?? $value;
            }

            return $value;
        }, $headers, array_keys($headers));

        $this->guardColumnCount($normalized);

        if ($normalized === [] || in_array('', $normalized, true)) {
            throw new InvalidArgumentException('Every imported column needs a header.');
        }

        $duplicates = array_diff_assoc($normalized, array_unique($normalized));
        if ($duplicates !== []) {
            throw new InvalidArgumentException(
                sprintf('Duplicate column header [%s] is not allowed.', (string) reset($duplicates)),
            );
        }

        return array_values($normalized);
    }

    /**
     * @param  array<int, string>  $headers
     * @param  array<int, mixed>  $values
     * @return array<string, string>
     */
    private function associate(array $headers, array $values): array
    {
        $values = array_slice(array_pad($values, count($headers), ''), 0, count($headers));
        $row = [];

        foreach ($headers as $index => $header) {
            $value = trim($this->cellValue($values[$index] ?? ''));
            if (mb_strlen($value) > $this->maximumCellCharacters()) {
                throw new InvalidArgumentException(
                    sprintf('A value in column [%s] exceeds the %d character limit.', $header, $this->maximumCellCharacters()),
                );
            }

            $row[$header] = $value;
        }

        return $row;
    }

    private function cellValue(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (! is_scalar($value)) {
            throw new InvalidArgumentException('The worksheet contains an unsupported cell value.');
        }

        return (string) $value;
    }

    /** @param array<string, string> $row */
    private function hasValues(array $row): bool
    {
        return array_any($row, fn (string $value): bool => $value !== '');
    }

    /** @param array<int, mixed> $columns */
    private function guardColumnCount(array $columns): void
    {
        if (count($columns) > $this->maximumColumns()) {
            throw new InvalidArgumentException(
                sprintf('Worksheet files may contain at most %d columns.', $this->maximumColumns()),
            );
        }
    }

    /** @param array<int, mixed> $rows */
    private function guardRowCount(array $rows): void
    {
        if (count($rows) > $this->maximumRows()) {
            throw new InvalidArgumentException(
                sprintf('Worksheet files may contain at most %d beneficiary rows.', $this->maximumRows()),
            );
        }
    }

    private function maximumRows(): int
    {
        return (int) config('x-change.campaigns.imports.maximum_rows', 10_000);
    }

    private function maximumColumns(): int
    {
        return (int) config('x-change.campaigns.imports.maximum_columns', 30);
    }

    private function maximumCellCharacters(): int
    {
        return (int) config('x-change.campaigns.imports.maximum_cell_characters', 2_000);
    }
}
