<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\PublicIssuance;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;
use LBHurtado\XChange\Models\IssuanceBatch;

final class PublicIssuanceBatchPayCodesPageController extends Controller
{
    public function __invoke(Request $request, IssuanceBatch $batch): Response
    {
        return Inertia::render('x-change/public/BatchPayCodes', [
            'batch_reference' => $batch->reference,
            'batch_endpoint' => route(
                'x-change.public-auto-generate.batches.show',
                ['batch' => $batch->reference],
                false,
            ),
            'download_endpoint' => route(
                'x-change.public-auto-generate.batches.download',
                ['batch' => $batch->reference],
                false,
            ),
            'detail_url_template' => route(
                'x-change.public-auto-generate.batches.pay-codes.detail',
                ['batch' => $batch->reference, 'code' => '__CODE__'],
                false,
            ),
            'auto_generate_url' => route('x-change.public-auto-generate.show', [], false),
        ]);
    }
}
