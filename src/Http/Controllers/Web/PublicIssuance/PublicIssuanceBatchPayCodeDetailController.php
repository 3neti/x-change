<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\PublicIssuance;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;
use LBHurtado\Contact\Models\Contact;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Contracts\VoucherAccessContract;
use LBHurtado\XChange\Models\IssuanceBatch;
use LBHurtado\XChange\Models\VoucherClaimEvidence;
use LBHurtado\XChange\Services\Claim\ClaimEvidenceArtifactReader;
use LBHurtado\XChange\Services\Claim\ContactKycEvidenceResolver;
use LBHurtado\XChange\Services\IssuanceBatch\IssuanceBatchAccess;
use LBHurtado\XChange\Services\PublicIssuance\PublicIssuanceVoucherActions;
use LBHurtado\XChange\Support\Cockpit\CockpitReadOnlyPageProps;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Throwable;

final class PublicIssuanceBatchPayCodeDetailController extends Controller
{
    public function __construct(
        private readonly CockpitReadOnlyPageProps $props,
        private readonly VoucherAccessContract $vouchers,
        private readonly IssuanceBatchAccess $batches,
        private readonly ContactKycEvidenceResolver $contactKycEvidence,
        private readonly PublicIssuanceVoucherActions $actions,
        private readonly PublicIssuanceVoucherActionPolicy $actionPolicy,
    ) {}

    public function page(IssuanceBatch $batch, string $code): Response
    {
        return Inertia::render('x-change/public/BatchPayCodeDetail', [
            'batch_reference' => $batch->reference,
            'code' => $code,
            'detail_endpoint' => route(
                'x-change.public-auto-generate.batches.pay-codes.data',
                ['batch' => $batch->reference, 'code' => $code],
                false,
            ),
            'batch_pay_codes_url' => route(
                'x-change.public-auto-generate.batches.pay-codes',
                ['batch' => $batch->reference],
                false,
            ),
        ]);
    }

    public function data(Request $request, IssuanceBatch $batch, string $code): JsonResponse
    {
        $this->batches->authorizeToken($request, $batch);
        $item = $this->batches->itemForCode($batch, $code);
        $voucher = $this->vouchers->findByCode($code);

        abort_unless($voucher instanceof Voucher && (string) $item->voucher_id === (string) $voucher->getKey(), 404);

        $props = $this->props->toVoucherDetailArray($code);
        $evidence = data_get($props, 'read_model.voucher.claims.evidence', []);

        if (is_array($evidence)) {
            foreach ($evidence as $index => $item) {
                if (! is_array($item) || ($item['revealable'] ?? false) !== true || ! isset($item['id'])) {
                    continue;
                }

                $source = ($item['legacy'] ?? false) === true ? 'input' : 'claim';
                $evidence[$index]['reveal_href'] = route(
                    'x-change.public-auto-generate.batches.pay-codes.evidence',
                    [
                        'batch' => $batch->reference,
                        'code' => $code,
                        'source' => $source,
                        'evidence' => (int) $item['id'],
                    ],
                    false,
                );
            }

            data_set($props, 'read_model.voucher.claims.evidence', $evidence);
        }

        data_set($props, 'public_actions', [
            'url' => route('x-change.public-auto-generate.batches.pay-codes.action', ['batch' => $batch->reference, 'code' => $code], false),
            ...$this->actionPolicy->permissions($voucher),
        ]);

        $this->appendContactKycEvidence($voucher, $props, $batch, $code);

        return response()->json($props);
    }

    public function action(Request $request, IssuanceBatch $batch, string $code): JsonResponse
    {
        $this->batches->authorizeToken($request, $batch);
        $item = $this->batches->itemForCode($batch, $code);
        $voucher = $this->vouchers->findByCode($code);
        abort_unless($voucher instanceof Voucher && (string) $item->voucher_id === (string) $voucher->getKey(), 404);
        $validated = $request->validate([
            'action' => ['required', 'in:extend,cancel,expire,request_refund'],
            'extension_hours' => ['nullable', 'integer', 'in:24,168'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $result = $this->actions->handle(
            $voucher,
            $validated['action'],
            isset($validated['extension_hours']) ? (int) $validated['extension_hours'] : null,
            (string) ($validated['reason'] ?? 'Public batch recovery'),
        );

        return response()->json([
            'message' => data_get($result, 'messages.0', 'Pay Code lifecycle updated.'),
            'result' => $result,
        ]);
    }

    public function evidence(
        Request $request,
        IssuanceBatch $batch,
        string $code,
        string $source,
        string $evidence,
        ClaimEvidenceArtifactReader $artifactReader,
    ): HttpResponse {
        $this->batches->authorizeToken($request, $batch);
        $item = $this->batches->itemForCode($batch, $code);
        $voucher = $this->vouchers->findByCode($code);

        abort_unless($voucher instanceof Voucher && (string) $item->voucher_id === (string) $voucher->getKey(), 404);

        [$contents, $mimeType, $filename] = match ($source) {
            'claim' => $this->claimEvidence($voucher, (int) $evidence, $artifactReader),
            'input' => $this->inputEvidence($voucher, (int) $evidence, $artifactReader),
            'kyc' => $this->contactKycEvidence($voucher, $evidence),
            default => abort(404),
        };

        return response($contents, 200, [
            'Content-Type' => $mimeType,
            'Content-Disposition' => HeaderUtils::makeDisposition(
                HeaderUtils::DISPOSITION_INLINE,
                $filename,
            ),
            'Cache-Control' => 'no-store, private, max-age=0',
            'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
            'X-Robots-Tag' => 'noindex, nofollow, noarchive',
        ]);
    }

    /** @return array{string, string, string} */
    private function claimEvidence(Voucher $voucher, int $evidence, ClaimEvidenceArtifactReader $artifactReader): array
    {
        $item = VoucherClaimEvidence::query()
            ->where('voucher_id', $voucher->getKey())
            ->findOrFail($evidence);

        try {
            $artifact = $artifactReader->stored($item);
        } catch (Throwable) {
            abort(404);
        }

        return [
            $artifact['contents'],
            $artifact['mime_type'],
            $item->requirement_key.'.'.$artifact['extension'],
        ];
    }

    /** @return array{string, string, string} */
    private function inputEvidence(Voucher $voucher, int $evidence, ClaimEvidenceArtifactReader $artifactReader): array
    {
        $input = $voucher->inputs()->findOrFail($evidence);
        $name = (string) $input->getAttribute('name');

        abort_unless(in_array($name, [
            'selfie',
            'signature',
            'kyc_id_front',
            'kyc_id_back',
            'location',
        ], true), 404);

        try {
            $artifact = $artifactReader->legacy((string) $input->getAttribute('value'), $name);
        } catch (Throwable) {
            abort(404);
        }

        return [
            $artifact['contents'],
            $artifact['mime_type'],
            $name.'.'.$artifact['extension'],
        ];
    }

    /** @param array<string, mixed> $props */
    private function appendContactKycEvidence(Voucher $voucher, array &$props, IssuanceBatch $batch, string $code): void
    {
        $contact = $voucher->contact;

        if (! $contact instanceof Contact) {
            return;
        }

        $evidence = (array) data_get($props, 'read_model.voucher.claims.evidence', []);

        foreach ($this->contactKycEvidence->resolve($contact) as $artifact) {
            $evidence[] = [
                'id' => (int) $artifact->mediaId,
                'kind' => 'image',
                'requirement_key' => $artifact->type === 'selfie' ? 'kyc_selfie' : 'kyc_government_id',
                'label' => $artifact->type === 'selfie' ? 'KYC Selfie' : 'Government ID',
                'summary' => $artifact->variant === null ? 'Contact KYC evidence' : ucfirst($artifact->variant).' contact KYC evidence',
                'revealable' => true,
                'source' => 'kyc',
                'reveal_href' => route(
                    'x-change.public-auto-generate.batches.pay-codes.evidence',
                    [
                        'batch' => $batch->reference,
                        'code' => $code,
                        'source' => 'kyc',
                        'evidence' => $artifact->mediaId,
                    ],
                    false,
                ),
            ];
        }

        data_set($props, 'read_model.voucher.claims.evidence', $evidence);
    }

    /** @return array{string, string, string} */
    private function contactKycEvidence(Voucher $voucher, string $mediaId): array
    {
        $contact = $voucher->contact;

        abort_unless($contact instanceof Contact && $contact->isKycApproved(), 404);

        try {
            $artifact = $this->contactKycEvidence->read($contact, $mediaId);
        } catch (Throwable) {
            abort(404);
        }

        return [
            $artifact['contents'],
            $artifact['mime_type'],
            'contact-kyc.'.$artifact['extension'],
        ];
    }
}
