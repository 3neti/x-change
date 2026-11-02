<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Cockpit;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use LBHurtado\SettlementEnvelope\Models\EnvelopeAuditLog;
use LBHurtado\SettlementEnvelope\Services\CreateContributionInvitation;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Http\Requests\CreateContributionInvitationRequest;

final class CockpitContributionInvitationController extends Controller
{
    public function store(CreateContributionInvitationRequest $request, string $code, CreateContributionInvitation $create): JsonResponse
    {
        $voucher = $this->ownedVoucher($request, $code);
        $envelope = $voucher->envelope()->firstOrFail();
        $input = $request->validated();
        try {
            $token = $create->handle($envelope, $request->user(), $input['document_types'], now()->addDays((int) $input['expires_days']), $input['password'] ?? null);
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['document_types' => $exception->getMessage()]);
        }

        return response()->json([
            'id' => $token->getKey(),
            'token' => $token->token,
            'expires_at' => $token->expires_at->toIso8601String(),
            'revoke_url' => route('x-change.cockpit.contribution-invitations.destroy', ['code' => $voucher->code, 'invitation' => $token->getKey()]),
        ], 201)->header('Cache-Control', 'no-store, private');
    }

    public function destroy(Request $request, string $code, string $invitation): Response
    {
        $voucher = $this->ownedVoucher($request, $code);
        DB::transaction(function () use ($voucher, $invitation, $request): void {
            $envelope = $voucher->envelope()->lockForUpdate()->firstOrFail();
            $token = $envelope->contributionTokens()->lockForUpdate()->findOrFail($invitation);
            if ($token->revoked_at !== null) {
                return;
            }
            $token->revoke();
            EnvelopeAuditLog::log($envelope, EnvelopeAuditLog::ACTION_CONTRIBUTION_TOKEN_REVOKED, actor: $request->user(), after: [
                'token_id' => $token->getKey(),
            ]);
        });

        return response()->noContent()->header('Cache-Control', 'no-store, private');
    }

    private function ownedVoucher(Request $request, string $code): Voucher
    {
        $voucher = Voucher::query()->where('code', strtoupper(trim($code)))->firstOrFail();
        $actor = $request->user();
        abort_unless($actor instanceof Model && $voucher->owner_type === $actor->getMorphClass()
            && (string) $voucher->owner_id === (string) $actor->getKey(), 404);

        return $voucher;
    }
}
