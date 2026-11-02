<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Contribution;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use LBHurtado\SettlementEnvelope\Services\EnvelopeService;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Actions\Claim\ValidateCompiledClaimVoucher;
use LBHurtado\XChange\Contracts\VoucherFlowCapabilityResolverContract;
use LBHurtado\XChange\Http\Requests\UploadParticipantDocumentRequest;
use LBHurtado\XChange\Services\Settlement\ParticipantDocumentSession;
use LBHurtado\XChange\Services\Settlement\V2EvidenceEventJournal;
use LBHurtado\XChange\Services\VoucherCollectionProgressService;

final class UploadParticipantDocumentController extends Controller
{
    public function __invoke(UploadParticipantDocumentRequest $request, string $code, ParticipantDocumentSession $sessions, EnvelopeService $envelopes, VoucherFlowCapabilityResolverContract $capabilities, VoucherCollectionProgressService $progress, ValidateCompiledClaimVoucher $claims, V2EvidenceEventJournal $events): JsonResponse
    {
        $role = (string) $request->route('participant_role');
        abort_unless(in_array($role, ['payer', 'claimant'], true), 404);
        $voucher = Voucher::query()->where('code', strtoupper(trim($code)))->firstOrFail();
        $input = $request->validated();
        abort_unless($sessions->allows($request, $voucher, $role, $input['document_type']), 404);
        $disk = (string) config('settlement-envelope.contribution_storage_disk', 'local');
        abort_unless(config("filesystems.disks.{$disk}.visibility") === 'private', 503);
        abort_unless(is_string(config('app.key')) && trim(config('app.key')) !== '', 503);

        $document = DB::transaction(function () use ($request, $voucher, $role, $input, $sessions, $envelopes, $capabilities, $progress, $claims, $disk, $events) {
            $lockedVoucher = Voucher::query()->lockForUpdate()->findOrFail($voucher->getKey());
            $flow = $capabilities->resolve($lockedVoucher);
            abort_unless($sessions->allows($request, $lockedVoucher, $role, $input['document_type']), 404);
            abort_unless($role === 'payer'
                ? ($flow->can_collect && ! $progress->compute($lockedVoucher)->is_fully_collected)
                : ($flow->can_disburse && $claims->handle($lockedVoucher) === null), 404);
            $envelope = $lockedVoucher->envelope()->lockForUpdate()->firstOrFail();

            $document = $envelopes->uploadAttachment(
                envelope: $envelope,
                docType: $input['document_type'],
                file: $input['file'],
                metadata: ['contributor_role' => $role, 'participant_session_hash' => $sessions->fingerprint($request, $lockedVoucher, $role)],
                storageDisk: $disk,
            );
            $events->settlementReadyAfterReview($lockedVoucher->fresh());

            return $document;
        });

        return response()->json(['id' => $document->getKey(), 'document_type' => $document->doc_type, 'review_status' => $document->review_status], 201)
            ->header('Cache-Control', 'no-store, private');
    }
}
