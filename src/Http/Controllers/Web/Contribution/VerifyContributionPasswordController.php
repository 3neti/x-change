<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Contribution;

use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use LBHurtado\SettlementEnvelope\Models\EnvelopeContributionToken;
use LBHurtado\XChange\Http\Requests\VerifyContributionPasswordRequest;
use LBHurtado\XChange\Services\Settlement\ContributionPasswordSession;

final class VerifyContributionPasswordController extends Controller
{
    public function __invoke(VerifyContributionPasswordRequest $request, ContributionPasswordSession $passwords): Response
    {
        $input = $request->validated();
        $token = EnvelopeContributionToken::query()->where('token', $input['token'])->first();

        abort_unless($token !== null && $passwords->verify($token, $input['password'], $request->session()), 404);

        return response()->noContent()->header('Cache-Control', 'no-store, private');
    }
}
