<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Settlement;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use LBHurtado\SettlementEnvelope\Services\DriverService;
use LBHurtado\SettlementEnvelope\Services\EnvelopeService;
use LBHurtado\Voucher\Models\Voucher;
use Symfony\Component\HttpFoundation\File\File;

final class IssuerInvoiceDrafts
{
    public function create(Model $owner, UploadedFile $file): string
    {
        $disk = (string) config('settlement-envelope.contribution_storage_disk', 'local');
        if (config("filesystems.disks.{$disk}.visibility") !== 'private') {
            throw ValidationException::withMessages(['invoice' => 'Private invoice storage is unavailable.']);
        }
        $reference = (string) Str::uuid();
        $path = $file->store('x-change/invoice-drafts', $disk);
        if (! is_string($path)) {
            throw ValidationException::withMessages(['invoice' => 'The invoice could not be stored.']);
        }
        try {
            DB::table('x_change_issuer_invoice_drafts')->insert([
                'reference' => $reference, 'document_id' => (string) Str::ulid(), 'owner_type' => $owner->getMorphClass(), 'owner_id' => (string) $owner->getKey(),
                'disk' => $disk, 'path' => $path, 'filename' => basename($file->getClientOriginalName()),
                'sha256' => hash_file('sha256', $file->getRealPath()), 'expires_at' => now()->addDays(7),
                'created_at' => now(), 'updated_at' => now(),
            ]);
        } catch (\Throwable $exception) {
            Storage::disk($disk)->delete($path);
            throw $exception;
        }

        return $reference;
    }

    public function documentId(Model $owner, string $reference): string
    {
        $id = DB::table('x_change_issuer_invoice_drafts')->where('reference', $reference)
            ->where('owner_type', $owner->getMorphClass())->where('owner_id', (string) $owner->getKey())->value('document_id');
        if (! is_string($id) || $id === '') {
            throw ValidationException::withMessages(['invoice' => 'The invoice is unavailable.']);
        }

        return $id;
    }

    public function bind(Model $owner, string $reference, string $orderKey): void
    {
        $orderKey = hash('sha256', $orderKey);
        DB::transaction(function () use ($owner, $reference, $orderKey): void {
            $draft = DB::table('x_change_issuer_invoice_drafts')->where('reference', $reference)->lockForUpdate()->first();
            if ($draft === null || $draft->owner_type !== $owner->getMorphClass() || $draft->owner_id !== (string) $owner->getKey()
                || ($draft->order_key === null && now()->greaterThanOrEqualTo($draft->expires_at)) || $draft->voucher_id !== null
                || ($draft->order_key !== null && ! hash_equals($draft->order_key, $orderKey))
                || ! Storage::disk($draft->disk)->exists($draft->path)) {
                throw ValidationException::withMessages(['invoice' => 'The invoice is unavailable for this order.']);
            }
            DB::table('x_change_issuer_invoice_drafts')->where('reference', $reference)->update(['order_key' => $orderKey, 'updated_at' => now()]);
        });
    }

    public function assertAllowed(Model $owner, string $reference, bool $philhealth): void
    {
        $draft = DB::table('x_change_issuer_invoice_drafts')->where('reference', $reference)
            ->where('owner_type', $owner->getMorphClass())->where('owner_id', (string) $owner->getKey())->first();
        if ($draft === null || config("filesystems.disks.{$draft->disk}.visibility") !== 'private') {
            throw ValidationException::withMessages(['invoice' => 'The document is unavailable.']);
        }
        $driver = app(DriverService::class)->load($philhealth ? 'philhealth.bst.demo' : 'x-change.documents', '1.0.0');
        $policy = $driver->getDocumentType($philhealth ? 'HOSPITAL_BILL' : 'INVOICE');
        $this->withVerifiedFile($draft, function (string $path) use ($policy): void {
            $file = new File($path);
            if ($policy === null || ! in_array($file->getMimeType(), $policy->allowed_mimes, true)
                || $file->getSize() > $policy->max_size_mb * 1024 * 1024) {
                throw ValidationException::withMessages(['invoice' => 'This document type or size is not supported by the selected workflow.']);
            }
        });
    }

    public function attach(Model $owner, Voucher $voucher, string $reference, string $orderKey, string $documentType, ?string $documentId = null, ?int $sequence = null): ?array
    {
        $orderKey = hash('sha256', $orderKey);
        $draft = DB::table('x_change_issuer_invoice_drafts')->where('reference', $reference)->lockForUpdate()->first();
        if ($draft === null || $draft->owner_type !== $owner->getMorphClass() || $draft->owner_id !== (string) $owner->getKey()
            || ! is_string($draft->order_key) || ! hash_equals($draft->order_key, $orderKey)
            || ($documentId !== null && $draft->document_id !== $documentId)
            || ($draft->voucher_id !== null && (string) $draft->voucher_id !== (string) $voucher->getKey())
            || config("filesystems.disks.{$draft->disk}.visibility") !== 'private') {
            throw ValidationException::withMessages(['invoice' => 'The invoice is unavailable for this issuance.']);
        }
        if ($draft->voucher_id !== null) {
            return null;
        }
        $attachment = $this->withVerifiedFile($draft, fn (string $path) => app(EnvelopeService::class)->uploadAttachment(
            envelope: $voucher->envelope()->lockForUpdate()->firstOrFail(),
            docType: $documentType, file: new UploadedFile($path, $draft->filename, null, null, true), actor: $owner,
            metadata: ['contributor_role' => 'issuer', 'shared_with_payer' => true], storageDisk: $draft->disk,
            documentId: $documentId, auditReference: $sequence !== null ? sprintf('%s-DOC-%03d', $voucher->code, $sequence) : null,
        ));
        try {
            DB::table('x_change_issuer_invoice_drafts')->where('reference', $reference)->update([
                'voucher_id' => $voucher->getKey(), 'updated_at' => now(),
            ]);
        } catch (\Throwable $exception) {
            Storage::disk($attachment->disk)->delete($attachment->file_path);
            throw $exception;
        }

        return ['disk' => $attachment->disk, 'path' => $attachment->file_path];
    }

    private function withVerifiedFile(object $draft, callable $callback): mixed
    {
        $source = Storage::disk($draft->disk)->readStream($draft->path);
        if (! is_resource($source)) {
            throw ValidationException::withMessages(['invoice' => 'The invoice file is missing or has changed.']);
        }
        $temporary = tmpfile();
        if ($temporary === false) {
            fclose($source);
            throw ValidationException::withMessages(['invoice' => 'The invoice could not be checked.']);
        }
        try {
            $limit = max(1, min(10240, (int) config('x-change.documents.issuer_max_kilobytes', 10240))) * 1024;
            $copied = stream_copy_to_stream($source, $temporary, $limit + 1);
            $path = stream_get_meta_data($temporary)['uri'];
            if ($copied === false || $copied > $limit || ! hash_equals($draft->sha256, hash_file('sha256', $path))) {
                throw ValidationException::withMessages(['invoice' => 'The invoice file is missing or has changed.']);
            }

            return $callback($path);
        } finally {
            fclose($source);
            fclose($temporary);
        }
    }
}
