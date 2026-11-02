<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Payment;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Contracts\VoucherFlowCapabilityResolverContract;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class SharedInvoiceController extends Controller
{
    public function __invoke(string $code, string $attachment, VoucherFlowCapabilityResolverContract $capabilities): StreamedResponse
    {
        $voucher = Voucher::query()->where('code', strtoupper(trim($code)))->firstOrFail();
        abort_unless($capabilities->resolve($voucher)->can_collect, 404);
        $document = $voucher->envelope()->firstOrFail()->attachments()->findOrFail($attachment);
        $details = data_get($voucher->metadata, 'instructions.payment_details');
        abort_unless(data_get($document->metadata, 'shared_with_payer') === true
            && data_get($document->metadata, 'contributor_role') === 'issuer'
            && (! is_array($details) || in_array($document->document_id, array_column($details['documents'] ?? [], 'id'), true))
            && config('filesystems.disks.'.$document->disk.'.visibility') === 'private', 404);

        $inline = in_array($document->mime_type, ['application/pdf', 'image/jpeg', 'image/png'], true);

        return Storage::disk($document->disk)->response(
            $document->file_path,
            $document->original_filename,
            [
                'Content-Type' => $document->mime_type,
                'Cache-Control' => 'private, no-store',
                'X-Content-Type-Options' => 'nosniff',
            ],
            $inline ? 'inline' : 'attachment',
        );
    }
}
