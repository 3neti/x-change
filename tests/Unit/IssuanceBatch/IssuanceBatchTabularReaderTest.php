<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use LBHurtado\XChange\Services\IssuanceBatch\IssuanceBatchTabularReader;

it('reads CSV uploads with stable headers and rows', function (): void {
    $path = tempnam(sys_get_temp_dir(), 'issuance-batch-').'.csv';
    file_put_contents($path, "Name,Mobile,Amount\nAna,09170000001,100.00\n");

    $result = (new IssuanceBatchTabularReader)->read(new UploadedFile($path, 'payroll.csv', 'text/csv', null, true));

    expect($result['headers'])->toBe(['Name', 'Mobile', 'Amount'])
        ->and($result['rows'])->toHaveCount(1)
        ->and($result['rows'][0]['Name'])->toBe('Ana');
});
