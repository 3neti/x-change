<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Cockpit;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use LBHurtado\SettlementEnvelope\Models\EnvelopeAttachment;
use LBHurtado\XChange\Http\Requests\Web\Cockpit\ViewCockpitFundingRequestEvidenceRequest;
use LBHurtado\XChange\Models\FundingRequest;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class CockpitFundingRequestEvidenceController extends Controller
{
    public function __invoke(
        ViewCockpitFundingRequestEvidenceRequest $request,
        FundingRequest $fundingRequest,
        EnvelopeAttachment $attachment,
    ): StreamedResponse {
        $fundingRequest->loadMissing('voucher.envelope');

        abort_unless(
            $fundingRequest->voucher?->envelope?->getKey()
                === $attachment->envelope_id,
            404,
        );

        return Storage::disk($attachment->disk)->download(
            $attachment->file_path,
            $attachment->original_filename,
            [
                'Cache-Control' => 'no-store, private',
                'Pragma' => 'no-cache',
                'X-Content-Type-Options' => 'nosniff',
            ],
        );
    }
}
