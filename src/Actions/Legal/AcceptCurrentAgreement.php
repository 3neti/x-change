<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Legal;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use LBHurtado\XChange\Data\Legal\AgreementDocumentData;
use LBHurtado\XChange\Models\AgreementAcceptance;
use LBHurtado\XChange\Services\Legal\CurrentAgreementService;

final readonly class AcceptCurrentAgreement
{
    public function __construct(
        private CurrentAgreementService $agreements,
        private ReleaseDeferredOnboardingFunding $releaseDeferredFunding,
    ) {}

    public function handle(
        Authenticatable&Model $subject,
        Request $request,
        AgreementDocumentData $document,
    ): AgreementAcceptance {
        return DB::transaction(function () use ($subject, $request, $document): AgreementAcceptance {
            $acceptance = $this->agreements->accept($subject, $request, $document);
            $this->releaseDeferredFunding->handle($subject, $acceptance);

            return $acceptance;
        }, attempts: 5);
    }
}
