<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Cockpit;

use DateTimeImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use LBHurtado\XChange\Actions\Funding\AttachFundingRequestEvidence;
use LBHurtado\XChange\Actions\Funding\CreateFundingRequest;
use LBHurtado\XChange\Contracts\WalletAccessContract;
use LBHurtado\XChange\Data\Funding\CreateFundingRequestData;
use LBHurtado\XChange\Enums\FundingRequestType;
use LBHurtado\XChange\Http\Requests\Web\Cockpit\CreateCockpitFundingRequestRequest;
use RuntimeException;

class CockpitFundingRequestController extends Controller
{
    public function __invoke(
        CreateCockpitFundingRequestRequest $request,
        WalletAccessContract $wallets,
        CreateFundingRequest $create,
        AttachFundingRequestEvidence $attachEvidence,
    ): RedirectResponse {
        $actor = $request->user();
        $account = $wallets->resolveForUser($actor);
        $validated = $request->validated();
        $fundingRequest = DB::transaction(function () use (
            $account,
            $actor,
            $attachEvidence,
            $create,
            $request,
            $validated,
        ) {
            $fundingRequest = $create->handle(new CreateFundingRequestData(
                accountReference: $this->accountReference($account),
                requesterType: $actor::class,
                requesterId: (string) $actor->getAuthIdentifier(),
                fundingType: FundingRequestType::from($validated['funding_type']),
                requestedValueMinor: (int) $validated['requested_value_minor'],
                currency: $validated['currency'],
                description: $validated['description'],
                idempotencyKey: $validated['idempotency_key'],
                externalReference: ($validated['external_reference'] ?? '') ?: null,
                occurredOn: isset($validated['occurred_on'])
                    ? new DateTimeImmutable($validated['occurred_on'])
                    : null,
                requesterNotes: ($validated['requester_notes'] ?? '') ?: null,
            ));
            $evidence = $request->file('evidence_document');

            if ($evidence !== null) {
                $attachEvidence->handle(
                    fundingRequest: $fundingRequest,
                    file: $evidence,
                    documentType: (string) $validated['evidence_document_type'],
                    actor: $actor,
                );
            }

            return $fundingRequest;
        }, 3);

        return redirect()
            ->route('x-change.cockpit.funding.index', ['mode' => 'pay_code'])
            ->with(
                'funding_request_submitted_reference',
                $fundingRequest->reference,
            )
            ->with(
                'funding_notice',
                'Funding requested. Share the Pay Code if you want to follow up.',
            );
    }

    private function accountReference(mixed $account): string
    {
        $uuid = data_get($account, 'uuid');

        if (is_string($uuid) && trim($uuid) !== '') {
            return 'wallet:'.trim($uuid);
        }

        throw new RuntimeException('Funding Account reference could not be resolved.');
    }
}
